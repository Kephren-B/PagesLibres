import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { useTheme } from '../theme'

export function Nav() {
  const { isAuthenticated, logout, isAdmin } = useAuth()
  const { theme, basculer } = useTheme()
  const navigate = useNavigate()

  // Le bouton annonce la destination du clic, pas l'état courant.
  const themeCible = theme === 'sombre' ? 'clair' : 'sombre'

  return (
    <nav className="nav">
      <Link to="/" className="nav-brand">PagesLibres</Link>
      <div className="nav-links">
        <Link to="/">Livres</Link>
        {isAuthenticated && <Link to="/livres/nouveau">Ajouter un livre</Link>}
        {isAuthenticated && <Link to="/trouvaille">Déclarer une trouvaille</Link>}
        {isAuthenticated && <Link to="/profil">Mon profil</Link>}
        {isAdmin && <Link to="/admin/signalements">Modération</Link>}
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
        <button
          type="button"
          className="theme-toggle"
          onClick={basculer}
          aria-label={`Passer au thème ${themeCible}`}
          title={`Passer au thème ${themeCible}`}
        >
          Thème {themeCible}
        </button>
      </div>
    </nav>
  )
}
