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

  const reload = useCallback(async () => {
    const livreData = await api.getLivre(id)
    setLivre(livreData)
    const exemplairesData = await api.listExemplaires(`?livre=/api/livres/${id}`)
    setExemplaires(Array.isArray(exemplairesData) ? exemplairesData : exemplairesData.member ?? [])
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
            <code>{exemplaire.codeBcid}</code> — {STATUTS[exemplaire.statut] ?? exemplaire.statut}
            <button type="button" onClick={() => handleOuvrirJournal(exemplaire.idExemplaire)}>
              Voir le journal de voyage
            </button>
          </li>
        ))}
        {exemplaires.length === 0 && <li>Aucun exemplaire libéré pour l'instant.</li>}
      </ul>

      {selectedExemplaire && (
        <div className="journal">
          <h3>Journal de voyage — {selectedExemplaire.codeBcid}</h3>
          <JourneyMap points={journeyPoints} />
          <ol>
            {selectedExemplaire.mouvements.map((m, i) => (
              <li key={i}>
                {m.typeMouvement} — {new Date(m.dateMouvement).toLocaleString('fr-FR')}
                {m.message && <> — « {m.message} »</>}
              </li>
            ))}
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
    </div>
  )
}
