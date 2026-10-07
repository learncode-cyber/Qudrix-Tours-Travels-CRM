import { createContext, useContext, useState, useEffect, ReactNode } from 'react'
import { authApi } from '../lib/api'

interface User {
  id: number
  name: string
  email: string
  tenant_id: number
}

interface AuthContextValue {
  user: User | null
  loading: boolean
  login: (email: string, password: string) => Promise<void>
  logout: () => Promise<void>
}

const AuthContext = createContext<AuthContextValue | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    const token = localStorage.getItem('qudrix_token')
    const storedUser = localStorage.getItem('qudrix_user')
    if (token && storedUser) {
      try {
        setUser(JSON.parse(storedUser))
      } catch {
        localStorage.removeItem('qudrix_user')
      }
    }
    setLoading(false)
  }, [])

  async function login(email: string, password: string) {
    // Calls the REAL /v1/login endpoint — no mock/fake auth.
    const { data } = await authApi.post('/login', { email, password })
    localStorage.setItem('qudrix_token', data.token)
    localStorage.setItem('qudrix_user', JSON.stringify(data.user))
    setUser(data.user)
  }

  async function logout() {
    try {
      await authApi.post('/logout')
    } finally {
      localStorage.removeItem('qudrix_token')
      localStorage.removeItem('qudrix_user')
      setUser(null)
    }
  }

  return (
    <AuthContext.Provider value={{ user, loading, login, logout }}>
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth must be used within AuthProvider')
  return ctx
}
