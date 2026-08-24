import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { api } from '../api/client'

export function NewBookPage() {
  const navigate = useNavigate()
  const [form, setForm] = useState({ titre: '', auteur: '', categorie: '', isbn: '', resume: '' })
  const [error, setError] = useState(null)
  const [loading, setLoading] = useState(false)

  function update(field) {
    return (event) => setForm((prev) => ({ ...prev, [field]: event.target.value }))
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setError(null)
    setLoading(true)
    try {
      const payload = { ...form, isbn: form.isbn || undefined, resume: form.resume || undefined }
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
          ISBN (optionnel — recherche Google Books à intégrer en Phase 2)
          <input value={form.isbn} onChange={update('isbn')} />
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
