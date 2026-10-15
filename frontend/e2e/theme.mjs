// Passdi light + dark: the same six screens in both themes at 1440 and 390, plus automatic checks that
// no text sinks into its background and no stray light box is left on a dark page.
import fs from 'node:fs/promises'
import process from 'node:process'
import { Buffer } from 'node:buffer'
import { Page, report, journey, check, php, sleep } from './lib.mjs'
const U = (key) => `c1-${key}@example.test`
const one = await Page.open(9351, 'browser-1')

// Runs in the page: text whose contrast with the colour behind it is too low, and large light boxes in dark.
const AUDIT = `(() => {
  const rgb = (value) => { const m = value.match(/rgba?\\(([^)]+)\\)/); if (!m) return null; const p = m[1].split(/[ ,/]+/).filter(Boolean).map(Number); return { r: p[0], g: p[1], b: p[2], a: p.length > 3 ? p[3] : 1 } }
  const lum = ({ r, g, b }) => { const c = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4 }; return 0.2126 * c(r) + 0.7152 * c(g) + 0.0722 * c(b) }
  const ratio = (a, b) => { const [x, y] = [lum(a), lum(b)].sort((p, q) => q - p); return (x + 0.05) / (y + 0.05) }
  const skip = (el) => el.closest('img, video, canvas, picture, svg, .product-card-media, .product-gallery, .video-review-card, .video-theater, .product-photo, [aria-hidden="true"], .visually-hidden, [class*="vue-devtools"]')
  // The colour actually painted behind an element: the first opaque background up the tree, blended.
  const backdrop = (el) => {
    const layers = []
    for (let node = el; node; node = node.parentElement) {
      const style = getComputedStyle(node)
      if (style.backgroundImage !== 'none' && !node.matches('html, body')) return null
      const c = rgb(style.backgroundColor)
      if (c && c.a > 0) { layers.push(c); if (c.a >= 0.99) break }
    }
    let out = { r: 255, g: 255, b: 255 }
    for (const c of layers.reverse()) out = { r: c.r * c.a + out.r * (1 - c.a), g: c.g * c.a + out.g * (1 - c.a), b: c.b * c.a + out.b * (1 - c.a) }
    return out
  }
  const visible = (el) => { const r = el.getBoundingClientRect(); const s = getComputedStyle(el); return r.width > 0 && r.height > 0 && s.visibility !== 'hidden' && Number(s.opacity) > 0.2 }
  const low = []
  for (const el of document.querySelectorAll('body *')) {
    if (skip(el) || !visible(el)) continue
    const own = [...el.childNodes].some((n) => n.nodeType === 3 && n.textContent.trim())
    if (!own || el.closest('[disabled], .disabled')) continue
    const s = getComputedStyle(el)
    const fg = rgb(s.color), bg = backdrop(el)
    if (!fg || !bg) continue
    const large = parseFloat(s.fontSize) >= 24 || (parseFloat(s.fontSize) >= 18.66 && Number(s.fontWeight) >= 700)
    const value = ratio(fg, bg)
    if (value < (large ? 3 : 4.5)) low.push(Math.round(value * 100) / 100 + ' ' + (el.className || el.tagName).toString().slice(0, 40) + ' "' + el.textContent.trim().slice(0, 30) + '"')
  }
  const light = []
  if (document.documentElement.dataset.theme === 'dark') {
    for (const el of document.querySelectorAll('body *')) {
      if (skip(el) || !visible(el) || el.closest('.theme-auction')) continue
      const c = rgb(getComputedStyle(el).backgroundColor)
      const r = el.getBoundingClientRect()
      if (c && c.a > 0.5 && lum(c) > 0.6 && r.width * r.height > 900) light.push((el.className || el.tagName).toString().slice(0, 50) + ' ' + Math.round(r.width) + 'x' + Math.round(r.height))
    }
  }
  return { theme: document.documentElement.dataset.theme, bs: document.documentElement.dataset.bsTheme, low: low.slice(0, 12), lowCount: low.length, light: light.slice(0, 12), lightCount: light.length }
})()`

