-- webpanel/services/password_reset.lua
-- Handle forget password reset
-- Copyright (C) 2024  1F616EMO
-- SPDX-License-Identifier: LGPL-3.0-or-later

local webpanel = webpanel

local storage = minetest.get_mod_storage()
local logger = webpanel.internal.logger:sublogger("services.password_reset")

local pending_confirms = {}

local charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"
local function random_char()
    local i = math.random(1, #charset)
    return string.sub(charset, i, i)
end

local function random_confirm_code()
    local confirm_code = ""
    for _ = 1, 10 do
        confirm_code = confirm_code .. random_char()
    end
    return confirm_code
end

local confirm_timeout = tonumber(minetest.settings:get("webpanel.email_confirm_timeout")) or 600

webpanel.register_service("password_reset_start", function(id, cmd)
    local username = cmd.username
    if not username then
        logger:warning("Client %s: Missing username", id)
        return {
            ok = false,
            err = "Missing username",
        }
    end

    for confirm_code, data in pairs(pending_confirms) do
        if data.name == username then
            pending_confirms[confirm_code] = nil
        end
    end

    local key = "webpanel_emails:" .. username
    local email = storage:get_string(key)
    if email == "" then
        return {
            ok = false,
            err = 'Missing email', -- This should not be known to the client
        }
    end

    local confirm_code = random_confirm_code()
    pending_confirms[confirm_code] = {
        name = username,
        time = os.time()
    }
    logger:action("Created confirmation code %s for player %s", confirm_code, username)
    return {
        ok = true,
        confirm_code = confirm_code,
        email = email,
    }
end)

webpanel.register_service("password_reset_end", function(id, cmd)
    local confirm_code = cmd.confirm_code
    if not confirm_code then
        logger:warning("Client %s: Missing confirm_code", id)
        return {
            ok = false,
            err = "Missing confirm_code",
        }
    end

    local data = pending_confirms[confirm_code]
    if not data then
        return {
            ok = false,
            err = 'Invalid token'
        }
    end
    pending_confirms[confirm_code] = nil

    return {
        ok = true,
        name = data.name,
    }
end)

local accu_dtime = 9
minetest.register_globalstep(function(dtime)
    accu_dtime = accu_dtime + dtime
    if accu_dtime <= 30 then
        return
    end
    accu_dtime = 0

    local now = os.time()
    for confirm_code, data in pairs(pending_confirms) do
        if now - data.time > confirm_timeout then
            logger:action("Invalidated token %s", confirm_code)
            pending_confirms[confirm_code] = nil
        end
    end
end)