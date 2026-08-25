import { useCallback, useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import { api } from '../api/client'
import { useAuth } from '../context/AuthContext'
import { JourneyMap } from '../components/JourneyMap'

const STATUTS = {
  en_circulation: 'En circulation',
  trouve: 'Trouvé',
  signale: 'Signalé',
  retire: 'Retiré',
}

const LABELS_MOUVEMENT = {
  liberation: 'Libéré',
  trouvaille: 'Trouvé',
}

export function BookDetailPage() {
  const { id } = useParams()
  const { isAuthenticated } = useAuth()
  const [livre, setLivre] = useState(null)
  const [exemplaires, setExemplaires] = useState([]);
  const [selectedExemplaire, setSelectedExemplaire] = useState(null)
  const [error, setError] = useState(null)
  const [codeBcid, setCodeBcid] = useState('')
  const [latitude, setLatitude] = useState('')
  const [longitude, setLongitude] = useState('')
  const [busy, setBusy] = useState(false)
  const [avis, setAvis] = useState([])
  const [commentaires, setCommentaires] = useState({})
  const [note, setNote] = useState('5')
  const [avisTexte, setAvisTexte] = useState('')
  const [commentContenu, setCommentContenu] = useState('')

  const reload = useCallback(async () => {
    const livreData = await api.getLivre(id)
    setLivre(livreData)
    const exemplairesData = await api.listExemplaires(`?livre=/api/livres/${id}`)
    setExemplaires(Array.isArray(exemplairesData) ? exemplairesData : exemplairesData.member ?? [])

    const avisData = await api.listAvis(id)
    const avisList = Array.isArray(avisData) ? avisData : avisData.member ?? []
    setAvis(avisList)
    const commentairesByAvis = {}
    for (const avisItem of avisList) {
      const c = await api.listCommentaires(`?avis=${avisItem.idAvis}`)
      commentairesByAvis[avisItem.idAvis] = Array.isArray(c) ? c : c.member ?? []
    }
    setCommentaires(commentairesByAvis)
  }, [id])

  useEffect(() => {
    reload().catch((err) => setError(err.message))
  }, [reload])

  async function handleOuvrirJournal(exemplaireId) {
    setError(null)
    try {
      const detail = await api.getExemplaire(exemplaireId)
      setSelectedExemplaire(detail)
    } catch (err) {
      setError(err.message)
    }
  }

  function useMaPosition() {
    if (!navigator.geolocation) {
      setError('La géolocalisation n\'est pas disponible sur ce navigateur.')
      return
    }
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        setLatitude(String(pos.coords.latitude))
        setLongitude(String(pos.coords.longitude))
      },
      () => setError('Impossible de récupérer votre position.')
    )
  }

  async function handleLibererExemplaire(event) {
    event.preventDefault()
    setError(null)
    setBusy(true)
    try {
      const exemplaire = await api.createExemplaire({
        livre: `/api/livres/${id}`,
        codeBcid,
      })
      await api.createMouvement({
        exemplaire: `/api/exemplaires/${exemplaire.idExemplaire}`,
        typeMouvement: 'liberation',
        latitude,
        longitude,
      })
      setCodeBcid('')
      setLatitude('')
      setLongitude('')
      await reload()
    } catch (err) {
      setError(err.message)
    } finally {
      setBusy(false)
    }
  }

  async function handleAjouterAvis(event) {
    event.preventDefault()
    setError(null)
    try {
      await api.createAvis({
        livre: `/api/livres/${id}`,
        note: Number(note),
        commentaire: avisTexte.trim() || null,
      })
      setNote('5')
      setAvisTexte('')
      await reload()
    } catch (err) {
      setError(err.message)
    }
  }

  async function handleAjouterCommentaire(event, avisId) {
    event.preventDefault()
    setError(null)
    try {
      await api.createCommentaire({ avis: `/api/avis/${avisId}`, contenu: commentContenu })
      setCommentContenu('')
      await reload()
    } catch (err) {
      setError(err.message)
    }
  }

  if (!livre) {
    return <div className="page">{error ? <p className="error">{error}</p> : <p>Chargement…</p>}</div>
  }

  const journeyPoints = selectedExemplaire
    ? selectedExemplaire.mouvements.map((m) => ({
        lat: m.positionArrondie.latitude,
        lon: m.positionArrondie.longitude,
        label: `${m.typeMouvement} — ${new Date(m.dateMouvement).toLocaleString('fr-FR')}`,
      }))
    : []

  return (
    <div className="page">
      <h1>{livre.titre}</h1>
      <p className="subtitle">{livre.auteur} — {livre.categorie}{livre.anneePublication ? ` (${livre.anneePublication})` : ''}</p>
      {livre.resume && <p>{livre.resume}</p>}

      {error && <p className="error">{error}</p>}

      <h2>Exemplaires en circulation</h2>
      <ul className="exemplaire-list">
        {exemplaires.map((exemplaire) => (
          <li key={exemplaire.idExemplaire}>
            <code className="stamp">{exemplaire.codeBcid}</code> — {STATUTS[exemplaire.statut] ?? exemplaire.statut}
            <button type="button" onClick={() => handleOuvrirJournal(exemplaire.idExemplaire)}>
              Voir le journal de voyage
            </button>
          </li>
        ))}
        {exemplaires.length === 0 && <li>Aucun exemplaire libéré pour l'instant.</li>}
      </ul>

      {selectedExemplaire && (
        <div className="journal">
          <h3>Journal de voyage — <span className="stamp">{selectedExemplaire.codeBcid}</span></h3>
          <JourneyMap points={journeyPoints} />
          <ol className="timeline">
            {selectedExemplaire.mouvements.map((m, i) => (
              <li key={i} className="timeline-step">
                <span className="timeline-dot" />
                <div className="timeline-content">
                  <span className="timeline-label">
                    {LABELS_MOUVEMENT[m.typeMouvement] ?? m.typeMouvement}
                    {m.utilisateur?.pseudo && <> par {m.utilisateur.pseudo}</>}
                  </span>
                  <span className="timeline-date">{new Date(m.dateMouvement).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })}</span>
                  {m.message && <p className="timeline-message">« {m.message} »</p>}
                </div>
              </li>
            ))}
            {selectedExemplaire.statut === 'trouve' && (
              <li className="timeline-step timeline-step-pending">
                <span className="timeline-dot timeline-dot-pending" />
                <div className="timeline-content">
                  <span className="timeline-label">En attente d'une nouvelle libération…</span>
                </div>
              </li>
            )}
            {selectedExemplaire.statut === 'en_circulation' && (
              <li className="timeline-step timeline-step-pending">
                <span className="timeline-dot timeline-dot-pending" />
                <div className="timeline-content">
                  <span className="timeline-label">En attente d'une nouvelle trouvaille…</span>
                </div>
              </li>
            )}
          </ol>
        </div>
      )}

      {isAuthenticated && (
        <div className="page-form">
          <h2>Libérer un nouvel exemplaire</h2>
          <form onSubmit={handleLibererExemplaire}>
            <label>
              Code BCID
              <input value={codeBcid} onChange={(e) => setCodeBcid(e.target.value)} required maxLength={20} />
            </label>
            <label>
              Latitude
              <input value={latitude} onChange={(e) => setLatitude(e.target.value)} required />
            </label>
            <label>
              Longitude
              <input value={longitude} onChange={(e) => setLongitude(e.target.value)} required />
            </label>
            <button type="button" onClick={useMaPosition}>Utiliser ma position actuelle</button>
            <button type="submit" disabled={busy}>{busy ? 'Libération…' : 'Libérer cet exemplaire'}</button>
          </form>
        </div>
      )}

      <h2>Avis des lecteurs</h2>
      {avis.length === 0 && <p>Aucun avis pour l'instant.</p>}
      <ul className="avis-list">
        {avis.map((avisItem) => (
          <li key={avisItem.idAvis}>
            <strong>{avisItem.utilisateur?.pseudo ?? 'Anonyme'}</strong> — {'★'.repeat(avisItem.note)}{'☆'.repeat(5 - avisItem.note)}
            {avisItem.commentaire && <p>{avisItem.commentaire}</p>}
            <ul className="commentaire-list">
              {(commentaires[avisItem.idAvis] ?? []).map((c) => (
                <li key={c.idCommentaire}>
                  <strong>{c.utilisateur?.pseudo ?? 'Anonyme'}</strong> — {c.contenu}
                </li>
              ))}
            </ul>
            {isAuthenticated && (
              <form className="inline-form" onSubmit={(e) => handleAjouterCommentaire(e, avisItem.idAvis)}>
                <input
                  value={commentContenu}
                  onChange={(e) => setCommentContenu(e.target.value)}
                  placeholder="Répondre…"
                  required
                />
                <button type="submit">Commenter</button>
              </form>
            )}
          </li>
        ))}
      </ul>

      {isAuthenticated && (
        <div className="page-form">
          <h3>Donner mon avis</h3>
          <form onSubmit={handleAjouterAvis}>
            <label>
              Note (1-5)
              <select value={note} onChange={(e) => setNote(e.target.value)}>
                {[1, 2, 3, 4, 5].map((n) => (
                  <option key={n} value={n}>{n}</option>
                ))}
              </select>
            </label>
            <label>
              Commentaire
              <textarea value={avisTexte} onChange={(e) => setAvisTexte(e.target.value)} />
            </label>
            <button type="submit">Publier mon avis</button>
          </form>
        </div>
      )}
    </div>
  )
}
