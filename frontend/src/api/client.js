import axios from 'axios'

export const TOKEN_KEY = 'archives_token'

const baseURL = (import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api').replace(/\/$/, '')

// localStorage peut être indisponible (navigation privée, stockage bloqué) :
// on protège chaque accès pour que l'application ne plante pas.
export const tokenStore = {
  get() {
    try {
      return localStorage.getItem(TOKEN_KEY)
    } catch {
      return null
    }
  },
  set(token) {
    try {
      localStorage.setItem(TOKEN_KEY, token)
    } catch {
      /* ignoré */
    }
  },
  clear() {
    try {
      localStorage.removeItem(TOKEN_KEY)
    } catch {
      /* ignoré */
    }
  },
}

export const api = axios.create({
  baseURL,
  headers: { Accept: 'application/json' },
})

// Intercepteur de requête : injecte "Authorization: Bearer <token>" automatiquement.
api.interceptors.request.use((config) => {
  const token = tokenStore.get()
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// Intercepteur de réponse : un 401 alors qu'un token était présent = session expirée
// ou token révoqué -> on nettoie et on prévient l'application (retour au login).
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401 && tokenStore.get()) {
      tokenStore.clear()
      window.dispatchEvent(new Event('auth:expired'))
    }
    return Promise.reject(error)
  },
)
