import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { api } from '../api/client'
import { rechercherParIsbn, IsbnNonTrouveError } from '../api/googleBooks'

export function NewBookPage() {
  const navigate = useNavigate()
  const [form, setForm] = useState({
    titre: '',
    auteur: '',
    categorie: '',
    isbn: '',
    anneePublication: '',
    resume: '',
    couvertureUrl: '',
  })
  const [error, setError] = useState(null)
  const [loading, setLoading] = useState(false)
  const [recherche, setRecherche] = useState(false)
  const [rechercheErreur, setRechercheErreur] = useState(null)

  function update(field) {
    return (event) => setForm((prev) => ({ ...prev, [field]: event.target.value }))
  }

  async function handleRechercheIsbn() {
    setRechercheErreur(null)
    if (!form.isbn) {
      setRechercheErreur('Saisissez un ISBN avant de lancer la recherche.')
      return
    }
    setRecherche(true)
    try {
      const trouve = await rechercherParIsbn(form.isbn)
      setForm((prev) => ({
        ...prev,
        titre: trouve.titre || prev.titre,
        auteur: trouve.auteur || prev.auteur,
        anneePublication: trouve.anneePublication ?? prev.anneePublication,
        resume: trouve.resume || prev.resume,
        couvertureUrl: trouve.couvertureUrl || prev.couvertureUrl,
      }))
    } catch (err) {
      setRechercheErreur(
        err instanceof IsbnNonTrouveError
          ? err.message
          : `${err.message} (vous pouvez continuer en saisie manuelle)`,
      )
    } finally {
      setRecherche(false)
    }
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setError(null)
    setLoading(true)
    try {
      const payload = {
        ...form,
        isbn: form.isbn || undefined,
        resume: form.resume || undefined,
        couvertureUrl: form.couvertureUrl || undefined,
        anneePublication: form.anneePublication ? Number(form.anneePublication) : undefined,
      }
      const livre = await api.createLivre(payload)
      navigate(`/livres/${livre.idLivre}`)
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="page page-form">
      <h1>Ajouter un livre</h1>
      <form onSubmit={handleSubmit}>
        <label>
          ISBN
          <div className="isbn-lookup">
            <input value={form.isbn} onChange={update('isbn')} placeholder="ex : 9782070368228" />
            <button type="button" onClick={handleRechercheIsbn} disabled={recherche}>
              {recherche ? 'Recherche…' : 'Rechercher (Google Books)'}
            </button>
          </div>
        </label>
        {rechercheErreur && <p className="error" role="alert">{rechercheErreur}</p>}
        {form.couvertureUrl && (
          <img src={form.couvertureUrl} alt="Couverture trouvée" className="couverture-preview" />
        )}
        <label>
          Titre
          <input value={form.titre} onChange={update('titre')} required />
        </label>
        <label>
          Auteur
          <input value={form.auteur} onChange={update('auteur')} required />
        </label>
        <label>
          Catégorie
          <input value={form.categorie} onChange={update('categorie')} required />
        </label>
        <label>
          Année de publication (optionnel)
          <input type="number" value={form.anneePublication} onChange={update('anneePublication')} />
        </label>
        <label>
          Résumé (optionnel)
          <textarea value={form.resume} onChange={update('resume')} rows={4} />
        </label>
        {error && <p className="error">{error}</p>}
        <button type="submit" disabled={loading}>{loading ? 'Création…' : 'Créer le livre'}</button>
      </form>
    </div>
  )
}
