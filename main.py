import config, tools
import asyncio, uuid, json, logging, time
from aiohttp import web
from aiohttp_session import get_session, setup
from aiohttp_session.cookie_storage import EncryptedCookieStorage

logging.basicConfig(level=config.logging_level,format=config.logging_format)
log = logging.getLogger(__name__)

app = web.Application()
routes = web.RouteTableDef()

queue = {}
responces = {}

def add_to_queue(request):
    queue_id = str(uuid.uuid4())
    request["id"] = queue_id
    request["time"] = int(time.time())
    queue[queue_id] = request
    return queue_id

async def get_responce(id):
    while True:
        await asyncio.sleep(0.2)
        if id in responces:
            resp = responces[id]
            del(responces[id])
            return resp

async def wait_for_responce(request):
    id = add_to_queue(request)
    try:
        return await asyncio.wait_for(get_responce(id), 10)
    except asyncio.TimeoutError:
        return False


@routes.get('/api')
async def api_get(request):
    if "method" not in request.query:
        raise web.HTTPBadRequest()
    action = request.query["method"]
    match action:
        case "token":

        case "status":
            data = await wait_for_responce({
                    "type": "status"
                })
            if data:
                return web.Response(body=data["status"])
            else:
                raise web.HTTPGatewayTimeout()
        case "auth":
            if not("uname" in request.query and "passwd" in request.query):
                raise web.HTTPBadRequest()
            data = await wait_for_responce({
                    "type": "auth",
                    "uname": request.query["uname"],
                    "passwd": request.query["passwd"]
                })
            if data:
                return web.Response(body=data["auth"])
            else:
                raise web.HTTPGatewayTimeout()
        case "shout":
            if not("uname" in request.query and "msg" in request.query):
                raise web.HTTPBadRequest()
            data = await wait_for_responce({
                    "type": "shout",
                    "uname": request.query["uname"],
                    "msg": request.query["msg"]
                })
            if data:
                return web.Response(body="OK")
            else:
                raise web.HTTPGatewayTimeout()
        case _:
            raise web.HTTPBadRequest()

@routes.get('/actionqueue')
async def actionqueue(request):
    if not(request.remote == "127.0.0.1" or request.remote == "::1"):
        raise web.HTTPForbidden()
    return web.Response(body=json.dumps(queue))

@routes.post('/responce')
async def responce_from_mt(request):
    if not(request.remote == "127.0.0.1" or request.remote == "::1"):
        raise web.HTTPForbidden()
    data = await request.post()
    del(queue[data["id"]])
    responces[data["id"]] = data
    return web.Response()

@routes.post('/cancel')
async def clearqueue(request):
    if not(request.remote == "127.0.0.1" or request.remote == "::1"):
        raise web.HTTPForbidden()
    data = await request.post()
    del(queue[data["id"]])
    return web.Response()

app.add_routes(routes)
web.run_app(app,port = config.port)

