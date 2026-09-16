import { createContext, useCallback, useContext, useEffect, useState } from 'react'
import { api, getToken, setToken as persistToken } from '../api/client'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [token, setTokenState] = useState(() => getToken())
  const [user, setUser] = useState(null)

  const login = useCallback((newToken) => {
    persistToken(newToken)
    setTokenState(newToken)
  }, [])

  const logout = useCallback(() => {
    persistToken(null)
    setTokenState(null)
  }, [])

  // Chargement du profil courant (rôle) dès qu'un jeton est présent :
  // permet de n'afficher le back-office qu'aux administrateurs.
  useEffect(() => {
    if (!token) {
      setUser(null)
      return
    }
    let cancelled = false
    api
      .getMoi()
      .then((moi) => { if (!cancelled) setUser(moi) })
      .catch(() => { if (!cancelled) setUser(null) })
    return () => { cancelled = true }
  }, [token])

  // Si le JWT expire pendant l'utilisation (événement émis par le client API
  // sur un 401), on déconnecte et on ramène l'utilisateur à la connexion.
  useEffect(() => {
    const handleExpired = () => {
      logout()
      window.location.assign('/connexion')
    }
    window.addEventListener('auth-expired', handleExpired)
    return () => window.removeEventListener('auth-expired', handleExpired)
  }, [logout])

  const isAdmin = user?.role === 'admin'

  return (
    <AuthContext.Provider value={{ token, user, isAuthenticated: Boolean(token), isAdmin, login, logout }}>
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) {
    throw new Error('useAuth doit être utilisé à l\'intérieur de <AuthProvider>')
  }
  return ctx
}
