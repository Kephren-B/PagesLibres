import { createContext, useCallback, useContext, useState } from 'react'
import { getToken, setToken as persistToken } from '../api/client'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [token, setTokenState] = useState(() => getToken())

  const login = useCallback((newToken) => {
    persistToken(newToken)
    setTokenState(newToken)
  }, [])

  const logout = useCallback(() => {
    persistToken(null)
    setTokenState(null)
  }, [])

  return (
    <AuthContext.Provider value={{ token, isAuthenticated: Boolean(token), login, logout }}>
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
