export function createAuthGuard(auth) {
  return async (to) => {
    await auth.initialize()
    if ((to.meta.requiresAuth || to.meta.requiresAdmin) && !auth.isAuthenticated)
      return { name: 'login', ...(to.fullPath ? { query: { redirect: to.fullPath } } : {}) }
    if (to.meta.requiresAdmin && !auth.isAdmin) return { name: 'forbidden' }
    if (to.meta.guestOnly && auth.isAuthenticated) return { name: 'home' }
    // Signed in with a temporary password: nothing else until a new password is set.
    if (auth.user?.must_change_password && to.name !== 'account-password')
      return {
        name: 'account-password',
        ...(to.fullPath && to.fullPath !== '/' ? { query: { redirect: to.fullPath } } : {}),
      }
    return true
  }
}
