-- webpanel/src/mediawiki.lua
-- MediaWiki Integration
-- Copyright (C) 2024  1F616EMO
-- SPDX-License-Identifier: LGPL-3.0-or-later

local webpanel = webpanel

local storage = minetest.get_mod_storage()

function webpanel.get_user_mediawiki_account(name) -- "" if not set
    return storage:get_string("webpanel_mediawiki_accounts:" .. name)
end

function webpanel.set_user_mediawiki_account(name, mediawiki_account)
    return storage:set_string("webpanel_mediawiki_accounts:" .. name, mediawiki_account)
end
