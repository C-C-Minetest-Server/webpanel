-- webpanel/src/chatcommand.lua
-- in-game Chatcommands
-- Copyright (C) 2024  1F616EMO
-- SPDX-License-Identifier: LGPL-3.0-or-later

local S = minetest.get_translator("webpanel")

minetest.register_chatcommand("webpanel_get_email", {
    privs = { ban = true },
    func = function(name, param)
        if param == "" then
            param = name
        end

        local email = webpanel.get_user_email(param)
        return true, S("Email of @1: @2", param, email == "" and S("Not set") or email)
    end,
})
