import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { api } from '../api/client'
import { useAuth } from '../context/AuthContext'

export function RegisterPage() {
  const { login } = useAuth()
  const navigate = useNavigate()
  const [pseudo, setPseudo] = useState('')
  const [email, setEmail] = useState('')
  const [plainPassword, setPlainPassword] = useState('')
  const [error, setError] = useState(null)
  const [loading, setLoading] = useState(false)

  async function handleSubmit(event) {
    event.preventDefault()
    setError(null)
    setLoading(true)
    try {
      await api.register({ pseudo, email, plainPassword })
      const { token } = await api.login({ email, password: plainPassword })
      login(token)
      navigate('/')
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="page page-form">
      <h1>Inscription</h1>
      <form onSubmit={handleSubmit}>
        <label>
          Pseudo
          <input value={pseudo} onChange={(e) => setPseudo(e.target.value)} required maxLength={50} />
        </label>
        <label>
          Email
          <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
        </label>
        <label>
          Mot de passe (8 caractères minimum)
          <input
            type="password"
            value={plainPassword}
            onChange={(e) => setPlainPassword(e.target.value)}
            required
            minLength={8}
          />
        </label>
        {error && <p className="error">{error}</p>}
        <button type="submit" disabled={loading}>{loading ? 'Inscription…' : "S'inscrire"}</button>
      </form>
    </div>
  )
}
