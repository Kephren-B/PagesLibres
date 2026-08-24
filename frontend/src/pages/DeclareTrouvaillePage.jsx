import { useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../api/client'

export function DeclareTrouvaillePage() {
  const [codeBcid, setCodeBcid] = useState('')
  const [latitude, setLatitude] = useState('')
  const [longitude, setLongitude] = useState('')
  const [message, setMessage] = useState('')
  const [error, setError] = useState(null)
  const [succes, setSucces] = useState(null)
  const [busy, setBusy] = useState(false)

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

  async function handleSubmit(event) {
    event.preventDefault()
    setError(null)
    setSucces(null)
    setBusy(true)
    try {
      // F5 : saisie manuelle du code BCID — pas de scan, hors périmètre.
      const resultats = await api.listExemplaires(`?codeBcid=${encodeURIComponent(codeBcid)}`)
      const liste = Array.isArray(resultats) ? resultats : resultats.member ?? []
      if (liste.length === 0) {
        throw new Error(`Aucun exemplaire ne porte le code "${codeBcid}".`)
      }
      const exemplaire = liste[0]
      await api.createMouvement({
        exemplaire: `/api/exemplaires/${exemplaire.idExemplaire}`,
        typeMouvement: 'trouvaille',
        latitude,
        longitude,
        message: message || undefined,
      })
      setSucces(exemplaire)
      setCodeBcid('')
      setMessage('')
    } catch (err) {
      setError(err.message)
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="page page-form">
      <h1>Déclarer une trouvaille</h1>
      <form onSubmit={handleSubmit}>
        <label>
          Code BCID (inscrit sur l'exemplaire)
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
        <label>
          Message (optionnel)
          <textarea value={message} onChange={(e) => setMessage(e.target.value)} rows={3} />
        </label>
        {error && <p className="error">{error}</p>}
        {succes && (
          <p className="success">
            Trouvaille enregistrée !{' '}
            <Link to={`/livres/${succes.livre.split('/').pop()}`}>Voir la fiche du livre</Link>
          </p>
        )}
        <button type="submit" disabled={busy}>{busy ? 'Envoi…' : 'Déclarer la trouvaille'}</button>
      </form>
    </div>
  )
}
