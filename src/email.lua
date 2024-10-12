-- webpanel/src/email.lua
-- User emails
-- Copyright (C) 2024  1F616EMO
-- SPDX-License-Identifier: LGPL-3.0-or-later

local webpanel = webpanel

local storage = minetest.get_mod_storage()

function webpanel.get_user_email(name) -- "" if not set
    return storage:get_string("webpanel_emails:" .. name)
end

function webpanel.set_user_email(name, email)
    return storage:set_string("webpanel_emails:" .. name, email)
end
