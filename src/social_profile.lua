-- webpanel/src/social_profile.lua
-- Show email address on social profile
-- Copyright (C) 2024  1F616EMO
-- SPDX-License-Identifier: LGPL-3.0-or-later

if not core.get_modpath("social_profile") then return end

local S = minetest.get_translator("webpanel")
local gui = flow.widgets

social_profile.register_field("email", {
    title = S("Email address"),
    priority = -110,
    get_value = function(name, profile)
        if profile.show_email then
            return webpanel.get_user_email(name)
        end
    end,
    disallow_edit = true,
})

social_profile.register_field("show_email", {
    title = S("Show email address?"),
    priority = -110,
    hide = true,
    get_edit_row = function(_, _, value)
        return gui.Checkbox {
            name = "show_email",
            label = S("Show email address?") .. "\n" .. S("Edit your email address on the web panel."),
            selected = value
        }
    end,
    process_form = function(_, ctx)
        local result = ctx.form.show_email
        return result and true or nil
    end,
})