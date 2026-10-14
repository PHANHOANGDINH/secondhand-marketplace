// Minimal CDP driver for the isolated E2E stack (Vite :5174 -> PHP :8001 -> test database, Reverb :8081).
import fs from 'node:fs/promises'
import process from 'node:process'
import { Buffer } from 'node:buffer'
import { execFileSync } from 'node:child_process'
export const BASE = 'http://localhost:5174'
export const ROOT = new URL('../../', import.meta.url)
export const OUT = new URL('docs/ui-redesign/e2e/', ROOT)
export const data = JSON.parse(await fs.readFile(new URL('./data.json', import.meta.url), 'utf8'))
const secret = JSON.parse(await fs.readFile(new URL('./secret.json', import.meta.url), 'utf8')).password
await fs.mkdir(OUT, { recursive: true })
export const sleep = (ms) => new Promise((r) => setTimeout(r, ms))
export const report = { journeys: {}, shots: {}, errors: [], notes: [] }
const PHP = 'C:/Users/axyz5/.config/herd/bin/php84/php.exe'
const backend = new URL('backend/', ROOT).pathname.replace(/^\/([A-Za-z]:)/, '$1')
const testEnv = { ...process.env, APP_ENV: 'testing', BROADCAST_CONNECTION: 'reverb', REVERB_APP_ID: 'e2e', REVERB_APP_KEY: 'e2ekey', REVERB_APP_SECRET: 'e2esecret', REVERB_HOST: '127.0.0.1', REVERB_PORT: '8081', REVERB_SCHEME: 'http' }
/** Runs PHP code inside the application on the TEST database (guarded) and returns its output. */
export function php(code) {
  const guard = "if (! app()->environment('testing') || DB::selectOne('SELECT DATABASE() name')->name !== 'secondhand_marketplace_test') { throw new RuntimeException('unsafe'); } "
  return execFileSync(PHP, ['artisan', 'tinker', '--execute=' + guard + code], { cwd: backend, env: testEnv, encoding: 'utf8' }).trim()
}
export function artisan(...args) {
  return execFileSync(PHP, ['artisan', ...args], { cwd: backend, env: testEnv, encoding: 'utf8' }).trim()
}

