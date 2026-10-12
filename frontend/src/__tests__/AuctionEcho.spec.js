import { afterEach, beforeEach, expect, it, vi } from 'vitest'
const mock = vi.hoisted(() => ({ instances: [], post: vi.fn(), csrf: vi.fn() }))
vi.mock('../api/axios', () => ({ default: { post: mock.post } }))
vi.mock('../api/auth.api', () => ({ getCsrfCookie: mock.csrf }))
vi.mock('pusher-js', () => ({ default: class {} }))
vi.mock('laravel-echo', () => ({
  default: class {
    constructor(options) {
      this.options = options
      this.channels = new Map()
      this.connector = {
        pusher: { connection: { bind: vi.fn(), unbind: vi.fn(), connect: vi.fn() } },
      }
      this.leave = vi.fn()
      this.disconnect = vi.fn()
      mock.instances.push(this)
    }
    channel(name) {
      if (!this.channels.has(name))
        this.channels.set(name, {
          listen: vi.fn(),
          stopListening: vi.fn(),
          subscribed: vi.fn(),
          error: vi.fn(),
        })
      return this.channels.get(name)
    }
    private(name) {
      return this.channel(name)
    }
  },
}))
import {
  disconnectRealtime,
  realtimeConnection,
  reconnectRealtime,
  subscribeRealtime,
} from '../composables/auctionEcho'
const emit = (channel, event, payload) =>
  channel.listen.mock.calls.find(([name]) => name === `.${event}`)[1](payload)
beforeEach(() => {
  vi.stubEnv('VITE_REVERB_APP_KEY', 'public-demo-key')
  mock.instances.length = 0
  mock.csrf.mockResolvedValue()
  mock.post.mockResolvedValue({ data: { auth: 'signed' } })
})
afterEach(() => {
  disconnectRealtime()
  vi.unstubAllEnvs()
  vi.clearAllMocks()
})
it('deduplicates one connection/channel/listener while delivering to all consumers', () => {
  const a = vi.fn(),
    b = vi.fn()
  const releaseA = subscribeRealtime('public', 7, { AuctionBidPlaced: a })
  const releaseB = subscribeRealtime('public', 7, { AuctionBidPlaced: b })
  expect(mock.instances).toHaveLength(1)
  const client = mock.instances[0],
    channel = client.channels.get('auction.7')
  expect(channel.listen).toHaveBeenCalledTimes(1)
  emit(channel, 'AuctionBidPlaced', { amount: '10.00' })
  expect(a).toHaveBeenCalledTimes(1)
  expect(b).toHaveBeenCalledTimes(1)
  releaseA()
  releaseA()
  expect(client.leave).not.toHaveBeenCalled()
  emit(channel, 'AuctionBidPlaced', {})
  expect(a).toHaveBeenCalledTimes(1)
  expect(b).toHaveBeenCalledTimes(2)
  releaseB()
  expect(client.leave).toHaveBeenCalledWith('auction.7')
  expect(client.disconnect).toHaveBeenCalledOnce()
  emit(channel, 'AuctionBidPlaced', {})
  expect(b).toHaveBeenCalledTimes(2)
})
it('recovers on each subscription acknowledgement and exposes connection state', async () => {
  const ready = vi.fn()
  subscribeRealtime('public', 7, {}, ready)
  const client = mock.instances[0],
    channel = client.channels.get('auction.7')
  const change = client.connector.pusher.connection.bind.mock.calls[0][1]
  change({ current: 'connected' })
  expect(realtimeConnection.value).toBe('connected')
  channel.subscribed.mock.calls[0][0]()
  expect(ready).toHaveBeenCalledOnce()
  change({ current: 'disconnected' })
  expect(realtimeConnection.value).toBe('disconnected')
  reconnectRealtime()
  expect(client.connector.pusher.connection.connect).toHaveBeenCalledOnce()
  channel.subscribed.mock.calls[0][0]()
  expect(ready).toHaveBeenCalledTimes(2)
  const another = vi.fn()
  subscribeRealtime('public', 7, {}, another)
  await Promise.resolve()
  expect(another).toHaveBeenCalledOnce()
})
it('uses existing CSRF and credentialed Axios for private authorization', async () => {
  subscribeRealtime('private', 9, { AuctionOutbid: vi.fn() })
  const callback = vi.fn(),
    client = mock.instances[0]
  await client.options.authorizer({ name: 'private-user.9' }).authorize('123.456', callback)
  expect(mock.csrf).toHaveBeenCalledOnce()
  expect(mock.post).toHaveBeenCalledWith('/api/v1/broadcasting/auth', {
    socket_id: '123.456',
    channel_name: 'private-user.9',
  })
  expect(callback).toHaveBeenCalledWith(null, { auth: 'signed' })
})
it('rejects stale private authorization after logout/unsubscribe', async () => {
  let resolve
  mock.post.mockImplementationOnce(
    () =>
      new Promise((done) => {
        resolve = done
      }),
  )
  const release = subscribeRealtime('private', 9),
    callback = vi.fn()
  const pending = mock.instances[0].options
    .authorizer({ name: 'private-user.9' })
    .authorize('1.2', callback)
  await Promise.resolve()
  release()
  resolve({ data: { auth: 'old-signature' } })
  await pending
  expect(callback.mock.calls[0][0]).toBeInstanceOf(Error)
  expect(callback.mock.calls[0][1]).toBe(null)
})
it('fails private authorization safely without leaking server errors', async () => {
  mock.post.mockRejectedValueOnce(new Error('Internal secret'))
  subscribeRealtime('private', 9)
  const callback = vi.fn()
  await mock.instances[0].options.authorizer({ name: 'private-user.9' }).authorize('1.2', callback)
  expect(callback.mock.calls[0][0].message).toBe('Private subscription denied')
})
it('cleans global connection and online handler on final unsubscribe', () => {
  const release = subscribeRealtime('public', 1),
    client = mock.instances[0]
  release()
  window.dispatchEvent(new Event('online'))
  expect(client.connector.pusher.connection.unbind).toHaveBeenCalledOnce()
  expect(client.connector.pusher.connection.connect).not.toHaveBeenCalled()
  subscribeRealtime('public', 1)
  expect(mock.instances).toHaveLength(2)
})
it('stays inert when configuration is absent or ID invalid', () => {
  subscribeRealtime('public', '../invalid')
  expect(mock.instances).toHaveLength(0)
  vi.stubEnv('VITE_REVERB_APP_KEY', '')
  subscribeRealtime('public', 1)
  expect(mock.instances).toHaveLength(0)
  expect(realtimeConnection.value).toBe('disabled')
})
it('shares public video channels without changing auction subscriptions', () => {
  const handler = vi.fn()
  const releaseVideo = subscribeRealtime('video', 7, { VideoCommentCreated: handler })
  const releaseAuction = subscribeRealtime('public', 7)
  const client = mock.instances[0]
  expect(mock.instances).toHaveLength(1)
  expect(client.channels.has('video.7')).toBe(true)
  expect(client.channels.has('auction.7')).toBe(true)
  emit(client.channels.get('video.7'), 'VideoCommentCreated', { video_id: '7' })
  expect(handler).toHaveBeenCalledOnce()
  releaseVideo()
  expect(client.leave).toHaveBeenCalledWith('video.7')
  expect(client.disconnect).not.toHaveBeenCalled()
  releaseAuction()
  expect(client.disconnect).toHaveBeenCalledOnce()
})
