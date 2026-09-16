import { useCallback, useEffect, useState } from 'react'
import { api } from '../api/client'

// F10 — Back-office de modération (réservé aux administrateurs).
const STATUTS = {
  en_attente: 'En attente',
  traite: 'Traité',
  rejete: 'Rejeté',
}

const FILTRES = [
  { valeur: 'en_attente', label: 'En attente' },
  { valeur: 'traite', label: 'Traités' },
  { valeur: 'rejete', label: 'Rejetés' },
  { valeur: 'tous', label: 'Tous' },
]

function extraireId(iri) {
  if (!iri) return null
  const parts = String(iri).split('/')
  return parts[parts.length - 1]
}

const FORMAT_DATE = { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' }

// Résout une description lisible de la ressource signalée (l'API renvoie des
// IRI pour les cibles : on va chercher le détail pour afficher un libellé).
async function decrireCible(s) {
  if (s.exemplaire) {
    try {
      const ex = await api.getExemplaire(extraireId(s.exemplaire))
      return { type: 'exemplaire', libelle: `Exemplaire ${ex.codeBcid}` }
    } catch {
      return { type: 'exemplaire', libelle: s.exemplaire }
    }
  }
  if (s.livre) {
    try {
      const lv = await api.getLivre(extraireId(s.livre))
      return { type: 'livre', libelle: `Livre « ${lv.titre} »` }
    } catch {
      return { type: 'livre', libelle: s.livre }
    }
  }
  if (s.avis) {
    try {
      const av = await api.getAvis(extraireId(s.avis))
      const extrait = av.commentaire ? ` — « ${av.commentaire} »` : ''
      return { type: 'avis', libelle: `Avis ${av.note}/5${extrait}` }
    } catch {
      return { type: 'avis', libelle: s.avis }
    }
  }
  if (s.commentaire) {
    try {
      const cm = await api.getCommentaire(extraireId(s.commentaire))
      return { type: 'commentaire', libelle: `Commentaire « ${cm.contenu} »` }
    } catch {
      return { type: 'commentaire', libelle: s.commentaire }
    }
  }
  return { type: 'inconnu', libelle: 'Cible inconnue' }
}

export function ModerationPage() {
  const [filtre, setFiltre] = useState('en_attente')
  const [lignes, setLignes] = useState([])
  const [error, setError] = useState(null)
  const [actionId, setActionId] = useState(null)

  const charger = useCallback(async () => {
    setError(null)
    try {
      const query = filtre === 'tous' ? '' : `?statut=${filtre}`
      const data = await api.listSignalements(query)
      const liste = Array.isArray(data) ? data : data.member ?? []
      const detaille = await Promise.all(
        liste.map(async (s) => ({ ...s, cible: await decrireCible(s) })),
      )
      setLignes(detaille)
    } catch (err) {
      setError(err.message)
    }
  }, [filtre])

  useEffect(() => {
    charger().catch(() => {})
  }, [charger])

  async function agir(id, decision) {
    setActionId(id)
    setError(null)
    try {
      if (decision === 'traiter') await api.traiterSignalement(id)
      else await api.rejeterSignalement(id)
      await charger()
    } catch (err) {
      setError(err.message)
    } finally {
      setActionId(null)
    }
  }

  const nbAttente = lignes.filter((s) => s.statut === 'en_attente').length

  return (
    <div className="page">
      <h1>Modération des signalements</h1>
      <p className="subtitle">Back-office — {nbAttente} signalement(s) en attente de décision.</p>

      {error && <p className="error">{error}</p>}

      <div className="modo-tabs" role="tablist">
        {FILTRES.map((f) => (
          <button
            key={f.valeur}
            type="button"
            className={`modo-tab${filtre === f.valeur ? ' active' : ''}`}
            onClick={() => setFiltre(f.valeur)}
          >
            {f.label}
          </button>
        ))}
      </div>

      {lignes.length === 0 && !error && <p>Aucun signalement dans cette catégorie.</p>}

      <ul className="modo-list">
        {lignes.map((s) => (
          <li key={s.idSignalement} className="modo-card">
            <div className="modo-head">
              <span className={`modo-type modo-type-${s.cible.type}`}>{s.cible.type}</span>
              <span className={`modo-chip modo-chip-${s.statut}`}>
                {STATUTS[s.statut] ?? s.statut}
              </span>
            </div>
            <p className="modo-cible">{s.cible.libelle}</p>
            <p className="modo-motif">« {s.motif} »</p>
            <p className="modo-meta">
              Signalé par <strong>{s.utilisateurSignaleur?.pseudo ?? 'Anonyme'}</strong> le{' '}
              {new Date(s.dateCreation).toLocaleString('fr-FR', FORMAT_DATE)}
              {s.statut !== 'en_attente' && s.dateTraitement && (
                <>
                  {' '}· traité le{' '}
                  {new Date(s.dateTraitement).toLocaleString('fr-FR', FORMAT_DATE)} par{' '}
                  <strong>{s.utilisateurTraitant?.pseudo ?? 'inconnu'}</strong>
                </>
              )}
            </p>
            {s.statut === 'en_attente' && (
              <div className="modo-actions">
                <button
                  type="button"
                  className="btn-ok"
                  disabled={actionId === s.idSignalement}
                  onClick={() => agir(s.idSignalement, 'traiter')}
                >
                  Traiter
                </button>
                <button
                  type="button"
                  className="btn-non"
                  disabled={actionId === s.idSignalement}
                  onClick={() => agir(s.idSignalement, 'rejeter')}
                >
                  Rejeter
                </button>
              </div>
            )}
          </li>
        ))}
      </ul>
    </div>
  )
}
