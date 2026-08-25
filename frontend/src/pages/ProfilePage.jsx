import { useEffect, useState } from 'react'
import { api } from '../api/client'

const LABELS_MOUVEMENT = {
  liberation: 'Libération',
  trouvaille: 'Trouvaille',
}

export function ProfilePage() {
  const [profil, setProfil] = useState(null)
  const [mouvements, setMouvements] = useState([])
  const [badges, setBadges] = useState([])
  const [error, setError] = useState(null)

  useEffect(() => {
    api
      .getMoi()
      .then(async (moi) => {
        setProfil(moi)
        const iri = `/api/utilisateurs/${moi.idUtilisateur}`
        const [mouvementsData, badgesData] = await Promise.all([
          api.listMesMouvements(iri),
          api.listMesBadges(iri),
        ])
        setMouvements(Array.isArray(mouvementsData) ? mouvementsData : mouvementsData.member ?? [])
        setBadges(Array.isArray(badgesData) ? badgesData : badgesData.member ?? [])
      })
      .catch((err) => setError(err.message))
  }, [])

  if (error) return <div className="page"><p className="error">{error}</p></div>
  if (!profil) return <div className="page"><p>Chargement…</p></div>

  return (
    <div className="page page-profile">
      <h1>{profil.pseudo}</h1>
      <p className="subtitle">
        {profil.email} — membre depuis le {new Date(profil.dateInscription).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })}
      </p>

      <h2>Badges</h2>
      {badges.length === 0 && <p>Aucun badge obtenu pour l'instant — libérez un livre pour commencer !</p>}
      <ul className="badge-list">
        {badges.map((obtention) => (
          <li key={obtention.idObtention} className="badge-card">
            <span className="badge-icon" aria-hidden="true">★</span>
            <div>
              <strong>{obtention.badge?.nom}</strong>
              <p>{obtention.badge?.description}</p>
              <span className="badge-date">
                obtenu le {new Date(obtention.dateObtention).toLocaleDateString('fr-FR')}
              </span>
            </div>
          </li>
        ))}
      </ul>

      <h2>Mon historique</h2>
      {mouvements.length === 0 && <p>Aucun mouvement pour l'instant.</p>}
      <ul className="history-list">
        {mouvements.map((m, i) => (
          <li key={i} className="history-item">
            <span className={`history-tag history-tag-${m.typeMouvement}`}>
              {LABELS_MOUVEMENT[m.typeMouvement] ?? m.typeMouvement}
            </span>
            <span className="history-date">
              {new Date(m.dateMouvement).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })}
            </span>
          </li>
        ))}
      </ul>
    </div>
  )
}
