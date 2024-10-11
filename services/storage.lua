-- webpanel/services/storage.lua
-- webpanel mod storage
-- Copyright (C) 2024  1F616EMO
-- SPDX-License-Identifier: LGPL-3.0-or-later

local webpanel = webpanel

local storage = minetest.get_mod_storage()
local logger = webpanel.internal.logger:sublogger("services.storage")

webpanel.register_service("storage_get", function(id, cmd)
    local key = cmd.key
    if not key then
        logger:warning("Client %s: Missing key", id)
        return {
            ok = false,
            err = "Missing key",
        }
    end
    key = "webpanel_storage:" .. key

    return {
        ok = true,
        value = storage:get(key)
    }
end)

webpanel.register_service("storage_get_string", function(id, cmd)
    local key = cmd.key
    if not key then
        logger:warning("Client %s: Missing key", id)
        return {
            ok = false,
            err = "Missing key",
        }
    end
    key = "webpanel_storage:" .. key

    return {
        ok = true,
        value = storage:get_string(key)
    }
end)

webpanel.register_service("storage_get_int", function(id, cmd)
    local key = cmd.key
    if not key then
        logger:warning("Client %s: Missing key", id)
        return {
            ok = false,
            err = "Missing key",
        }
    end
    key = "webpanel_storage:" .. key

    return {
        ok = true,
        value = storage:get_int(key)
    }
end)

webpanel.register_service("storage_get_float", function(id, cmd)
    local key = cmd.key
    if not key then
        logger:warning("Client %s: Missing key", id)
        return {
            ok = false,
            err = "Missing key",
        }
    end
    key = "webpanel_storage:" .. key

    return {
        ok = true,
        value = storage:get_float(key)
    }
end)

webpanel.register_service("storage_set_string", function(id, cmd)
    local key, value = cmd.key, cmd.value
    if not (key and value) then
        logger:warning("Client %s: Missing key and/or value", id)
        return {
            ok = false,
            err = "Missing key and/or value",
        }
    end
    key = "webpanel_storage:" .. key

    storage:set_string(key, value)
    return {
        ok = true,
    }
end)

webpanel.register_service("storage_set_int", function(id, cmd)
    local key, value = cmd.key, cmd.value
    if not (key and value) then
        logger:warning("Client %s: Missing key and/or value", id)
        return {
            ok = false,
            err = "Missing key and/or value",
        }
    end
    key = "webpanel_storage:" .. key

    storage:set_int(key, value)
    return {
        ok = true,
    }
end)

webpanel.register_service("storage_set_float", function(id, cmd)
    local key, value = cmd.key, cmd.value
    if not (key and value) then
        logger:warning("Client %s: Missing key and/or value", id)
        return {
            ok = false,
            err = "Missing key and/or value",
        }
    end
    key = "webpanel_storage:" .. key

    storage:set_float(key, value)
    return {
        ok = true,
    }
end)
