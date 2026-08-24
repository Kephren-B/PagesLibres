import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../api/client'

export function BooksPage() {
  const [livres, setLivres] = useState([])
  const [titre, setTitre] = useState('')
  const [error, setError] = useState(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    const query = titre ? `?titre=${encodeURIComponent(titre)}` : ''
    setLoading(true)
    api
      .listLivres(query)
      .then((data) => setLivres(Array.isArray(data) ? data : data.member ?? []))
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false))
  }, [titre])

  return (
    <div className="page">
      <h1>Livres</h1>
      <input
        type="search"
        placeholder="Rechercher par titre…"
        value={titre}
        onChange={(e) => setTitre(e.target.value)}
        className="search-input"
      />
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
