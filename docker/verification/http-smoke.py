import argparse, hashlib, http.client, http.cookiejar, json, os, pathlib, re, secrets, socket, struct, subprocess, urllib.error, urllib.parse, urllib.request, uuid, zlib

ROOT = pathlib.Path(__file__).resolve().parents[2]
BASE = 'http://localhost:8088'
COMPOSE = ['docker', 'compose', '--env-file', '.env.docker-verification']
LABEL = os.environ.get('VERIFICATION_LABEL', 'phase14')
if LABEL not in ('phase14', 'phase15'):
    raise ValueError('Verification label must be phase14 or phase15')
STATE = ROOT / f'.runtime/{LABEL}-http-state.json'
RESULT = ROOT / f'.runtime/{LABEL}-http-results.json'
checks = []

def check(name, condition):
    if not condition:
        raise AssertionError(name)
    checks.append(name)

def db(action, **kwargs):
    proc = subprocess.run(COMPOSE + ['exec', '-T', 'backend', 'php', '/tmp/phase14-docker-db.php'], cwd=ROOT, input=json.dumps(dict(action=action, **kwargs)), text=True, capture_output=True)
    if proc.returncode:
        raise RuntimeError(proc.stderr + proc.stdout)
    return json.loads(proc.stdout)

class LocalConnection(http.client.HTTPConnection):
    def connect(self):
        # Compose publishes IPv4 loopback. Keep the public localhost URI/Host,
        # while avoiding Windows' slow failed IPv6 attempt on every request.
        self.sock = socket.create_connection(('127.0.0.1', self.port), self.timeout, self.source_address)

class LocalHandler(urllib.request.HTTPHandler):
    def http_open(self, request):
        if request.host != 'localhost:8088':
            raise ValueError('Verifier is limited to the local deployment origin')
        return self.do_open(LocalConnection, request)

class Client:
    def __init__(self, kind):
        self.kind = kind
        self.jar = http.cookiejar.MozillaCookieJar(str(ROOT / f'.runtime/{LABEL}-{kind}-cookies.txt'))
        self.opener = urllib.request.build_opener(urllib.request.ProxyHandler({}), LocalHandler(), urllib.request.HTTPCookieProcessor(self.jar))
    def request(self, method, path, expected, payload=None, csrf=True, extra=None, raw=None, content_type=None):
        headers = {'Accept': 'application/json', 'Origin': BASE, 'Referer': BASE + '/'}
        if payload is not None:
            raw = json.dumps(payload).encode(); headers['Content-Type'] = 'application/json'
        if content_type: headers['Content-Type'] = content_type
        if csrf and method not in ('GET', 'HEAD', 'OPTIONS'):
            for cookie in self.jar:
                if cookie.name == 'XSRF-TOKEN': headers['X-XSRF-TOKEN'] = urllib.parse.unquote(cookie.value)
        headers.update(extra or {})
        req = urllib.request.Request(BASE + path, data=raw, headers=headers, method=method)
        try: response = self.opener.open(req, timeout=25)
        except urllib.error.HTTPError as error: response = error
        body = response.read()
        check(f'{self.kind} {method} {path}: expected {expected}, received {response.status}', response.status == expected)
        self.jar.save(ignore_discard=True, ignore_expires=True)
        if 'application/json' in response.headers.get('Content-Type', ''):
            return json.loads(body), response.headers
        return body, response.headers

def png():
    def chunk(kind, data): return struct.pack('!I', len(data)) + kind + data + struct.pack('!I', zlib.crc32(kind + data))
    return b'\x89PNG\r\n\x1a\n' + chunk(b'IHDR', struct.pack('!2I5B', 2, 2, 8, 2, 0, 0, 0)) + chunk(b'IDAT', zlib.compress((b'\x00' + b'\x00\x80\xff' * 2) * 2)) + chunk(b'IEND', b'')

def upload(client, pid, expected):
    boundary = 'Phase14' + secrets.token_hex(12)
    body = (f'--{boundary}\r\nContent-Disposition: form-data; name="images[]"; filename="docker.png"\r\nContent-Type: image/png\r\n\r\n'.encode() + png() + f'\r\n--{boundary}--\r\n'.encode())
    return client.request('POST', f'/api/v1/seller/products/{pid}/images', expected, raw=body, content_type=f'multipart/form-data; boundary={boundary}')[0]

