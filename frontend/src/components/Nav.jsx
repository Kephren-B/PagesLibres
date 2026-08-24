import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'

export function Nav() {
  const { isAuthenticated, logout } = useAuth()
  const navigate = useNavigate()

  return (
    <nav className="nav">
      <Link to="/" className="nav-brand">PagesLibres</Link>
      <div className="nav-links">
        <Link to="/">Livres</Link>
        {isAuthenticated && <Link to="/livres/nouveau">Ajouter un livre</Link>}
        {isAuthenticated && <Link to="/trouvaille">Déclarer une trouvaille</Link>}
        {isAuthenticated ? (
          <button
            type="button"
            onClick={() => {
              logout()
              navigate('/')
            }}
          >
            Déconnexion
          </button>
        ) : (
          <>
            <Link to="/connexion">Connexion</Link>
            <Link to="/inscription">Inscription</Link>
          </>
        )}
      </div>
    </nav>
  )
}
