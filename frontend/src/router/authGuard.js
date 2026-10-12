export function createAuthGuard(auth) {
  return async (to) => {
    await auth.initialize()
    if ((to.meta.requiresAuth || to.meta.requiresAdmin) && !auth.isAuthenticated)
      return { name: 'login', ...(to.fullPath ? { query: { redirect: to.fullPath } } : {}) }
    if (to.meta.requiresAdmin && !auth.isAdmin) return { name: 'forbidden' }
    if (to.meta.guestOnly && auth.isAuthenticated) return { name: 'home' }
    return true
  }
}