def prepare():
    baseline = db('snapshot')
    check('Docker baseline contains no business fixtures', all(value['count'] == 0 for key, value in baseline.items() if key != 'migrations'))
    nonce = secrets.token_hex(16)
    state = dict(nonce=nonce, baseline=baseline)
    STATE.write_text(json.dumps(state, indent=2))
    guest = Client('guest')
    body, _ = guest.request('GET', '/api/v1/health', 200)
    check('health response', body['status'] == 'ok')
    for path in ['/', '/orders', '/payment/result?order=1']:
        html, headers = guest.request('GET', path, 200, extra={'Accept': 'text/html'})
        check(f'SPA fallback {path}', b'id="app"' in html)
    assets = re.findall(rb'(?:src|href)="(/assets/[^" ]+)"', html)
    check('production assets present', len(assets) >= 2)
    for asset in assets:
        data, _ = guest.request('GET', asset.decode(), 200)
        check('production asset is nonempty', len(data) > 0)
    guest.request('GET', '/api/v1/auth/me', 401)
    guest.request('GET', '/.env', 404)
    guest.request('GET', '/index.php', 404)
    _, headers = guest.request('OPTIONS', '/api/v1/auth/login', 204, extra={'Access-Control-Request-Method': 'POST', 'Access-Control-Request-Headers': 'content-type,x-xsrf-token'})
    check('exact deployed origin CORS', headers.get('Access-Control-Allow-Origin') == BASE and headers.get('Access-Control-Allow-Credentials') == 'true')
    _, headers = guest.request('OPTIONS', '/api/v1/auth/login', 204, extra={'Origin': 'https://evil.example', 'Access-Control-Request-Method': 'POST'})
    check('foreign origin CORS rejected', headers.get('Access-Control-Allow-Origin') != 'https://evil.example')
    clients = {kind: Client(kind) for kind in ['buyer', 'seller', 'admin']}
    for kind, client in clients.items():
        password = secrets.token_hex(18) + 'Ab1!'
        email = f'phase14-docker-{kind}-{nonce}@example.test'
        client.request('GET', '/sanctum/csrf-cookie', 204)
        cookies = {c.name: c for c in client.jar}
        check('session cookie HttpOnly', 'httponly' in cookies['marketplace_session']._rest)
        check('XSRF cookie browser readable', 'httponly' not in cookies['XSRF-TOKEN']._rest)
        check('local HTTP cookies use SameSite=Lax', cookies['marketplace_session']._rest.get('samesite', '').lower() == 'lax' and not cookies['marketplace_session'].secure)
        credentials = dict(name='Phase14 ' + kind, email=email, password=password, password_confirmation=password)
        client.request('POST', '/api/v1/auth/register', 419, credentials, csrf=False)
        user, _ = client.request('POST', '/api/v1/auth/register', 201, credentials)
        state[kind] = user['data']['id']
        client.request('GET', '/api/v1/auth/me', 200)
        client.request('POST', '/api/v1/auth/logout', 204, {})
        client.request('GET', '/api/v1/auth/me', 401)
        client.request('GET', '/sanctum/csrf-cookie', 204)
        client.request('POST', '/api/v1/auth/login', 419, dict(email=email, password=password), csrf=False)
        client.request('POST', '/api/v1/auth/login', 200, dict(email=email, password=password))
    STATE.write_text(json.dumps(state, indent=2))
    db('promote', nonce=nonce)
    buyer, seller, admin = [clients[k] for k in ['buyer', 'seller', 'admin']]
    buyer.request('GET', '/api/v1/profile', 200)
    buyer.request('PATCH', '/api/v1/profile', 200, dict(name='Deployment Buyer', phone='0901234567'))
    admin.request('GET', '/api/v1/admin/dashboard', 200)
    buyer.request('GET', '/api/v1/admin/dashboard', 403)
    category, _ = admin.request('POST', '/api/v1/admin/categories', 201, dict(name='Phase14 ' + nonce, status='ACTIVE', reason='Docker verification'))
    shop, _ = seller.request('POST', '/api/v1/shops', 201, dict(name='Phase14 ' + nonce, description='Isolated deployment fixture'))
    shop = shop['data']
    admin.request('PATCH', f'/api/v1/admin/shops/{shop["id"]}/status', 200, dict(expected_status=shop['status'], status='ACTIVE', reason='Docker verification'))
    product, _ = seller.request('POST', '/api/v1/seller/products', 201, dict(name='Phase14 ' + nonce, description='Docker upload and checkout fixture', category_id=category['data']['id'], price='250000.00', stock_quantity=3, condition='GOOD'))
    product = product['data']; pid = product['id']
    state['product_slug'] = product['slug']; state['shop_slug'] = shop['slug']
    image = upload(seller, pid, 201)['data'][0]
    upload(buyer, pid, 403)
    check('uploaded image uses deployed public origin', image['url'].startswith(BASE + '/storage/products/'))
    state['image_url'] = image['url']
    image_body, _ = guest.request('GET', image['url'].removeprefix(BASE), 200)
    state['image_sha256'] = hashlib.sha256(image_body).hexdigest()
    seller.request('POST', f'/api/v1/seller/products/{pid}/submit', 200, {})
    admin.request('PATCH', f'/api/v1/admin/products/{pid}/status', 200, dict(expected_status='PENDING', status='ACTIVE', reason='Docker verification'))
    public, _ = guest.request('GET', '/api/v1/products/' + product['slug'], 200, extra={'X-Forwarded-Host': 'evil.example', 'X-Forwarded-Proto': 'https', 'X-Forwarded-For': '192.0.2.14'})
    check('client forwarded headers do not change upload URLs', public['data']['images'][0]['url'] == image['url'])
    guest.request('GET', '/api/v1/products', 200)
    address, _ = buyer.request('POST', '/api/v1/addresses', 201, dict(recipient_name='Docker Buyer', phone='0901234567', address_line='Docker verification', ward='Ward 1', province='Ho Chi Minh', country_code='VN', is_default=True))
    buyer.request('POST', '/api/v1/cart/items', 201, dict(product_id=pid, quantity=1))
    buyer.request('GET', '/api/v1/cart', 200)
    payload = dict(address_id=address['data']['id'], payment_method='COD')
    key = str(uuid.uuid4()); extra = {'Idempotency-Key': key}
    buyer.request('POST', '/api/v1/checkout', 419, payload, csrf=False, extra=extra)
    order, _ = buyer.request('POST', '/api/v1/checkout', 201, payload, extra=extra)
    order = order['data']; oid = order['id']; part = order['shop_orders'][0]['id']
    check('checkout totals preserved', order['total_amount'] == '250000.00')
    replay, _ = buyer.request('POST', '/api/v1/checkout', 200, payload, extra=extra)
    check('checkout idempotency through Nginx', replay['data']['id'] == oid)
    buyer.request('GET', '/api/v1/orders', 200)
    buyer.request('GET', f'/api/v1/orders/{oid}/payments', 200)
    seller.request('GET', f'/api/v1/orders/{oid}', 403)
    buyer.request('GET', f'/api/v1/seller/orders/{part}', 403)
    seller.request('GET', f'/api/v1/seller/orders/{part}', 200)
    buyer.request('POST', f'/api/v1/orders/{oid}/payments/zalopay', 409, {}, extra={'Idempotency-Key': str(uuid.uuid4())})
    buyer.request('POST', f'/api/v1/orders/{oid}/payments/zalopay/query', 409, {})
    callback, _ = guest.request('POST', '/api/v1/payments/zalopay/callback', 200, dict(data='{}', mac='0' * 64, type=1), csrf=False)
    check('unauthenticated callback route rejects invalid signature', callback['return_code'] == -1)
    previous = 'PENDING'
    for status in ['CONFIRMED', 'PROCESSING', 'SHIPPING', 'COMPLETED']:
        payload = dict(expected_status=previous, status=status)
        if status == 'COMPLETED': payload['cod_collected'] = True
        seller.request('PATCH', f'/api/v1/seller/orders/{part}/status', 200, payload)
        previous = status
    completed, _ = buyer.request('GET', f'/api/v1/orders/{oid}', 200)
    check('COD payment paid after completed delivery', completed['data']['payments'][0]['status'] == 'PAID')
    if LABEL == 'phase15':
        buyer.request('GET', '/api/v1/addresses', 200)
        seller.request('GET', '/api/v1/seller/shop', 200)
        seller.request('GET', '/api/v1/seller/products', 200)
        seller.request('GET', '/api/v1/seller/orders', 200)
        seller.request('GET', '/api/v1/seller/dashboard', 200)
        seller.request('GET', '/api/v1/seller/dashboard/revenue', 200)
        item = completed['data']['shop_orders'][0]['items'][0]['id']
        review, _ = buyer.request('POST', f'/api/v1/order-items/{item}/review', 201, dict(rating=4, comment='Isolated deployment review'))
        rid = review['data']['id']
        buyer.request('PATCH', f'/api/v1/reviews/{rid}', 200, dict(rating=5, comment='Updated deployment review'))
        guest.request('GET', f'/api/v1/products/{product["slug"]}/reviews', 200)
        buyer.request('GET', f'/api/v1/products/{product["slug"]}/review-eligibility', 200)
        admin.request('PATCH', f'/api/v1/admin/reviews/{rid}/status', 200, dict(expected_status='PUBLISHED', status='HIDDEN', reason='Deployment verification'))
        public, _ = guest.request('GET', f'/api/v1/products/{product["slug"]}/reviews', 200)
        check('hidden review absent from public response', len(public['data']) == 0)
        admin.request('PATCH', f'/api/v1/admin/reviews/{rid}/status', 200, dict(expected_status='HIDDEN', status='PUBLISHED', reason='Deployment verification'))
        for path in ['dashboard', 'users', 'shops', 'products', 'categories', 'reviews', 'orders']:
            admin.request('GET', '/api/v1/admin/' + path, 200)
        admin.request('GET', f'/api/v1/admin/orders/{oid}', 200)
    state.update(order=oid, part=part, product=pid, persisted=db('snapshot'), prepare_checks=checks)
    STATE.write_text(json.dumps(state, indent=2))
    RESULT.write_text(json.dumps(dict(stage='prepared', checks=len(checks), results=checks), indent=2))
    print(json.dumps(dict(stage='prepared', checks=len(checks), nonce=nonce)))

