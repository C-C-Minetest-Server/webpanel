-- webpanel/services/mediawiki.lua
-- MediaWiki Intergation
-- Copyright (C) 2024  1F616EMO
-- SPDX-License-Identifier: LGPL-3.0-or-later

local webpanel = webpanel

local logger = webpanel.internal.logger:sublogger("services.mediawiki")

local pending_confirms = {}
local rate_limits = {}

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

webpanel.register_service("mediawiki_get", function(id, cmd)
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
        meidawik_name = webpanel.get_user_mediawiki_account(username)
    }
end)

webpanel.register_service("mediawiki_start_confirm", function(id, cmd)
    local username, mediawiki_name = cmd.username, cmd.mediawiki_name
    if not (username and mediawiki_name) then
        logger:warning("Client %s: Missing username and/or mediawiki account name", id)
        return {
            ok = false,
            err = "Missing username and/or mediawiki account name",
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
        mediawiki_name = mediawiki_name,
        time = os.time()
    }
    logger:action("Created MediaWiki confirmation code %s for player %s", confirm_code, username)
    return {
        ok = true,
        confirm_code = confirm_code,
    }
end)

webpanel.register_service("mediawiki_do_confirm", function(id, cmd)
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
    local old_mediawiki_name = webpanel.get_user_mediawiki_account(username)
    local new_mediawiki_name = confirm_data.mediawiki_name
    webpanel.set_user_mediawiki_account(username, new_mediawiki_name)
    pending_confirms[confirm_code] = nil
    logger:action("Set MediaWiki account name of %s to %s with token %s", username, new_mediawiki_name, confirm_code)
    return {
        ok = true,
        username = username,
        old_mediawiki_name = old_mediawiki_name,
        new_mediawiki_name = new_mediawiki_name,
    }
end)

webpanel.register_service("mediawiki_init_ratelimit", function(id, cmd)
    local username = cmd.username
    if not username then
        logger:warning("Client %s: Missing username", id)
        return {
            ok = false,
            err = "Missing username",
        }
    end

    rate_limits[username] = os.time()
end)

webpanel.register_service("mediawiki_in_ratelimit", function(id, cmd)
    local username = cmd.username
    if not username then
        logger:warning("Client %s: Missing username", id)
        return {
            ok = false,
            err = "Missing username",
        }
    end

    local now = os.time()
    local start_time = rate_limits[username]
    if not rate_limits[username] or now - start_time > 30 then
        rate_limits[username] = nil
        return {
            ok = true,
            ratelimit = 0,
        }
    else
        return {
            ok = true,
            ratelimit = start_time + 30 - now,
        }
    end
end)

local accu_dtime = 8
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

    for username, start_time in pairs(rate_limits) do
        if now - start_time > 30 then
            rate_limits[username] = nil
        end
    end
end)