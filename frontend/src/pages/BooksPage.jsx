import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api, requeteLivres } from '../api/client'
import { HomeMap } from '../components/HomeMap'

const PARIS = { lat: 48.8566, lon: 2.3522 }
const RAYONS = [1000, 5000, 20000, 50000]
const LIVRES_PAR_PAGE = 6

// F8 : filtre de statut des exemplaires. « En lecture » correspond au statut
// `trouve` — l'exemplaire est détenu par son dernier découvreur (cf. dossier § III).
const STATUTS = [
  { valeur: '', libelle: 'Tous' },
  { valeur: 'en_circulation', libelle: 'Disponibles' },
  { valeur: 'trouve', libelle: 'En lecture' },
]

export function BooksPage() {
  const [livres, setLivres] = useState([])
  const [titre, setTitre] = useState('')
  const [categorie, setCategorie] = useState('')
  const [categories, setCategories] = useState([])
  const [limite, setLimite] = useState(LIVRES_PAR_PAGE)
  const [error, setError] = useState(null)
  const [loading, setLoading] = useState(true)

  const [position, setPosition] = useState(null)
  const [rayon, setRayon] = useState(5000)
  const [statut, setStatut] = useState('')
  const [markers, setMarkers] = useState([])
  const [mapError, setMapError] = useState(null)

  // Changer de recherche ou de catégorie repart de la première page de résultats.
  function filtrerCatalogue(prochainTitre, prochaineCategorie) {
    setTitre(prochainTitre)
    setCategorie(prochaineCategorie)
    setLimite(LIVRES_PAR_PAGE)
  }

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

  // Vocabulaire des catégories, pour les pastilles de filtre (F8).
  useEffect(() => {
    api
      .listCategories()
      .then(setCategories)
      .catch(() => setCategories([]))
  }, [])

  // F8 : catalogue — recherche par titre, filtre par catégorie, pages successives.
  useEffect(() => {
    setLoading(true)
    api
      .listLivres(requeteLivres({ titre, categorie, limite }))
      .then((data) => setLivres(Array.isArray(data) ? data : []))
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false))
  }, [titre, categorie, limite])

  // F4 : carte de proximité. F8 : mêmes filtres catégorie et statut.
  useEffect(() => {
    if (!position) return
    setMapError(null)
    api
      .proximite(position.lat, position.lon, rayon, { categorie, statut })
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
  }, [position, rayon, categorie, statut])

  return (
    <div className="page page-home">
      <h1>Livres à proximité</h1>
      <p className="subtitle">Explorez la carte ou cherchez un titre précis.</p>

      <div className="home-controls">
        <input
          type="search"
          placeholder="Rechercher par titre…"
          value={titre}
          onChange={(e) => filtrerCatalogue(e.target.value, categorie)}
          className="search-input"
        />
        <div className="rayon-picker">
          <span>Catégorie</span>
          <button
            type="button"
            className={categorie === '' ? 'rayon-btn active' : 'rayon-btn'}
            onClick={() => filtrerCatalogue(titre, '')}
          >
            Toutes
          </button>
          {categories.map((c) => (
            <button
              key={c}
              type="button"
              className={c === categorie ? 'rayon-btn active' : 'rayon-btn'}
              onClick={() => filtrerCatalogue(titre, c === categorie ? '' : c)}
            >
              {c}
            </button>
          ))}
        </div>
      </div>

      <div className="home-controls">
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
        <div className="rayon-picker">
          <span>Exemplaires</span>
          {STATUTS.map((s) => (
            <button
              key={s.valeur || 'tous'}
              type="button"
              className={s.valeur === statut ? 'rayon-btn active' : 'rayon-btn'}
              onClick={() => setStatut(s.valeur)}
              title={
                s.valeur === 'trouve'
                  ? 'Exemplaires en lecture : détenus par leur dernier découvreur'
                  : undefined
              }
            >
              {s.libelle}
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
      {!loading && <p className="compteur">{livres.length} livre(s) affiché(s)</p>}
      {error && <p className="error">{error}</p>}
      {loading && <p>Chargement…</p>}

      <ul className="book-list">
        {livres.map((livre) => (
          <li key={livre.idLivre}>
            <Link to={`/livres/${livre.idLivre}`}>
              <strong>{livre.titre}</strong> — {livre.auteur}
            </Link>
            {livre.categorie && (
              <button
                type="button"
                className="tag"
                onClick={() => filtrerCatalogue(titre, livre.categorie)}
                title={`Filtrer sur la catégorie ${livre.categorie}`}
              >
                {livre.categorie}
              </button>
            )}
          </li>
        ))}
        {!loading && livres.length === 0 && (
          <li className="book-list-vide">Aucun livre ne correspond à ces filtres.</li>
        )}
      </ul>

      {!loading && livres.length === limite && (
        <button type="button" className="voir-plus" onClick={() => setLimite(limite + LIVRES_PAR_PAGE)}>
          Voir plus de livres
        </button>
      )}
    </div>
  )
}