export class Page {
  static async open(port, label) {
    const tab = await (await fetch(`http://127.0.0.1:${port}/json/new?about:blank`, { method: 'PUT' })).json()
    const page = new Page()
    page.label = label
    page.ws = new WebSocket(tab.webSocketDebuggerUrl)
    await new Promise((r) => page.ws.addEventListener('open', r, { once: true }))
    page.id = 0
    page.pending = new Map()
    page.ws.addEventListener('message', (e) => {
      const m = JSON.parse(e.data)
      if (m.method === 'Runtime.exceptionThrown')
        report.errors.push(`[${label}] ` + (m.params.exceptionDetails?.exception?.description || m.params.exceptionDetails?.text || 'exception').slice(0, 300))
      const p = page.pending.get(m.id)
      if (!p) return
      page.pending.delete(m.id)
      if (m.error) p.reject(new Error(JSON.stringify(m.error)))
      else p.resolve(m.result)
    })
    for (const domain of ['Runtime', 'Page', 'Network', 'DOM']) await page.call(domain + '.enable')
    await page.size(1440)
    return page
  }
  call(method, params = {}) {
    return new Promise((resolve, reject) => {
      const n = ++this.id
      const timer = setTimeout(() => (this.pending.delete(n), reject(new Error(method + ' timeout'))), 30000)
      this.pending.set(n, { resolve: (v) => (clearTimeout(timer), resolve(v)), reject: (v) => (clearTimeout(timer), reject(v)) })
      this.ws.send(JSON.stringify({ id: n, method, params }))
    })
  }
  size(width) {
    this.width = width
    return this.call('Emulation.setDeviceMetricsOverride', { width, height: width < 500 ? 844 : 900, deviceScaleFactor: 1, mobile: width < 500 })
  }
  async evaluate(expression) {
    const r = await this.call('Runtime.evaluate', { expression: `(async()=>(${expression}))()`, awaitPromise: true, returnByValue: true })
    if (r.exceptionDetails) throw new Error(`[${this.label}] ` + JSON.stringify(r.exceptionDetails.exception?.description || r.exceptionDetails).slice(0, 500))
    return r.result.value
  }
  async wait(expression, label = expression, tries = 80) {
    for (let i = 0; i < tries; i++) {
      if (await this.evaluate(expression).catch(() => false)) return true
      await sleep(250)
    }
    throw new Error(`[${this.label}] timeout: ${label} @ ` + (await this.evaluate('location.pathname + location.search + " :: " + document.body.innerText.replace(/\\s+/g, " ").slice(0, 400)')))
  }
  async goto(path, settle = 1800) {
    await this.call('Page.navigate', { url: BASE + path })
    await sleep(settle)
  }
  text(selector = 'body') {
    return this.evaluate(`document.querySelector(${JSON.stringify(selector)})?.innerText || ''`)
  }
  exists(selector) {
    return this.evaluate(`!!document.querySelector(${JSON.stringify(selector)})`)
  }
  async fill(selector, value) {
    await this.wait(`!!document.querySelector(${JSON.stringify(selector)})`, 'field ' + selector)
    await this.evaluate(`(()=>{const e=document.querySelector(${JSON.stringify(selector)});const proto=e.tagName==='TEXTAREA'?HTMLTextAreaElement.prototype:e.tagName==='SELECT'?HTMLSelectElement.prototype:HTMLInputElement.prototype;Object.getOwnPropertyDescriptor(proto,'value').set.call(e,${JSON.stringify(String(value))});e.dispatchEvent(new Event('input',{bubbles:true}));e.dispatchEvent(new Event('change',{bubbles:true}));return true})()`)
  }
  async click(selector) {
    await this.wait(`(()=>{const e=document.querySelector(${JSON.stringify(selector)});return !!e && !e.disabled})()`, 'clickable ' + selector)
    await this.evaluate(`(document.querySelector(${JSON.stringify(selector)}).click(), true)`)
    await sleep(350)
  }
  /** Clicks the first enabled button/link whose text contains `text`, optionally inside `scope`. */
  async clickText(text, scope = 'body', tags = 'button, a') {
    const find = `[...(document.querySelector(${JSON.stringify(scope)})||document).querySelectorAll(${JSON.stringify(tags)})].find(e=>e.innerText.trim().includes(${JSON.stringify(text)}) && !e.disabled && e.offsetParent !== null)`
    await this.wait(`!!(${find})`, `button "${text}" in ${scope}`)
    await this.evaluate(`((${find}).click(), true)`)
    await sleep(350)
  }
  async submit(selector) {
    await this.evaluate(`(document.querySelector(${JSON.stringify(selector)}).requestSubmit(), true)`)
    await sleep(350)
  }
  api(path) {
    return this.evaluate(`fetch(${JSON.stringify(path)},{headers:{Accept:'application/json'},credentials:'include'}).then(r=>r.json())`)
  }
  async login(email, { guides = true } = {}) {
    // Sign the previous user out for certain: drop every cookie one by one, then load the form.
    for (let attempt = 0; attempt < 3; attempt++) {
      await this.call('Network.clearBrowserCookies')
      const { cookies } = await this.call('Network.getAllCookies')
      for (const cookie of cookies) await this.call('Network.deleteCookies', { name: cookie.name, domain: cookie.domain, path: cookie.path })
      await this.goto('/login', 2200)
      if (await this.exists('#auth-email')) break
    }
    await this.wait("!!document.querySelector('#auth-email')", 'login form')
    await this.evaluate('(localStorage.clear(), sessionStorage.clear(), true)')
    await this.fill('#auth-email', email)
    await this.fill('#auth-password', secret)
    await this.submit('.auth-form')
    await this.wait("location.pathname !== '/login'", 'signed in as ' + email)
    await sleep(800)
    if (guides) await this.skipGuides(email)
  }
  /** Marks every first-time guide as seen so it cannot sit on top of the journey under test. */
  async skipGuides(email) {
    const id = data.users[email]
    await this.evaluate(`(['welcome','quick-listing','auction-room','shop-created'].forEach(k=>localStorage.setItem('passlai:first-time:${id}:'+k,'1')), true)`)
    await this.evaluate("(document.querySelector('[data-guide-skip]')?.click(), true)")
    await sleep(300)
  }
  /** Screenshot at 1440 and 390 of the current state; records horizontal overflow. */
  async shot(name) {
    for (const width of [1440, 390]) {
      await this.size(width)
      await sleep(500)
      const m = await this.evaluate('({w: document.documentElement.scrollWidth, vw: innerWidth})')
      const s = await this.call('Page.captureScreenshot', { format: 'png' })
      await fs.writeFile(new URL(`${name}-${width}.png`, OUT), Buffer.from(s.data, 'base64'))
      // An emulated phone viewport grows to fit overflowing content, so the requested width is the real limit.
      report.shots[`${name}-${width}`] = m.w > Math.min(m.vw, width) + 1 ? `OVERFLOW ${m.w}>${width}` : 'ok'
    }
    await this.size(1440)
    await sleep(300)
  }
  close() {
    this.ws.close()
  }
}
export function check(condition, message) {
  if (!condition) throw new Error('assertion failed: ' + message)
}
export async function journey(name, run) {
  const started = Date.now()
  try {
    await run()
    report.journeys[name] = `PASS (${Math.round((Date.now() - started) / 1000)}s)`
  } catch (error) {
    report.journeys[name] = 'FAIL: ' + String(error.message || error).slice(0, 700)
  }
  console.log(name, '→', report.journeys[name])
}