def persistence(cleanup=False):
    state = json.loads(STATE.read_text())
    check('Docker database rows survive restart', db('snapshot') == state['persisted'])
    guest = Client('persistence')
    image, _ = guest.request('GET', state['image_url'].removeprefix(BASE), 200)
    check('uploaded image bytes survive restart', hashlib.sha256(image).hexdigest() == state['image_sha256'])
    buyer = Client('buyer'); buyer.jar.load(ignore_discard=True, ignore_expires=True)
    user, _ = buyer.request('GET', '/api/v1/auth/me', 200)
    check('authenticated cookie session survives restart', user['data']['id'] == state['buyer'])
    order, _ = buyer.request('GET', f'/api/v1/orders/{state["order"]}', 200)
    check('paid order survives restart', order['data']['payments'][0]['status'] == 'PAID' and order['data']['shop_orders'][0]['status'] == 'COMPLETED')
    if cleanup:
        db('cleanup', nonce=state['nonce'])
        check('Docker fixture data fully cleaned', db('snapshot') == state['baseline'])
        guest.request('GET', state['image_url'].removeprefix(BASE), 404)
    previous = json.loads(RESULT.read_text())['results']
    RESULT.write_text(json.dumps(dict(stage='cleaned' if cleanup else 'restart-verified', checks=len(previous)+len(checks), results=previous+checks), indent=2))
    print(json.dumps(dict(stage='cleaned' if cleanup else 'restart-verified', checks=len(previous)+len(checks))))

