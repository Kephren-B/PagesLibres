import { useCallback, useEffect, useRef, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { api } from '../api/client'
import { urlCouverture } from '../api/googleBooks'
import { useAuth } from '../context/AuthContext'
import { JourneyMap } from '../components/JourneyMap'
import { ChoixPosition } from '../components/ChoixPosition'
import { IconeEtiquette, IconeMouvement } from '../components/Icones'
import { cadenceEtapes, etiqueterEtapes, DUREE_APPARITION, DUREE_EFFACEMENT, DUREE_TRACE } from '../etapes'
import {
  distanceKm,
  etoiles,
  formatJours,
  formatKm,
  joursDepuis,
  mouvementDeReference,
  nombreTrouvailles,
  noteMoyenne,
} from '../voyage'

// Vocabulaire aligné sur la maquette haute fidélité du Jalon 2 (Figure 8) :
// « En transit — à trouver » plutôt que « En circulation » seul.
const STATUTS = {
  en_circulation: 'En transit — à trouver',
  trouve: 'Trouvé — en lecture',
  signale: 'Signalé',
  retire: 'Retiré',
}

const ONGLETS = [
  { cle: 'journal', libelle: 'Journal de voyage' },
  { cle: 'avis', libelle: 'Avis' },
  { cle: 'details', libelle: 'Détails' },
]

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
  // Exemplaire qui vient d'être libéré : on affiche alors son BCID, généré par
  // la plateforme, pour que le membre le recopie dans le livre.
  const [exemplaireLibere, setExemplaireLibere] = useState(null)
  const [error, setError] = useState(null)
  const [latitude, setLatitude] = useState('')
  const [longitude, setLongitude] = useState('')
  const [busy, setBusy] = useState(false)
  const [avis, setAvis] = useState([])
  const [commentaires, setCommentaires] = useState({})
  const [note, setNote] = useState('5')
  const [avisTexte, setAvisTexte] = useState('')
  const [commentContenu, setCommentContenu] = useState('')
  // Une couverture peut pointer vers une image disparue : on la retire plutôt
  // que d'afficher une icône cassée.
  const [couvertureInvalide, setCouvertureInvalide] = useState(false)
  const [onglet, setOnglet] = useState('journal')
  const ongletsRef = useRef([])

  const reload = useCallback(async () => {
    const livreData = await api.getLivre(id)
    setLivre(livreData)
    setCouvertureInvalide(false)
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

  /**
   * Le journal du premier exemplaire s'ouvre d'emblée : la maquette du Jalon 2
   * (Figure 8) montre un journal affiché, pas un écran à cliquer pour le voir.
   */
  useEffect(() => {
    if (!selectedExemplaire && exemplaires.length > 0) {
      handleOuvrirJournal(exemplaires[0].idExemplaire)
    }
  }, [exemplaires, selectedExemplaire])

  /** Navigation clavier entre onglets — attendue du motif ARIA « tabs ». */
  function naviguerOnglets(evenement, index) {
    const deplacement = { ArrowRight: 1, ArrowLeft: -1 }[evenement.key]
    let cible = null

    if (deplacement) {
      cible = (index + deplacement + ONGLETS.length) % ONGLETS.length
    } else if (evenement.key === 'Home') {
      cible = 0
    } else if (evenement.key === 'End') {
      cible = ONGLETS.length - 1
    }

    if (cible === null) return
    evenement.preventDefault()
    setOnglet(ONGLETS[cible].cle)
    ongletsRef.current[cible]?.focus()
  }

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
      () => setError(
        'Position automatique indisponible : placez le point sur la carte, ou saisissez les coordonnées.'
      )
    )
  }

  async function handleLibererExemplaire(event) {
    event.preventDefault()
    setError(null)
    setBusy(true)
    try {
      const exemplaire = await api.createExemplaire({
        livre: `/api/livres/${id}`,
      })
      await api.createMouvement({
        exemplaire: `/api/exemplaires/${exemplaire.idExemplaire}`,
        typeMouvement: 'liberation',
        latitude,
        longitude,
      })
      setExemplaireLibere(exemplaire)
      setLatitude('')
      setLongitude('')
      await reload()
      // Le nouvel exemplaire devient celui qu'on regarde : son journal s'ouvre,
      // avec sa première étape.
      setOnglet('journal')
      await handleOuvrirJournal(exemplaire.idExemplaire)
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

  // Les étapes sont étiquetées une seule fois : la frise et la carte partagent
  // donc la même numérotation (« 3 · L2 »), et une seule vérité.
  const etapes = selectedExemplaire ? etiqueterEtapes(selectedExemplaire.mouvements) : []
  const cadence = cadenceEtapes(etapes.length)
  const journeyPoints = etapes.map((etape) => ({
    lat: etape.positionArrondie.latitude,
    lon: etape.positionArrondie.longitude,
    type: etape.typeMouvement,
    numeroEtape: etape.numeroEtape,
    etiquette: etape.etiquette,
    libelleParle: etape.libelleParle,
    label: `${LABELS_MOUVEMENT[etape.typeMouvement] ?? etape.typeMouvement} — ${new Date(etape.dateMouvement).toLocaleString('fr-FR')}`,
  }))

  const couverture = livre.couvertureUrl ? urlCouverture(livre.couvertureUrl) : null

  // Chiffres du panneau « Le voyage en chiffres » (Figure 8 du Jalon 2).
  const moyenne = noteMoyenne(avis)
  const { pleines, vides } = etoiles(moyenne)
  const distance = distanceKm(journeyPoints)
  const reference = selectedExemplaire
    ? mouvementDeReference(selectedExemplaire.mouvements, selectedExemplaire.statut)
    : null
  const enLecture = selectedExemplaire?.statut === 'trouve'

  return (
    <div className="page">
      <nav className="fil-ariane" aria-label="Fil d'Ariane">
        <Link to="/">Accueil</Link>
        <span className="fil-ariane-sep" aria-hidden="true">›</span>
        {livre.categorie && (
          <>
            <Link to={`/?categorie=${encodeURIComponent(livre.categorie)}`}>{livre.categorie}</Link>
            <span className="fil-ariane-sep" aria-hidden="true">›</span>
          </>
        )}
        <span aria-current="page">{livre.titre}</span>
      </nav>

      <div className="livre-entete">
        {couverture && !couvertureInvalide && (
          <img
            className="livre-couverture"
            src={couverture}
            alt={`Couverture de « ${livre.titre} »`}
            loading="lazy"
            onError={() => setCouvertureInvalide(true)}
          />
        )}
        <div className="livre-entete-texte">
          <h1>{livre.titre}</h1>
          <p className="subtitle">{livre.auteur} — {livre.categorie}{livre.anneePublication ? ` (${livre.anneePublication})` : ''}</p>
          {moyenne !== null && (
            <p className="livre-note">
              <span className="livre-note-etoiles" aria-hidden="true">{'★'.repeat(pleines)}{'☆'.repeat(vides)}</span>
              <span className="sr-only">{`Note moyenne ${moyenne.toFixed(1).replace('.', ',')} sur 5, sur ${avis.length} avis`}</span>
              <span className="livre-note-valeur" aria-hidden="true">{`${moyenne.toFixed(1).replace('.', ',')}/5 · ${avis.length} avis`}</span>
            </p>
          )}
          {livre.resume && <p>{livre.resume}</p>}
        </div>
      </div>

      {error && <p className="error">{error}</p>}

      <h2>Exemplaires</h2>
      <ul className="exemplaire-list">
        {exemplaires.map((exemplaire) => (
          <li key={exemplaire.idExemplaire} className="exemplaire-carte">
            <div className="exemplaire-code">
              <code className="stamp exemplaire-tampon">{exemplaire.codeBcid}</code>
              <span className="exemplaire-code-legende">Code exemplaire</span>
            </div>
            <p className="exemplaire-statut">
              <span className="statut-point" data-statut={exemplaire.statut} aria-hidden="true" />
              <span className="exemplaire-statut-legende">Statut actuel</span>
              <strong>{STATUTS[exemplaire.statut] ?? exemplaire.statut}</strong>
            </p>
            <div className="exemplaire-actions">
              <button
                type="button"
                className="bouton-journal"
                aria-pressed={selectedExemplaire?.idExemplaire === exemplaire.idExemplaire}
                onClick={() => {
                  setOnglet('journal')
                  handleOuvrirJournal(exemplaire.idExemplaire)
                }}
              >
                Voir le journal de voyage
              </button>
              <Link
                className="bouton-trouvaille"
                to={`/trouvaille?code=${encodeURIComponent(exemplaire.codeBcid)}`}
              >
                <IconeEtiquette />
                J'ai trouvé ce livre
              </Link>
            </div>
          </li>
        ))}
        {exemplaires.length === 0 && <li>Aucun exemplaire libéré pour l'instant.</li>}
      </ul>

      <div className="onglets">
        <div className="onglets-barre" role="tablist" aria-label="Contenu de la fiche">
          {ONGLETS.map((item, index) => (
            <button
              key={item.cle}
              type="button"
              className="onglet-bouton"
              role="tab"
              id={`onglet-${item.cle}`}
              aria-selected={onglet === item.cle}
              aria-controls={`panneau-${item.cle}`}
              tabIndex={onglet === item.cle ? 0 : -1}
              ref={(element) => {
                ongletsRef.current[index] = element
              }}
              onClick={() => setOnglet(item.cle)}
              onKeyDown={(evenement) => naviguerOnglets(evenement, index)}
            >
              {item.libelle}
              {item.cle === 'avis' && avis.length > 0 ? ` (${avis.length})` : ''}
            </button>
          ))}
        </div>

        <section
          id="panneau-journal"
          role="tabpanel"
          aria-labelledby="onglet-journal"
          hidden={onglet !== 'journal'}
          className="onglet-panneau"
        >
          {selectedExemplaire ? (
            <div className="voyage">
              <div className="journal">
          <JourneyMap key={selectedExemplaire.idExemplaire} points={journeyPoints} />
          <ol
            className="timeline"
            style={{
              '--delai': `${cadence.delai}ms`,
              '--fin-trait': `${cadence.finTrait}ms`,
              '--duree-apparition': `${DUREE_APPARITION}ms`,
              '--duree-trace': `${DUREE_TRACE}ms`,
              '--duree-effacement': `${DUREE_EFFACEMENT}ms`,
            }}
          >
            {etapes.map((etape, i) => (
              <li key={etape.idMouvement ?? i} className="timeline-step" style={{ '--rang': i }}>
                <span className="timeline-trait" aria-hidden="true" />
                <span className="timeline-pastille" data-type={etape.typeMouvement} aria-hidden="true">
                  <IconeMouvement type={etape.typeMouvement} />
                  {etape.numeroEtape}
                </span>
                <div className="timeline-content">
                  <span className="timeline-label">
                    <span className="sr-only">{etape.libelleParle} — </span>
                    {LABELS_MOUVEMENT[etape.typeMouvement] ?? etape.typeMouvement}
                    {etape.utilisateur?.pseudo && <> par {etape.utilisateur.pseudo}</>}
                  </span>
                  <span className="timeline-date">{new Date(etape.dateMouvement).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })}</span>
                  {etape.message && <p className="timeline-message">« {etape.message} »</p>}
                </div>
              </li>
            ))}
            {selectedExemplaire.statut === 'trouve' && (
              <li className="timeline-step timeline-step-pending" style={{ '--rang': etapes.length }}>
                <span className="timeline-pastille timeline-pastille-pending" aria-hidden="true">…</span>
                <div className="timeline-content">
                  <span className="timeline-label">En attente d'une nouvelle libération…</span>
                </div>
              </li>
            )}
            {selectedExemplaire.statut === 'en_circulation' && (
              <li className="timeline-step timeline-step-pending" style={{ '--rang': etapes.length }}>
                <span className="timeline-pastille timeline-pastille-pending" aria-hidden="true">…</span>
                <div className="timeline-content">
                  <span className="timeline-label">En attente d'une nouvelle trouvaille…</span>
                </div>
              </li>
            )}
          </ol>
            </div>

            {/* Le voyage en chiffres — panneau de la Figure 8 du Jalon 2. */}
            <aside className="voyage-chiffres">
              <h4>Le voyage en chiffres</h4>
              <dl>
                <div className="voyage-chiffre">
                  <dt>Distance parcourue</dt>
                  <dd>{formatKm(distance)}</dd>
                </div>
                <div className="voyage-chiffre">
                  <dt>Nombre de trouvailles</dt>
                  <dd>{nombreTrouvailles(selectedExemplaire.mouvements)}</dd>
                </div>
                <div className="voyage-chiffre">
                  <dt>{enLecture ? 'En lecture depuis' : 'En circulation depuis'}</dt>
                  <dd>{formatJours(joursDepuis(reference?.dateMouvement))}</dd>
                </div>
              </dl>
            </aside>
          </div>
          ) : (
            <p>Aucun journal à afficher : ce livre n'a pas encore été libéré.</p>
          )}
        </section>

        <section
          id="panneau-avis"
          role="tabpanel"
          aria-labelledby="onglet-avis"
          hidden={onglet !== 'avis'}
          className="onglet-panneau"
        >
          <h3>Avis des lecteurs</h3>
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
          <h4>Donner mon avis</h4>
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
        </section>

        <section
          id="panneau-details"
          role="tabpanel"
          aria-labelledby="onglet-details"
          hidden={onglet !== 'details'}
          className="onglet-panneau"
        >
          <h3>Détails</h3>
          <dl className="details-liste">
            <div><dt>ISBN</dt><dd>{livre.isbn ?? '—'}</dd></div>
            <div><dt>Catégorie</dt><dd>{livre.categorie || '—'}</dd></div>
            <div><dt>Année de publication</dt><dd>{livre.anneePublication ?? '—'}</dd></div>
            <div><dt>Exemplaires enregistrés</dt><dd>{exemplaires.length}</dd></div>
            <div>
              <dt>Note moyenne</dt>
              <dd>{moyenne !== null ? `${moyenne.toFixed(1).replace('.', ',')} / 5` : '—'}</dd>
            </div>
          </dl>
        </section>
      </div>

      {isAuthenticated && (
        <div className="page-form">
          <h2>Libérer un nouvel exemplaire</h2>
          <p className="subtitle">
            Le code BCID est attribué par PagesLibres : il s'affiche une fois l'exemplaire
            enregistré, à recopier dans le livre.
          </p>
          <form onSubmit={handleLibererExemplaire}>
            <ChoixPosition
              latitude={latitude}
              longitude={longitude}
              onChoisir={(lat, lon) => {
                setLatitude(String(lat))
                setLongitude(String(lon))
              }}
            />
            <button type="button" onClick={useMaPosition}>Utiliser ma position actuelle</button>
            <label>
              Latitude
              <input value={latitude} onChange={(e) => setLatitude(e.target.value)} required />
            </label>
            <label>
              Longitude
              <input value={longitude} onChange={(e) => setLongitude(e.target.value)} required />
            </label>
            <button type="submit" disabled={busy}>{busy ? 'Libération…' : 'Libérer cet exemplaire'}</button>
          </form>
          {exemplaireLibere && (
            <p className="success">
              Exemplaire enregistré et libéré. Son code BCID est{' '}
              <code className="stamp">{exemplaireLibere.codeBcid}</code> — recopiez-le dans le
              livre avant de le déposer : c'est lui qui permettra au prochain lecteur de
              raconter la suite.
            </p>
          )}
        </div>
      )}
    </div>
  )
}
