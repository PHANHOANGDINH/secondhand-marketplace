import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { readonly, shallowRef } from 'vue'
import api from '../api/axios'
import { getCsrfCookie } from '../api/auth.api'

const state = shallowRef('idle')
export const realtimeConnection = readonly(state)
const channels = new Map()
let echo, connection, stateChanged, online
const configured = () => !!import.meta.env.VITE_REVERB_APP_KEY

function ensureEcho() {
  if (echo) return echo
  if (!configured()) {
    state.value = 'disabled'
    return null
  }
  state.value = 'connecting'
  const scheme = import.meta.env.VITE_REVERB_SCHEME || 'http'
  const port = Number(import.meta.env.VITE_REVERB_PORT || (scheme === 'https' ? 443 : 8080))
  try {
    echo = new Echo({
      broadcaster: 'reverb',
      Pusher,
      key: import.meta.env.VITE_REVERB_APP_KEY,
      wsHost: import.meta.env.VITE_REVERB_HOST || '127.0.0.1',
      wsPort: port,
      wssPort: port,
      forceTLS: scheme === 'https',
      enabledTransports: ['ws', 'wss'],
      disableStats: true,
      authorizer: (channel) => ({
        authorize: async (socketId, callback) => {
          try {
            await getCsrfCookie()
            const entry = channels.get(`private:${channel.name.replace(/^private-/, '')}`)
            if (!entry) return callback(new Error('Subscription closed'), null)
            const response = await api.post('/api/v1/broadcasting/auth', {
              socket_id: socketId,
              channel_name: channel.name,
            })
            if (!entry || channels.get(entry.key) !== entry)
              return callback(new Error('Subscription closed'), null)
            callback(null, response.data)
          } catch {
            callback(new Error('Private subscription denied'), null)
          }
        },
      }),
    })
    connection = echo.connector.pusher.connection
    stateChanged = ({ current }) => {
      state.value = current
      if (current !== 'connected') for (const entry of channels.values()) entry.ready = false
    }
    connection.bind('state_change', stateChanged)
    online = () => connection?.connect()
    window.addEventListener('online', online)
    return echo
  } catch {
    echo = undefined
    state.value = 'unavailable'
    return null
  }
}

export function subscribeRealtime(kind, id, handlers = {}, onReady = () => {}) {
  if (!['public', 'private', 'video'].includes(kind)) return () => {}
  if (!/^[1-9][0-9]*$/.test(String(id))) return () => {}
  const client = ensureEcho()
  if (!client) return () => {}
  const name = `${kind === 'private' ? 'user' : kind === 'video' ? 'video' : 'auction'}.${id}`,
    key = `${kind}:${name}`
  let entry = channels.get(key)
  if (!entry) {
    const channel = kind === 'private' ? client.private(name) : client.channel(name)
    entry = { key, name, channel, consumers: new Set(), listeners: new Map(), ready: false }
    channels.set(key, entry)
    channel.subscribed(() => {
      if (channels.get(key) !== entry) return
      entry.ready = true
      for (const consumer of entry.consumers) consumer.onReady()
    })
    channel.error(() => {
      entry.ready = false
    })
  }
  const consumer = { handlers, onReady }
  entry.consumers.add(consumer)
  for (const event of Object.keys(handlers)) {
    if (entry.listeners.has(event)) continue
    const listener = (payload) => {
      if (channels.get(key) !== entry) return
      for (const current of entry.consumers) current.handlers[event]?.(payload)
    }
    entry.listeners.set(event, listener)
    entry.channel.listen(`.${event}`, listener)
  }
  if (entry.ready)
    queueMicrotask(() => {
      if (entry.consumers.has(consumer)) onReady()
    })
  let released = false
  return () => {
    if (released) return
    released = true
    entry.consumers.delete(consumer)
    if (!entry.consumers.size && channels.get(key) === entry) {
      for (const [event, listener] of entry.listeners)
        entry.channel.stopListening(`.${event}`, listener)
      client.leave(name)
      channels.delete(key)
      if (!channels.size) disconnectRealtime()
    }
  }
}
export function reconnectRealtime() {
  connection?.connect()
}
export function disconnectRealtime() {
  if (online) window.removeEventListener('online', online)
  if (connection && stateChanged) connection.unbind('state_change', stateChanged)
  for (const entry of channels.values()) echo?.leave(entry.name)
  channels.clear()
  echo?.disconnect()
  echo = connection = stateChanged = online = undefined
  state.value = configured() ? 'disconnected' : 'disabled'
}
if (import.meta.hot) import.meta.hot.dispose(disconnectRealtime)
