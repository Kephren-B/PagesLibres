/**
 * Chiffres du voyage d'un exemplaire (F6) — fonctions pures, testées.
 *
 * Extrait de la Figure 8 du livrable Jalon 2, panneau « Le voyage en chiffres ».
 * Tout est calculé à partir des mouvements déjà chargés par la fiche : aucune
 * donnée supplémentaire n'est demandée à l'API.
 */

const RAYON_TERRE_KM = 6371

const enRadians = (degres) => (degres * Math.PI) / 180

/** Distance orthodromique entre deux points, en kilomètres (haversine). */
function distanceEntre(a, b) {
  const dLat = enRadians(b.lat - a.lat)
  const dLon = enRadians(b.lon - a.lon)
  const lat1 = enRadians(a.lat)
  const lat2 = enRadians(b.lat)

  const h = Math.sin(dLat / 2) ** 2
    + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLon / 2) ** 2

  return 2 * RAYON_TERRE_KM * Math.asin(Math.min(1, Math.sqrt(h)))
}

/**
 * Distance parcourue, en kilomètres, en suivant les étapes dans l'ordre.
 *
 * ⚠️ Les coordonnées reçues sont **arrondies par l'API** (le projet ne laisse
 * jamais sortir une position exacte) : le total est donc une estimation, ce que
 * l'affichage assume en n'annonçant qu'une décimale.
 */
export function distanceKm(points = []) {
  let total = 0

  for (let i = 1; i < points.length; i += 1) {
    total += distanceEntre(points[i - 1], points[i])
  }

  return total
}

/** Note moyenne sur 5, ou null s'il n'y a aucun avis. */
export function noteMoyenne(avis = []) {
  if (avis.length === 0) return null

  const somme = avis.reduce((total, item) => total + Number(item.note ?? 0), 0)

  return somme / avis.length
}

/** Étoiles pleines et vides pour une note — arrondi à l'étoile, comme la maquette. */
export function etoiles(note) {
  const pleines = Math.min(5, Math.max(0, Math.round(note ?? 0)))

  return { pleines, vides: 5 - pleines }
}

/** Nombre de jours entiers écoulés depuis une date ISO. */
export function joursDepuis(dateIso, maintenant = new Date()) {
  if (!dateIso) return null

  const jours = Math.floor((maintenant.getTime() - new Date(dateIso).getTime()) / 86400000)

  return Math.max(0, jours)
}

/**
 * Mouvement qui date l'état actuel : la dernière libération pour un exemplaire
 * en circulation, la dernière trouvaille pour un exemplaire en lecture. C'est ce
 * que la maquette appelle « En circulation depuis ».
 */
export function mouvementDeReference(mouvements = [], statut) {
  const typeCherche = statut === 'trouve' ? 'trouvaille' : 'liberation'
  const candidats = mouvements.filter((mouvement) => mouvement.typeMouvement === typeCherche)

  return candidats.at(-1) ?? mouvements.at(-1) ?? null
}

export function nombreTrouvailles(mouvements = []) {
  return mouvements.filter((mouvement) => mouvement.typeMouvement === 'trouvaille').length
}

/** « 7,4 km » — virgule décimale, une seule décimale, comme la maquette. */
export function formatKm(km) {
  return `${(km ?? 0).toLocaleString('fr-FR', {
    minimumFractionDigits: 1,
    maximumFractionDigits: 1,
  })} km`
}

/** « 10 jours » — singulier géré, et « aujourd'hui » pour zéro. */
export function formatJours(jours) {
  if (jours === null) return '—'
  if (jours === 0) return "aujourd'hui"

  return jours === 1 ? '1 jour' : `${jours} jours`
}