def checkpoint():
    state = json.loads(STATE.read_text())
    current = db('snapshot')
    carts = db('browser_checkpoint', nonce=state['nonce'])
    check('browser cart initialization leaves the existing buyer cart unchanged', carts['buyer_cart'] == state['persisted']['carts'])
    check('browser navigation leaves every other business table unchanged', all(value == state['persisted'][table] for table, value in current.items() if table != 'carts'))
    state['persisted'] = current
    STATE.write_text(json.dumps(state, indent=2))
    previous = json.loads(RESULT.read_text())['results']
    RESULT.write_text(json.dumps(dict(stage='checkpoint', checks=len(previous)+len(checks), results=previous+checks), indent=2))
    print(json.dumps(dict(stage='checkpoint', checks=len(previous)+len(checks))))

if __name__ == '__main__':
    parser = argparse.ArgumentParser(description='Smoke verification for the isolated marketplace-phase14 Docker database and localhost:8088 origin.')
    parser.add_argument('stage', choices=['prepare', 'checkpoint', 'persistence', 'cleanup', 'abort'])
    stage = parser.parse_args().stage
    if stage == 'prepare':
        prepare()
    elif stage == 'checkpoint':
        checkpoint()
    elif stage == 'abort':
        state = json.loads(STATE.read_text())
        print(json.dumps(db('cleanup', nonce=state['nonce'])))
        check('aborted Docker fixtures fully cleaned', db('snapshot') == state['baseline'])
    else:
        persistence(cleanup=stage == 'cleanup')