const audits = {}
const heights = {}
// Leave the app first: a request still in flight could set the old session cookie again after sign-out.
async function switchUser(key) {
  await one.call('Page.navigate', { url: 'about:blank' })
  await sleep(600)
  await one.login(U(key))
}
async function setTheme(theme) {
  await one.evaluate(`(localStorage.setItem('passdi:theme', ${JSON.stringify(theme)}), true)`)
}
async function capture(name, theme, ready) {
  await setTheme(theme)
  await one.call('Page.reload')
  await sleep(2200)
  if (ready) await one.wait(ready, name + ' ready')
  for (const width of [1440, 390]) {
    await one.size(width)
    await sleep(500)
    audits[`${name}-${theme}-${width}`] = await one.evaluate(AUDIT)
  }
  await one.size(1440)
  await one.shot(`T-${name}-${theme}`)
}
async function both(name, ready) {
  for (const theme of ['light', 'dark']) await capture(name, theme, ready)
}

const item = JSON.parse(
  php(
    "$seller = DB::table('users')->where('email', 'c1-seller-1@example.test')->value('id'); $shop = DB::table('shops')->where('owner_id', $seller)->value('id'); $p = DB::table('products')->where('shop_id', $shop)->where('status', 'ACTIVE')->where('stock_quantity', '>', 0)->whereNotExists(fn ($q) => $q->from('auctions')->whereColumn('auctions.product_id', 'products.id'))->orderBy('id')->first(['id', 'slug', 'name']); echo json_encode($p);",
  ),
)
check(item?.slug, 'a product to buy')

await journey('T1 toggle and system default', async () => {
  await one.call('Network.clearBrowserCookies')
  await one.goto('/', 2500)
  await one.evaluate('(localStorage.clear(), true)')
  // No stored choice: the theme follows the operating system, from the first paint.
  for (const scheme of ['dark', 'light']) {
    await one.call('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-color-scheme', value: scheme }] })
    await one.call('Page.reload')
    await sleep(1800)
    check((await one.evaluate('document.documentElement.dataset.theme')) === scheme, 'system ' + scheme)
    check((await one.evaluate("document.documentElement.getAttribute('data-bs-theme')")) === scheme, 'bootstrap follows ' + scheme)
  }
  const label = await one.evaluate("document.querySelector('[data-theme-toggle]').getAttribute('aria-label')")
  check(label === 'Chuyển sang giao diện tối', 'toggle label')
  check((await one.evaluate("document.querySelector('[data-theme-toggle]').getBoundingClientRect().width")) === 44, 'toggle is 44px')
  await one.click('[data-theme-toggle]')
  await sleep(300)
  check((await one.evaluate('document.documentElement.dataset.theme')) === 'dark', 'toggle switches to dark')
  check((await one.evaluate("localStorage.getItem('passdi:theme')")) === 'dark', 'choice stored')
  await one.call('Page.reload')
  await sleep(1800)
  check((await one.evaluate('document.documentElement.dataset.theme')) === 'dark', 'choice survives a reload')
  await one.call('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-color-scheme', value: 'light' }] })
  await one.evaluate('(localStorage.clear(), true)')
  await one.goto('/login', 2200)
  await both('login', "!!document.querySelector('#auth-email')")
})

await journey('T2 home, product detail, checkout (buyer)', async () => {
  await switchUser('buyer-a')
  await one.goto('/', 2500)
  await both('home', "document.querySelectorAll('[data-product]').length > 0")
  // The account menu with its three theme choices, in dark.
  await setTheme('dark')
  await one.call('Page.reload')
  await sleep(2200)
  await one.evaluate("(document.querySelector('.marketplace-account summary').click(), true)")
  await sleep(400)
  check((await one.evaluate("[...document.querySelectorAll('[data-theme-choice]')].map(e=>e.innerText.trim()).join('|')")) === 'Theo hệ thống|Sáng|Tối', 'three choices in the account menu')
  await one.shot('T-account-menu-dark')
  await one.click('[data-theme-choice="light"]')
  await sleep(300)
  check((await one.evaluate('document.documentElement.dataset.theme')) === 'light', 'menu choice applies')

  await one.goto(`/products/${item.slug}`, 2500)
  await both('detail', "!!document.querySelector('.product-detail-side h1')")
  await one.clickText('Thêm vào giỏ', '.product-buy-bar')
  await one.wait("document.body.innerText.includes('Đã thêm sản phẩm vào giỏ hàng')", 'added to cart')
  await one.goto('/cart', 2200)
  await one.clickText('Đặt hàng', 'main', 'a')
  await one.wait("location.pathname === '/checkout' && !!document.querySelector('#voucher-platform')", 'checkout')
  await both('checkout', "!!document.querySelector('#voucher-platform')")
  heights.checkout = await one.evaluate('document.documentElement.scrollHeight')
  await one.goto('/cart', 2200)
  await both('cart', "!!document.querySelector('[data-cart-total]')")
  // Phone: the account menu with modes and theme, and the bottom tab bar.
  await setTheme('light')
  await one.size(390)
  await one.call('Page.reload')
  await sleep(2200)
  check(await one.evaluate("getComputedStyle(document.querySelector('.mobile-tabbar')).display === 'grid'"), 'tab bar on phones')
  await one.evaluate("(document.querySelector('.marketplace-account').setAttribute('open', ''), true)")
  await sleep(300)
  const s = await one.call('Page.captureScreenshot', { format: 'png' })
  await fs.writeFile(new URL('../../docs/ui-redesign/e2e/R-account-menu-390.png', import.meta.url), Buffer.from(s.data, 'base64'))
  await one.size(1440)
})

