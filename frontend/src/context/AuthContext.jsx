import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'
import { authApi } from '../api/services'
import { tokenStore } from '../api/client'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  // Tant qu'on vérifie le token stocké, on ne sait pas si l'utilisateur est connecté.
  const [loading, setLoading] = useState(() => Boolean(tokenStore.get()))

  // Au démarrage : si un token est présent, on récupère l'utilisateur correspondant.
  useEffect(() => {
    if (!tokenStore.get()) return undefined
    let active = true
    authApi
      .me()
      .then((res) => active && setUser(res.data.data))
      .catch(() => {
        tokenStore.clear()
      })
      .finally(() => active && setLoading(false))
    return () => {
      active = false
    }
  }, [])

  // Déclenché par l'intercepteur Axios quand l'API répond 401 avec un token présent.
  useEffect(() => {
    const onExpired = () => setUser(null)
    window.addEventListener('auth:expired', onExpired)
    return () => window.removeEventListener('auth:expired', onExpired)
  }, [])

  const startSession = useCallback((payload) => {
    tokenStore.set(payload.token)
    setUser(payload.user)
  }, [])

  const login = useCallback(
    async (credentials) => {
      const res = await authApi.login(credentials)
      startSession(res.data)
    },
    [startSession],
  )

  const register = useCallback(
    async (data) => {
      const res = await authApi.register(data)
      startSession(res.data)
    },
    [startSession],
  )

  const logout = useCallback(async () => {
    try {
      await authApi.logout() // révoque le token côté serveur
    } catch {
      /* même si l'appel échoue, on ferme la session locale */
    } finally {
      tokenStore.clear()
      setUser(null)
    }
  }, [])

  // Après une modification du profil : met à jour l'utilisateur affiché partout.
  const updateUser = useCallback((next) => setUser((current) => ({ ...current, ...next })), [])

  const value = useMemo(
    () => ({
      user,
      loading,
      isAdmin: user?.role === 'admin',
      isStaff: user?.role === 'admin' || user?.role === 'archiviste',
      login,
      register,
      logout,
      updateUser,
    }),
    [user, loading, login, register, logout, updateUser],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

// eslint-disable-next-line react-refresh/only-export-components
export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth doit être utilisé dans <AuthProvider>')
  return ctx
}
