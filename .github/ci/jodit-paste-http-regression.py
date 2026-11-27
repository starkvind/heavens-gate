import json
import time
import urllib.request
import urllib.error
import http.cookiejar

base = "http://127.0.0.1:9876"
client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
keys = {"docs":"csrf_admin_docs","news":"csrf_admin_news","chapters":"csrf_admin_chapters",
        "items":"csrf_admin_items","systems":"csrf_admin_systems","forms":"csrf_admin_forms",
        "seasons":"csrf_admin_seasons","powers":"csrf_admin_poderes","system_details":"csrf_admin_system_details"}

def request(path, method="GET", token=None, body=None):
    headers = {}
    if token is not None: headers["X-CSRF-Token"] = token
    if body is not None: headers["Content-Type"] = "application/json"
    req = urllib.request.Request(base+path, data=None if body is None else json.dumps(body).encode(), headers=headers, method=method)
    try:
        with client.open(req,timeout=5) as response:
            return response.status,json.load(response)
    except urllib.error.HTTPError as response:
        return response.code,json.load(response)

def endpoint(module):
    return "/?module="+module+"&ajax_mode=sanitize_paste"

for attempt in range(30):
    try:
        status,_ = request(endpoint("docs"),"POST",body={"html":"<p>A</p>"})
        break
    except urllib.error.URLError:
        time.sleep(.2)
else:
    raise SystemExit("Fixture server unavailable")
assert status==403, status
status,login=request("/fixture-login")
assert status==200
tokens=login["data"]["tokens"]
for module,key in keys.items():
    url=endpoint(module)
    assert request(url,"GET")[0]==405
    assert request(url,"POST",body={"html":"<p>A</p>"})[0]==403
    assert request(url,"POST","wrong",{"html":"<p>A</p>"})[0]==403
    token=tokens[key]
    status,data=request(url,"POST",token,{"html":'<p onclick="void(0)">Texto</p><table><tr><td>A</td></tr></table>'})
    assert status==200,(module,status,data)
    clean=data["data"]["content"]
    assert "onclick" not in clean and "<table" in clean and "Texto" in clean
    assert request(url,"POST",token,{"html":[]})[0]==400
print("Authenticated paste HTTP regression: 9 modules, OK")