await journey('T3 my ads and seller dashboard (seller)', async () => {
  await switchUser('seller-1')
  await one.goto('/my-ads', 2500)
  await both('my-ads', "document.querySelectorAll('.my-ads-count').length > 0")
  await one.goto('/seller/dashboard', 2500)
  await both('seller', "!!document.querySelector('canvas')")
  await one.goto('/seller/inventory', 2500)
  await one.wait("!!document.querySelector('[aria-label^=\"Cập nhật tồn kho:\"]')", 'inventory rows')
  await one.click('[aria-label^="Cập nhật tồn kho:"]')
  await one.wait("!!document.querySelector('#inventory-edit #inventory-quantity')", 'stock sheet')
  await one.shot('R-inventory-sheet')
  await one.goto('/seller/auctions', 2500)
  await capture('seller-auctions', 'light', "!!document.querySelector('.auction-seller-list')")
  if (await one.exists('[data-sell-now]')) {
    await one.click('[data-sell-now]')
    await one.wait("!!document.querySelector('[data-sell-now-panel]')", 'sell-now sheet')
    await one.shot('R-sell-now-sheet')
  }
  await one.goto('/?quick-listing=1', 2500)
  await one.wait("!!document.querySelector('#quick-listing-dialog #quick-product-form')", 'quick listing form')
  await one.shot('R-quick-listing')
  // The pages take their colours from the tokens (the last capture above was light).
  const bar = await one.evaluate("getComputedStyle(document.documentElement).getPropertyValue('--primary').trim()")
  check(bar === '#c2410c', 'light primary token is ' + bar)
  // What sticks out at phone width, if anything.
  await one.size(390)
  await sleep(600)
  report.notes.push('seller-390 wide: ' + JSON.stringify(await one.evaluate("[...document.querySelectorAll('body *')].filter(e=>e.getBoundingClientRect().right>window.innerWidth+1).slice(0,8).map(e=>(e.className||e.tagName).toString().slice(0,60)+' '+Math.round(e.getBoundingClientRect().right))")))
  await one.size(1440)
})

await journey('T4 admin, auction stays dark', async () => {
  await switchUser('admin')
  await one.goto('/admin/dashboard', 2500)
  await both('admin', "!!document.querySelector('.admin-shell')")
  await one.goto('/admin/products', 2500)
  await capture('admin-products', 'light', "!!document.querySelector('.admin-shell')")
  await one.goto('/admin/payments', 2500)
  await capture('admin-payments', 'dark', "!!document.querySelector('.admin-shell')")
  await one.goto('/auctions', 2500)
  for (const theme of ['light', 'dark']) {
    await setTheme(theme)
    await one.call('Page.reload')
    await sleep(2200)
    const bg = await one.evaluate("getComputedStyle(document.querySelector('.auction-discovery')).backgroundColor")
    check(bg === 'rgb(14, 11, 31)', `auction area is dark in ${theme} (${bg})`)
  }
  await one.shot('T-auctions-light-theme')
})

one.close()
const problems = Object.entries(audits).filter(([, a]) => a.lowCount || a.lightCount)
report.audits = Object.fromEntries(problems)
await fs.writeFile(new URL('./theme-report.json', import.meta.url), JSON.stringify(report, null, 2))
console.log(JSON.stringify({ heights, notes: report.notes, journeys: report.journeys, shots: Object.entries(report.shots).filter(([, v]) => v !== 'ok'), errors: report.errors.slice(0, 8), audits: report.audits }, null, 1))
process.exit(Object.values(report.journeys).every((v) => v.startsWith('PASS')) && !report.errors.length ? 0 : 1)
