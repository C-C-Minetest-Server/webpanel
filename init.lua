-- webpanel/init.lua
-- Minetest Web Panel
-- Copyright (C) 2024  1F616EMO
-- SPDX-License-Identifier: LGPL-3.0-or-later

webpanel = {}

webpanel.internal = {}
webpanel.internal.logger = logging.logger("webpanel")
local logger = webpanel.internal.logger

do
	local ie = minetest.request_insecure_environment()
    if not ie then
        logger:raise("Please add `webpanel` to `secure.trusted_mods`!")
    end
	local dbg = ie.debug
	dbg.sethook()
	local old_thread_env = ie.getfenv(0)
	local old_string_metatable = dbg.getmetatable("")
	ie.setfenv(0, ie)
	dbg.setmetatable("", {__index = ie.string})
	local ok, ret = ie.pcall(ie.require, "socket")
	ie.setfenv(0, old_thread_env)
	dbg.setmetatable("", old_string_metatable)
	if not ok then ie.error(ret) end
	webpanel.internal.socket = ret
end


local MP = minetest.get_modpath("webpanel")

for _, name in ipairs({
    "services",
    "socket",
}) do
    dofile(MP .. DIR_DELIM .. "src" .. DIR_DELIM .. name .. ".lua")
end

for _, name in ipairs({
    "auth",
	"email",
	"password_reset",
    "storage",
}) do
    dofile(MP .. DIR_DELIM .. "services" .. DIR_DELIM .. name .. ".lua")
end

-- Garbage collection
setmetatable(webpanel.internal, { __mode = "v" })
webpanel.internal = nil
