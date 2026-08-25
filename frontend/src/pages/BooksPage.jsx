import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../api/client'
import { HomeMap } from '../components/HomeMap'

const PARIS = { lat: 48.8566, lon: 2.3522 }
const RAYONS = [1000, 5000, 20000, 50000]

export function BooksPage() {
  const [livres, setLivres] = useState([])
  const [titre, setTitre] = useState('')
  const [error, setError] = useState(null)
  const [loading, setLoading] = useState(true)

  const [position, setPosition] = useState(null)
  const [rayon, setRayon] = useState(5000)
  const [markers, setMarkers] = useState([])
  const [mapError, setMapError] = useState(null)

  useEffect(() => {
    if (!navigator.geolocation) {
      setPosition(PARIS)
      return
    }
    navigator.geolocation.getCurrentPosition(
      (pos) => setPosition({ lat: pos.coords.latitude, lon: pos.coords.longitude }),
      () => setPosition(PARIS),
      { timeout: 4000 },
    )
  }, [])

  useEffect(() => {
    const query = titre ? `?titre=${encodeURIComponent(titre)}` : ''
    setLoading(true)
    api
      .listLivres(query)
      .then((data) => setLivres(Array.isArray(data) ? data : data.member ?? []))
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false))
  }, [titre])

  useEffect(() => {
    if (!position) return
    setMapError(null)
    api
      .proximite(position.lat, position.lon, rayon)
      .then(async (data) => {
        const exemplaires = Array.isArray(data) ? data : data.member ?? []
        const livreCache = {}
        const withLivres = await Promise.all(
          exemplaires.map(async (ex) => {
            const livreIri = typeof ex.livre === 'string' ? ex.livre : ex.livre?.['@id']
            const livreId = livreIri?.split('/').pop()
            if (livreId && !livreCache[livreId]) {
              livreCache[livreId] = api.getLivre(livreId).catch(() => null)
            }
            const livre = livreId ? await livreCache[livreId] : null
            return {
              idExemplaire: ex.idExemplaire,
              codeBcid: ex.codeBcid,
              lat: ex.positionArrondie?.latitude,
              lon: ex.positionArrondie?.longitude,
              livre,
            }
          }),
        )
        setMarkers(withLivres.filter((m) => m.lat != null && m.lon != null))
      })
      .catch((err) => setMapError(err.message))
  }, [position, rayon])

  return (
    <div className="page page-home">
      <h1>Livres à proximité</h1>
      <p className="subtitle">Explorez la carte ou cherchez un titre précis.</p>

      <div className="home-controls">
        <input
          type="search"
          placeholder="Rechercher par titre…"
          value={titre}
          onChange={(e) => setTitre(e.target.value)}
          className="search-input"
        />
        <div className="rayon-picker">
          <span>Rayon</span>
          {RAYONS.map((r) => (
            <button
              key={r}
              type="button"
              className={r === rayon ? 'rayon-btn active' : 'rayon-btn'}
              onClick={() => setRayon(r)}
            >
              {r >= 1000 ? `${r / 1000} km` : `${r} m`}
            </button>
          ))}
        </div>
      </div>

      {mapError && <p className="error">{mapError}</p>}
      {position && (
        <div className="home-map-wrap">
          <HomeMap center={[position.lat, position.lon]} markers={markers} />
          <p className="map-caption">
            {markers.length === 0 ? 'Aucun exemplaire à proximité pour ce rayon.' : `${markers.length} exemplaire(s) à proximité.`}
          </p>
        </div>
      )}

      <h2>Tout le catalogue</h2>
      {error && <p className="error">{error}</p>}
      {loading && <p>Chargement…</p>}
      <ul className="book-list">
        {livres.map((livre) => (
          <li key={livre.idLivre}>
            <Link to={`/livres/${livre.idLivre}`}>
              <strong>{livre.titre}</strong> — {livre.auteur}
              {livre.categorie && <span className="tag">{livre.categorie}</span>}
            </Link>
          </li>
        ))}
        {!loading && livres.length === 0 && <li>Aucun livre trouvé.</li>}
      </ul>
    </div>
  )
}
