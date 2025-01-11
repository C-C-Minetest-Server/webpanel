-- webpanel/services/email.lua
-- Handling player emails
-- Copyright (C) 2024  1F616EMO
-- SPDX-License-Identifier: LGPL-3.0-or-later

local webpanel = webpanel

local logger = webpanel.internal.logger:sublogger("services.email")

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

webpanel.register_service("email_get", function(id, cmd)
    local username = cmd.username
    if not username then
        logger:warning("Client %s: Missing username", id)
        return {
            ok = false,
            err = "Missing username",
        }
    end

    return {
        ok = true,
        email = webpanel.get_user_email(username)
    }
end)

webpanel.register_service("email_start_confirm", function(id, cmd)
    local username, email = cmd.username, cmd.email
    if not (username and email) then
        logger:warning("Client %s: Missing username and/or email", id)
        return {
            ok = false,
            err = "Missing username and/or email",
        }
    end

    for confirm_code, data in pairs(pending_confirms) do
        if data.name == username then
            pending_confirms[confirm_code] = nil
        end
    end

    local confirm_code = random_confirm_code()
    pending_confirms[confirm_code] = {
        name = username,
        email = email,
        time = os.time()
    }
    logger:action("Created confirmation code %s for player %s", confirm_code, username)
    return {
        ok = true,
        confirm_code = confirm_code,
    }
end)

webpanel.register_service("email_do_confirm", function(id, cmd)
    local confirm_code = cmd.confirm_code
    if not confirm_code then
        logger:warning("Client %s: Missing confirm_code", id)
        return {
            ok = false,
            err = "Missing confirm_code",
        }
    end

    local confirm_data = pending_confirms[confirm_code]
    if not confirm_data then
        return {
            ok = false,
            err = "Invalid confirmation code",
        }
    end

    local username = confirm_data.name
    local old_email = webpanel.get_user_email(username)
    local new_email = confirm_data.email
    webpanel.set_user_email(username, new_email)
    pending_confirms[confirm_code] = nil
    logger:action("Set email of %s to %s with token %s", username, new_email, confirm_code)
    return {
        ok = true,
        old_email = old_email,
        new_email = new_email,
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