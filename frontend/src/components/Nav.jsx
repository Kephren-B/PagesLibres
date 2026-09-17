import { NavLink, useNavigate } from 'react-router-dom'
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
      <NavLink to="/" end className="nav-brand">PagesLibres</NavLink>
      <div className="nav-links">
        <NavLink to="/" end>Livres</NavLink>
        {isAuthenticated && <NavLink to="/livres/nouveau">Ajouter un livre</NavLink>}
        {isAuthenticated && <NavLink to="/trouvaille">Déclarer une trouvaille</NavLink>}
        {isAuthenticated && <NavLink to="/profil">Mon profil</NavLink>}
        {isAdmin && <NavLink to="/admin/signalements">Modération</NavLink>}
        {isAuthenticated ? (
          <button
            type="button"
            className="nav-utilitaire"
            onClick={() => {
              logout()
              navigate('/')
            }}
          >
            Déconnexion
          </button>
        ) : (
          <>
            <NavLink to="/connexion">Connexion</NavLink>
            <NavLink to="/inscription">Inscription</NavLink>
          </>
        )}
        <button
          type="button"
          className="nav-utilitaire"
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
