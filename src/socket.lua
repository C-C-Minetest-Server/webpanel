-- webpanel/src/socket.lua
-- SSocket to connect with the frontend
-- Copyright (C) 2024  1F616EMO
-- SPDX-License-Identifier: LGPL-3.0-or-later

local minetest, webpanel = minetest, webpanel
local string, os = string, os

local logger = webpanel.internal.logger:sublogger("socket")
local socket = webpanel.internal.socket

local settings = minetest.settings
local bind_addr = settings:get("webpanel.socket_bind_addr") or "*"
local bind_port = tonumber(settings:get("webpanel.socket_bind_port")) or 30300
local socket_timeout = tonumber(settings:get("webpanel.socket_timeout")) or 2
local sock_secret = logger:assert(settings:get("webpanel.socket_secret"),
    "Please set a secret at `webpanel.socket_secret`!")

local function assert_err_msg(desc, func, ...)
    local ret, err = func(...)
    return logger:assert(ret, "Failed to %s: %s", desc, err)
end

local server = assert_err_msg("create socket", socket.bind, bind_addr, bind_port)
do
    local ip, port = server:getsockname()
    logger:assert(ip, "Failed to retrieve socket information")
    logger:action("Listening on %s:%d", ip, port)
end
server:settimeout(0)

local clients = {}

local function accept()
    local client, err = server:accept()
    if not client then
        if err ~= "timeout" then
            logger:warning("Failed to accept remote connection: %s", err)
        end
        return
    end

    client:settimeout(0)
    local id = string.match(tostring(client), "0x%x+") or tostring(client)
    logger:verbose("Client %s: Connected", id)
    clients[id] = {
        client = client,
        buffer = "",
        start = os.time(),
    }
end

local function handle_command(id, cmd)
    if cmd.secret ~= sock_secret then
        logger:warning("Client %s: Authentication failed", id)
        return {
            ok = false,
            err = "Authentication failed",
        }
    end
    local service_name = cmd.service_name
    local service_func = webpanel.registered_services[service_name]
    if not service_func then
        logger:warning("Client %s: Attempted to execute invalid service %s", id, service_name)
        return {
            ok = false,
            err = "Invalid service",
        }
    end
    return service_func(id, cmd)
end

local function receive(id, data)
    local client = data.client
    local recv, err, partial = client:receive(1024)
    if not recv and err ~= "timeout" then
        logger:warning("Client %s: Failed to receive: %s", id, err)
        client:close()
        clients[id] = nil
        return
    end

    local new_recv = recv or partial
    if new_recv == "" then
        local cmd = minetest.parse_json(data.buffer)
        if not cmd then
            logger:warning("Client %s: Invalid data: %s", id, data.buffer)
            client:send(minetest.write_json({
                ok = false,
                err = "Invalid data",
            }))
            client:close()
            clients[id] = nil
            return
        end
        local reply = handle_command(id, cmd)
        local reply_string = minetest.write_json(reply)
        client:send(reply_string)
        client:close()
        clients[id] = nil
        return
    else
        if os.time() - data.start > socket_timeout then
            client:send(minetest.write_json({
                ok = false,
                err = "Timed out",
            }))
            client:close()
            clients[id] = nil
            return
        elseif #data.buffer > 20000 then
            client:send(minetest.write_json({
                ok = false,
                err = "Body too long",
            }))
            client:close()
            clients[id] = nil
            return
        end
        data.buffer = data.buffer .. new_recv
    end
end

minetest.register_globalstep(function()
    accept()
    for id, data in pairs(clients) do
        receive(id, data)
    end
end)

minetest.register_on_shutdown(function()
    for _, data in pairs(clients) do
        local client = data.client
        client:close()
    end
    server:close()
end)
