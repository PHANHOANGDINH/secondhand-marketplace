// Display clock only. Bid validity always comes from the REST backend.
export function createAuctionClock({
  clientNow = Date.now,
  monotonicNow = () => performance.now(),
} = {}) {
  let anchor,
    received,
    offset = 0
  return {
    sync(serverTime) {
      const parsed = Date.parse(serverTime)
      if (!Number.isFinite(parsed)) return false
      offset = parsed - clientNow()
      anchor = parsed
      received = monotonicNow()
      return true
    },
    get offsetMs() {
      return offset
    },
    now() {
      return anchor === undefined ? clientNow() : anchor + Math.max(0, monotonicNow() - received)
    },
    remainingMs(endsAt) {
      const target = Date.parse(endsAt)
      return Number.isFinite(target) ? Math.max(0, target - this.now()) : 0
    },
  }
}
