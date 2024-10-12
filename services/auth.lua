-- webpanel/services/auth.lua
-- Authentication interface
-- Copyright (C) 2024  1F616EMO
-- SPDX-License-Identifier: LGPL-3.0-or-later

local webpanel = webpanel

local logger = webpanel.internal.logger:sublogger("services.auth")

local auth

minetest.after(0, function()
    auth = minetest.get_auth_handler()
end)

local function wrap_require_auth(func)
    return function(id, cmd)
        if not auth then
            logger:warning("Client %s: Called before first globalstep", id)
            return {
                ok = false,
                err = "Called before first globalstep",
            }
        end

        return func(id, cmd)
    end
end

webpanel.register_service("auth_name_password", wrap_require_auth(function(id, cmd)
    local username, password = cmd.username, cmd.password
    if not (username and password) then
        logger:warning("Client %s: Missing username and/or password", id)
        return {
            ok = false,
            err = "Missing username and/or password",
        }
    end

    local entry = auth.get_auth(username)
    if not entry then
        return {
            ok = true,
            auth = false,
            auth_err = "Player does not exist",
        }
    end

    if minetest.check_password_entry(username, entry.password, password) then
        return {
            ok = true,
            auth = true,
        }
    else
        return {
            ok = true,
            auth = false,
            auth_err = "Invalid password",
        }
    end
end))

webpanel.register_service("auth_set_password", wrap_require_auth(function(id, cmd)
    local username, password, old_password = cmd.username, cmd.password, cmd.old_password
    if not (username and password) then
        logger:warning("Client %s: Missing username and/or password", id)
        return {
            ok = false,
            err = "Missing username and/or password",
        }
    end

    -- if old_password, check for it
    if cmd.old_password then
        local entry = auth.get_auth(username)
        if not entry then
            return {
                ok = false,
                err = "Player does not exist",
            }
        elseif not minetest.check_password_entry(username, entry.password, old_password) then
            return {
                ok = false,
                err = "Old password mismatch",
            }
        end
    end

    local hash = minetest.get_password_hash(username, password)
    minetest.set_player_password(username, hash)
    local resp = {
        ok = true
    }
    if cmd.get_email then
        resp.email = webpanel.get_user_email(username)
    end
    return resp
end))

webpanel.register_service("auth_get_privs", wrap_require_auth(function(id, cmd)
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
        privs = minetest.get_player_privs(username),
    }
end))

webpanel.register_service("auth_set_privs", wrap_require_auth(function(id, cmd)
    local username, privs = cmd.username, cmd.privs
    if not (username and privs) then
        logger:warning("Client %s: Missing username and/or privs", id)
        return {
            ok = false,
            err = "Missing username and/or privs",
        }
    end
    local warnings = {}
    for k, v in pairs(privs) do
        if v ~= true then
            warnings[#warnings+1] = string.format(
                "Invalid value %s for key %s", tostring(v), k)
            privs[k] = nil
        elseif not minetest.registered_privileges[k] then
            warnings[#warnings+1] = string.format(
                "Invalid privilege %s", k)
            privs[k] = nil
        end
    end

    minetest.set_player_privs(username, privs)
    return {
        ok = true,
        warnings = #warnings ~= 0 and warnings or nil,
    }
end))

webpanel.register_service("auth_change_privs", wrap_require_auth(function(id, cmd)
    local username, privs = cmd.username, cmd.privs
    if not (username and privs) then
        logger:warning("Client %s: Missing username and/or privs", id)
        return {
            ok = false,
            err = "Missing username and/or privs",
        }
    end
    local warnings = {}
    for k, v in pairs(privs) do
        if v ~= true and v ~= false then
            warnings[#warnings+1] = string.format(
                "Invalid value %s for key %s", tostring(v), k)
            privs[k] = nil
        elseif not minetest.registered_privileges[k] then
            warnings[#warnings+1] = string.format(
                "Invalid privilege %s", k)
            privs[k] = nil
        end
    end

    minetest.change_player_privs(username, privs)
    return {
        ok = true,
        warnings = #warnings ~= 0 and warnings or nil,
    }
end))


