-- webpanel/src/services.lua
-- Services provided by the socket
-- Copyright (C) 2024  1F616EMO
-- SPDX-License-Identifier: LGPL-3.0-or-later

local webpanel = webpanel

local logger = webpanel.internal.logger:sublogger("services")

webpanel.registered_services = {}

function webpanel.register_service(name, func)
    logger:action("Registered %s from %s", name, minetest.get_current_modname())
    webpanel.registered_services[name] = func
end
