local http = minetest.request_http_api()

if not http then
	error("Please include webpanel into secure.http_mods!")
end

local PORT = minetest.settings:get("webpanel_port")
if not PORT then
	error("Please set port at webpanel_port!")
end
local URLBASE = "http://localhost:" .. PORT .. "/"
local ACTIONQUEUE = URLBASE .. "actionqueue"
local RESPONCE = URLBASE .. "responce"
local CANCEL = URLBASE .. "cancel"

local AUTHHANDLE = minetest.get_auth_handler()
local SINGLEPLAYER = minetest.is_singleplayer()

local actions = {}
function actions.status(request)
	return {status = minetest.get_server_status(request.uname or "PANEL")}
end
function actions.auth(request)
	local uname = request.uname
	if SINGLEPLAYER then
		if uname == "singleplayer" then
			return {auth = "true"}
		else
			return {auth = "false"}
		end
	else
		if uname == "singleplayer" then
			return {auth = "false"}
		end
	end
	local passwd = request.passwd
	local entry = AUTHHANDLE.get_auth(uname)
	if not entry then
		return {auth = "false"}
	end
	local passwd_entry = entry.password
	return {auth = minetest.check_password_entry(uname, passwd_entry, passwd) and "true" or "false"}
end
function actions.shout(request)
	local uname = request.uname
	local msg = request.msg
	minetest.chat_send_all(minetest.format_chat_message(uname,msg))
	return {}
end

local function process_request(request)
	local responce
	if actions[request.type] then
		responce = actions[request.type](request)
	else
		responce = {error = "Not Found"}
	end
	responce.id = request.id
	print(dump(responce))
	http.fetch_async({
		url = RESPONCE,
		method = "POST",
		data = responce,
		user_agent = "Minetest-web-panel",
		multipart = true
	})
end

function webpanel_loop()
	http.fetch({
		url = ACTIONQUEUE,
		method = "GET",
		user_agent = "Minetest-web-panel"
	},function(responce)
		if responce.succeeded then
			local now = os.time()
			local data = minetest.parse_json(responce.data)
			for _,y in pairs(data) do
				if now - y.time > 10 then
					minetest.log("Request timed out " .. dump(y))
					http.fetch_async({
						url = CANCEL,
						method = "POST",
						data = {id=y.id},
						user_agent = "Minetest-web-panel",
					})
				else
					minetest.log("Received request " .. dump(y))
					process_request(y)
				end
			end
		end
		minetest.after(1,webpanel_loop)
	end)
end

webpanel_loop()
