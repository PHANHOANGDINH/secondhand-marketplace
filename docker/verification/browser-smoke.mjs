// Read-only deployed-page checks. Start an isolated Chromium/Edge CDP instance
// on localhost:9223, then run after VERIFICATION_LABEL=phase15 HTTP prepare.
import { readFile, writeFile } from 'node:fs/promises'
const root = new URL('../../', import.meta.url)
const runtime = new URL('.runtime/', root)
const base = 'http://localhost:8088'
const state = JSON.parse(await readFile(new URL('phase15-http-state.json', runtime), 'utf8'))
const targets = await (await fetch('http://127.0.0.1:9223/json/list')).json()
const target = targets.find((item) => item.type === 'page')
if (!target) throw new Error('No isolated browser page available')
const socket = new WebSocket(target.webSocketDebuggerUrl)
await new Promise((resolve, reject) => { socket.addEventListener('open', resolve, { once: true }); socket.addEventListener('error', reject, { once: true }) })
let nextId = 0
const pending = new Map(), exceptions = [], checks = []
socket.addEventListener('message', (event) => {
  const message = JSON.parse(event.data)
  if (message.method === 'Runtime.exceptionThrown') exceptions.push(message.params.exceptionDetails.text)
  if (!message.id) return
  const request = pending.get(message.id)
  if (!request) return
  pending.delete(message.id); clearTimeout(request.timer)
  if (message.error) request.reject(new Error(message.error.message))
  else request.resolve(message.result)
})
function call(method, params = {}) {
  const id = ++nextId
  return new Promise((resolve, reject) => {
    const timer = setTimeout(() => { pending.delete(id); reject(new Error(`CDP timeout: ${method}`)) }, 20000)
    pending.set(id, { resolve, reject, timer }); socket.send(JSON.stringify({ id, method, params }))
  })
}
async function evaluate(expression) { return (await call('Runtime.evaluate', { expression, returnByValue: true })).result.value }
async function cookies(kind) {
  await call('Network.clearBrowserCookies')
  if (kind === 'guest') return
  const file = await readFile(new URL(`phase15-${kind}-cookies.txt`, runtime), 'utf8')
  const entries = file.split(/\r?\n/).filter((line) => line && !line.startsWith('#')).map((line) => {
    const parts = line.split('\t')
    return { name: parts[5], value: parts[6], url: base, path: '/', httpOnly: parts[5] === 'marketplace_session', secure: false, sameSite: 'Lax' }
  })
  if (!entries.some((cookie) => cookie.name === 'marketplace_session')) throw new Error('Prepared test session missing')
  await call('Network.setCookies', { cookies: entries })
}
async function page(kind, path, expectedTitle, expectedPath = path.split('?')[0], allowError = false) {
  const previousTime = await evaluate('performance.timeOrigin')
  await call('Page.navigate', { url: base + path })
  const deadline = Date.now() + 20000
  let view
  while (Date.now() < deadline) {
    view = await evaluate(`({time:performance.timeOrigin, ready:document.readyState, path:location.pathname, query:location.search, title:document.querySelector('main h1')?.textContent?.trim(), alerts:[...document.querySelectorAll('[role="alert"]')].map(n=>n.textContent.trim()), busy:[...document.querySelectorAll('[role="status"]')].some(n=>/Đang (xử lý|kiểm tra|tải)/.test(n.textContent))})`)
    if (view?.time !== previousTime && view?.ready === 'complete' && view?.path === expectedPath && view?.title && !view.busy) break
    await new Promise((resolve) => setTimeout(resolve, 150))
  }
  if (!view?.title || view.path !== expectedPath || view.busy || (expectedTitle && !view.title.includes(expectedTitle))) throw new Error(`${kind} ${path}: page did not become ready`)
  if (!allowError && view.alerts.length) throw new Error(`${kind} ${path}: ${view.alerts.join('; ')}`)
  if (exceptions.length) throw new Error(`Browser JavaScript exception: ${exceptions.join('; ')}`)
  checks.push({ role: kind, path, title: view.title, result: 'PASS' })
  return view
}
try {
  await call('Page.enable'); await call('Network.enable'); await call('Runtime.enable')
  await cookies('guest')
  for (const path of ['/', '/products', '/shops', `/products/${state.product_slug}`, `/shops/${state.shop_slug}`]) await page('guest', path)
  await page('guest', '/no-such-page', 'Không tìm thấy trang')
  const login = await page('guest', `/payment/result?order=${state.order}`, 'Đăng nhập', '/login')
  if (new URLSearchParams(login.query).get('redirect') !== `/payment/result?order=${state.order}`) throw new Error('Guest payment destination lost')
  await cookies('buyer')
  for (const path of ['/account', '/account/profile', '/account/addresses', '/cart', '/checkout', '/orders', `/orders/${state.order}`, `/products/${state.product_slug}`, `/payment/result?order=${state.order}`]) await page('buyer', path)
  const invalid = await page('buyer', '/payment/result', 'Kết quả thanh toán', '/payment/result', true)
  if (!invalid.alerts.some((message) => message.includes('Mã đơn hàng bị thiếu hoặc không hợp lệ'))) throw new Error('Missing payment order recovery message')
  await page('buyer', '/admin/dashboard', 'Secondhand Marketplace', '/')
  await cookies('seller')
  for (const path of ['/seller/shop', '/seller/products', '/seller/products/create', `/seller/products/${state.product}/edit`, '/seller/orders', `/seller/orders/${state.part}`, '/seller/dashboard']) await page('seller', path)
  await cookies('admin')
  for (const path of ['/admin/dashboard', '/admin/users', '/admin/categories', '/admin/shops', '/admin/products', '/admin/reviews', `/admin/orders?order=${state.order}`]) await page('admin', path)
  await writeFile(new URL('phase15-browser-results.json', runtime), JSON.stringify({ checks: checks.length, javascriptExceptions: exceptions.length, results: checks }, null, 2))
  console.log(JSON.stringify({ checks: checks.length, javascriptExceptions: exceptions.length, result: 'PASS' }))
} finally {
  // Close only the isolated browser started for this verification, including on failure.
  await call('Browser.close').catch(() => {})
  socket.close()
}
