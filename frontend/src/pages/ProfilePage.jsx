import { useEffect, useState } from 'react'
import { api } from '../api/client'

const LABELS_MOUVEMENT = {
  liberation: 'Libération',
  trouvaille: 'Trouvaille',
}

// 20 et non 30 : c'est le plafond que l'API applique de toute façon, et une
// page plus courte garde le bouton « Voir plus » accessible.
const MOUVEMENTS_PAR_PAGE = 20

const TRIS = [
  { valeur: 'desc', libelle: 'Plus récents d’abord' },
  { valeur: 'asc', libelle: 'Plus anciens d’abord' },
]

const FORMAT_JOUR = { day: 'numeric', month: 'long', year: 'numeric' }
const FORMAT_HEURE = { hour: '2-digit', minute: '2-digit' }

export function ProfilePage() {
  const [profil, setProfil] = useState(null)
  const [mouvements, setMouvements] = useState([])
  const [obtentions, setObtentions] = useState([])
  const [catalogue, setCatalogue] = useState([])
  const [sens, setSens] = useState('desc')
  const [page, setPage] = useState(1)
  const [pageComplete, setPageComplete] = useState(false)
  const [chargement, setChargement] = useState(false)
  const [erreur, setErreur] = useState(null)

  const [edition, setEdition] = useState(false)
  const [pseudoSaisi, setPseudoSaisi] = useState('')
  const [erreurPseudo, setErreurPseudo] = useState(null)
  const [enregistrement, setEnregistrement] = useState(false)
  const [confirmation, setConfirmation] = useState(null)

  // Chargement initial : profil, catalogue complet des badges, et badges
  // réellement obtenus. Les deux listes sont nécessaires — le profil ne
  // connaît que ce qui est déjà gagné, le catalogue seul ne dit pas où on en
  // est.
  useEffect(() => {
    let annule = false

    Promise.all([api.getMoi(), api.listBadges()])
      .then(async ([moi, badges]) => {
        if (annule) return
        setProfil(moi)
        setPseudoSaisi(moi.pseudo)
        setCatalogue(Array.isArray(badges) ? badges : badges.member ?? [])

        const obtenus = await api.listMesBadges(`/api/utilisateurs/${moi.idUtilisateur}`)
        if (!annule) setObtentions(Array.isArray(obtenus) ? obtenus : obtenus.member ?? [])
      })
      .catch((err) => {
        if (!annule) setErreur(err.message)
      })

    return () => {
      annule = true
    }
  }, [])

  // Historique : tri et pagination demandés à l'API. Trier ici une liste déjà
  // paginée ne trierait que la page affichée, pas l'historique.
  useEffect(() => {
    if (!profil) return
    let annule = false
    setChargement(true)

    api
      .listMesMouvements(`/api/utilisateurs/${profil.idUtilisateur}`, {
        page,
        sens,
        taille: MOUVEMENTS_PAR_PAGE,
      })
      .then((donnees) => {
        if (annule) return
        const lot = Array.isArray(donnees) ? donnees : donnees.member ?? []
        setPageComplete(lot.length === MOUVEMENTS_PAR_PAGE)
        setMouvements((precedents) => (page === 1 ? lot : [...precedents, ...lot]))
      })
      .catch((err) => {
        if (!annule) setErreur(err.message)
      })
      .finally(() => {
        if (!annule) setChargement(false)
      })

    return () => {
      annule = true
    }
  }, [profil, page, sens])

  // Changer de sens repart de la première page : sans cela, on empilerait la
  // première page d'un sens sur la dernière page de l'autre.
  function changerTri(nouveauSens) {
    if (nouveauSens === sens) return
    setSens(nouveauSens)
    setPage(1)
  }

  async function enregistrerPseudo(evenement) {
    evenement.preventDefault()
    setErreurPseudo(null)
    setConfirmation(null)
    setEnregistrement(true)

    try {
      const misAJour = await api.updateProfil(profil.idUtilisateur, { pseudo: pseudoSaisi })
      setProfil((precedent) => ({ ...precedent, pseudo: misAJour.pseudo }))
      setPseudoSaisi(misAJour.pseudo)
      setEdition(false)
      setConfirmation('Pseudo modifié.')
    } catch (err) {
      // Le client API remonte le message de validation de l'API tel quel
      // (« Ce pseudo est déjà pris. »), il n'y a rien à traduire.
      setErreurPseudo(err.message)
    } finally {
      setEnregistrement(false)
    }
  }

  function annulerEdition() {
    setEdition(false)
    setPseudoSaisi(profil.pseudo)
    setErreurPseudo(null)
  }

  if (erreur) return <div className="page"><p className="error">{erreur}</p></div>
  if (!profil) return <div className="page"><p>Chargement…</p></div>

  const obtentionDe = (badge) => obtentions.find((o) => o.badge?.idBadge === badge.idBadge)

  // Badges obtenus d'abord, puis le reste dans l'ordre du catalogue : la
  // question « que me reste-t-il ? » se lit d'un coup d'œil.
  const badgesTries = [...catalogue].sort((a, b) => {
    const rang = (badge) => (obtentionDe(badge) ? 0 : 1)
    return rang(a) - rang(b) || a.idBadge - b.idBadge
  })

  return (
    <div className="page page-profile">
      <div className="profil-entete">
        <h1>{profil.pseudo}</h1>
        {!edition && (
          <button
            type="button"
            className="pseudo-bouton"
            onClick={() => {
              setEdition(true)
              setConfirmation(null)
              setErreurPseudo(null)
            }}
          >
            Modifier mon pseudo
          </button>
        )}
      </div>

      {edition && (
        <form className="pseudo-form" onSubmit={enregistrerPseudo}>
          <label htmlFor="pseudo">Nouveau pseudo</label>
          <input
            id="pseudo"
            name="pseudo"
            value={pseudoSaisi}
            onChange={(evenement) => setPseudoSaisi(evenement.target.value)}
            maxLength={50}
            required
            autoComplete="off"
          />
          <button type="submit" className="pseudo-valider" disabled={enregistrement}>
            {enregistrement ? 'Enregistrement…' : 'Enregistrer'}
          </button>
          <button type="button" className="pseudo-annuler" onClick={annulerEdition}>
            Annuler
          </button>
        </form>
      )}

      {erreurPseudo && <p className="error" role="alert">{erreurPseudo}</p>}
      {confirmation && <p className="success" role="status">{confirmation}</p>}

      <p className="subtitle">
        {profil.email} — membre depuis le {new Date(profil.dateInscription).toLocaleDateString('fr-FR', FORMAT_JOUR)}
      </p>

      <h2>Badges</h2>
      {catalogue.length === 0 && <p>Chargement des badges…</p>}
      <ul className="badge-list">
        {badgesTries.map((badge) => {
          const obtention = obtentionDe(badge)
          return (
            <li
              key={badge.idBadge}
              className={`badge-card${obtention ? '' : ' badge-card-verrouille'}`}
            >
              <span className="badge-icon" aria-hidden="true">{obtention ? '★' : '☆'}</span>
              <div>
                <strong>{badge.nom}</strong>
                <p>{badge.description}</p>
                {obtention ? (
                  <span className="badge-etat">
                    Obtenu le {new Date(obtention.dateObtention).toLocaleDateString('fr-FR', FORMAT_JOUR)}
                  </span>
                ) : (
                  <span className="badge-etat">Non obtenu</span>
                )}
              </div>
            </li>
          )
        })}
      </ul>

      <h2>Mon historique</h2>
      <div className="history-outils" role="group" aria-label="Trier l'historique">
        {TRIS.map((tri) => (
          <button
            key={tri.valeur}
            type="button"
            className="pastille"
            aria-pressed={tri.valeur === sens}
            onClick={() => changerTri(tri.valeur)}
          >
            {tri.libelle}
          </button>
        ))}
      </div>

      {mouvements.length === 0 && !chargement && <p>Aucun mouvement pour l'instant.</p>}
      <ul className="history-list">
        {mouvements.map((mouvement) => (
          <li key={mouvement.idMouvement} className="history-item">
            <span className={`history-tag history-tag-${mouvement.typeMouvement}`}>
              {LABELS_MOUVEMENT[mouvement.typeMouvement] ?? mouvement.typeMouvement}
            </span>
            <time className="history-date" dateTime={mouvement.dateMouvement}>
              {new Date(mouvement.dateMouvement).toLocaleDateString('fr-FR', FORMAT_JOUR)} à{' '}
              {new Date(mouvement.dateMouvement).toLocaleTimeString('fr-FR', FORMAT_HEURE)}
            </time>
            {mouvement.message && <p className="history-message">{mouvement.message}</p>}
          </li>
        ))}
      </ul>

      {pageComplete && (
        <button
          type="button"
          className="voir-plus"
          onClick={() => setPage((precedente) => precedente + 1)}
          disabled={chargement}
        >
          {chargement ? 'Chargement…' : 'Voir plus de mouvements'}
        </button>
      )}
    </div>
  )
}
