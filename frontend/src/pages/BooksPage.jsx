import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { api, requeteLivres } from '../api/client'
import { HomeMap } from '../components/HomeMap'
import {
  IconeChevron,
  IconeCible,
  IconeCroix,
  IconeEpingle,
  IconeEtiquette,
  IconeLoupe,
  IconeOeil,
} from '../components/Icones'

const PARIS = { lat: 48.8566, lon: 2.3522 }
const RAYONS = [1000, 5000, 20000, 50000]
const LIVRES_PAR_PAGE = 6
const TEMPORISATION_SAISIE = 300

// F8 : filtre de statut des exemplaires. « En lecture » correspond au statut
// `trouve` — l'exemplaire est détenu par son dernier découvreur (cf. dossier § III).
const STATUTS = [
  { valeur: '', libelle: 'Tous' },
  { valeur: 'en_circulation', libelle: 'Disponibles' },
  { valeur: 'trouve', libelle: 'En lecture' },
]

export function BooksPage() {
  // Le fil d'Ariane d'une fiche pointe vers « /?categorie=… » : le catalogue
  // s'ouvre donc déjà filtré, au lieu d'ignorer le paramètre.
  const [parametres] = useSearchParams()
  const [livres, setLivres] = useState([])
  const [recherche, setRecherche] = useState('')
  const [titre, setTitre] = useState('')
  const [categorie, setCategorie] = useState(() => parametres.get('categorie') ?? '')
  const [categories, setCategories] = useState([])
  const [page, setPage] = useState(1)
  // La dernière page reçue était-elle pleine ? Sinon, le catalogue est fini.
  const [pageComplete, setPageComplete] = useState(false)
  const [error, setError] = useState(null)
  const [loading, setLoading] = useState(true)

  const [position, setPosition] = useState(null)
  const [rayon, setRayon] = useState(5000)
  const [statut, setStatut] = useState('')
  const [markers, setMarkers] = useState([])
  const [mapError, setMapError] = useState(null)

  // Temporisation de la saisie : sans elle, chaque frappe déclenchait une requête
  // et faisait clignoter le catalogue.
  useEffect(() => {
    const minuteur = setTimeout(() => {
      setTitre(recherche)
      setPage(1)
    }, TEMPORISATION_SAISIE)

    return () => clearTimeout(minuteur)
  }, [recherche])

  // Changer de catégorie repart de la première page de résultats.
  function filtrerCategorie(prochaineCategorie) {
    setCategorie(prochaineCategorie)
    setPage(1)
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

  // Vocabulaire des catégories, pour le filtre de catégorie (F8).
  useEffect(() => {
    api
      .listCategories()
      .then(setCategories)
      .catch(() => setCategories([]))
  }, [])

  // F8 : catalogue — recherche par titre, filtre par catégorie, pages successives.
  // La réponse JSON des collections ne porte pas le total : on s'arrête donc à la
  // première page incomplète.
  useEffect(() => {
    let annule = false
    setLoading(true)
    api
      .listLivres(requeteLivres({ titre, categorie, limite: LIVRES_PAR_PAGE, page }))
      .then((donnees) => {
        if (annule) return
        const lot = Array.isArray(donnees) ? donnees : []
        setPageComplete(lot.length === LIVRES_PAR_PAGE)
        setLivres((precedents) => (page === 1 ? lot : [...precedents, ...lot]))
      })
      .catch((err) => {
        if (!annule) setError(err.message)
      })
      .finally(() => {
        if (!annule) setLoading(false)
      })

    return () => {
      annule = true
    }
  }, [titre, categorie, page])

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

      <section className="filtres" aria-label="Filtres de recherche">
        <div className="filtre-recherche">
          <IconeLoupe />
          <label htmlFor="recherche-titre" className="sr-only">
            Rechercher un livre par titre
          </label>
          <input
            id="recherche-titre"
            type="search"
            placeholder="Rechercher un titre…"
            value={recherche}
            onChange={(e) => setRecherche(e.target.value)}
          />
          {recherche !== '' && (
            <button
              type="button"
              className="filtre-effacer"
              onClick={() => setRecherche('')}
              aria-label="Effacer la recherche"
            >
              <IconeCroix />
            </button>
          )}
        </div>

        <div className="filtre-groupe">
          <label className="filtre-libelle" htmlFor="filtre-categorie">
            <IconeEtiquette />
            Catégorie
          </label>
          {/* Liste ouverte (elle grandit avec le catalogue) : un select natif est
              plus compact en mobile que sept pastilles, et reste accessible
              (libellé associé, clavier, lecteur d'écran). */}
          <select
            id="filtre-categorie"
            className="filtre-select"
            value={categorie}
            onChange={(e) => filtrerCategorie(e.target.value)}
          >
            <option value="">Toutes les catégories</option>
            {categories.map((c) => (
              <option key={c} value={c}>
                {c}
              </option>
            ))}
          </select>
        </div>

        <div className="filtre-groupe">
          <span className="filtre-libelle" id="filtre-rayon">
            <IconeCible />
            Rayon
          </span>
          <div className="filtre-valeurs" role="group" aria-labelledby="filtre-rayon">
            {RAYONS.map((r) => (
              <button
                key={r}
                type="button"
                className="pastille"
                aria-pressed={r === rayon}
                onClick={() => setRayon(r)}
              >
                {r >= 1000 ? `${r / 1000} km` : `${r} m`}
              </button>
            ))}
          </div>
        </div>

        <div className="filtre-groupe">
          <span className="filtre-libelle" id="filtre-statut">
            <IconeOeil />
            Exemplaires
          </span>
          <div className="filtre-valeurs" role="group" aria-labelledby="filtre-statut">
            {STATUTS.map((s) => (
              <button
                key={s.valeur || 'tous'}
                type="button"
                className="pastille"
                aria-pressed={s.valeur === statut}
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
      </section>

      {mapError && <p className="error">{mapError}</p>}
      {position && (
        <div className="home-map-wrap">
          <HomeMap center={[position.lat, position.lon]} markers={markers} />
          <p className="map-caption">
            <IconeEpingle />
            {markers.length === 0
              ? 'Aucun exemplaire à proximité pour ce rayon.'
              : `${markers.length} exemplaire(s) à proximité.`}
          </p>
        </div>
      )}

      <h2>Tout le catalogue</h2>
      {livres.length > 0 && <p className="compteur">{livres.length} livre(s) affiché(s)</p>}
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
                onClick={() => filtrerCategorie(livre.categorie)}
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

      {pageComplete && (
        <button
          type="button"
          className="voir-plus"
          disabled={loading}
          onClick={() => setPage((precedente) => precedente + 1)}
        >
          Voir plus de livres
          <IconeChevron />
        </button>
      )}
    </div>
  )
}
