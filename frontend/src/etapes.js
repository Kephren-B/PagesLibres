/**
 * Étiquetage des étapes d'un journal de voyage (F6).
 *
 * Chaque étape reçoit le rang de son mouvement (1 → N, ordre chronologique) et
 * le numéro de son type : « L3 » pour la 3ᵉ libération, « T2 » pour la
 * 2ᵉ trouvaille. Le libellé court — « 3 · L2 » — est celui des pastilles, à
 * l'écran comme sur la carte. Le libellé parlé sert aux lecteurs d'écran, qui
 * n'ont que faire du code « L2 ».
 */

const SIGLES = { liberation: 'L', trouvaille: 'T' }
const TYPES_PARLES = { liberation: 'libération', trouvaille: 'trouvaille' }

export function etiqueterEtapes(mouvements = []) {
  const compteurs = { liberation: 0, trouvaille: 0 }

  return mouvements.map((mouvement, index) => {
    const type = mouvement.typeMouvement
    const sigle = SIGLES[type] ?? null
    if (sigle) compteurs[type] += 1

    const numeroEtape = index + 1
    const numeroType = sigle ? compteurs[type] : null

    return {
      ...mouvement,
      numeroEtape,
      sigle,
      numeroType,
      etiquette: sigle ? `${numeroEtape} · ${sigle}${numeroType}` : String(numeroEtape),
      libelleParle: sigle
        ? `Étape ${numeroEtape}, ${TYPES_PARLES[type]} n° ${numeroType}`
        : `Étape ${numeroEtape}`,
    }
  })
}

// Durées de la révélation, en millisecondes. Elles sont aussi poussées au CSS
// sous forme de variables : les garder ici évite que la feuille de style et le
// script divergent.
export const DUREE_APPARITION = 320
export const DUREE_TRACE = 240
export const DUREE_EFFACEMENT = 400
const PAUSE_AVANT_EFFACEMENT = 700
const SEQUENCE_CIBLE = 2600
const DELAI_MIN = 70
const DELAI_MAX = 260

/**
 * Cadence de la révélation : les points apparaissent un par un, dans l'ordre,
 * puis le trait s'efface à la fin.
 *
 * La séquence vise ~2,6 s quel que soit le nombre d'étapes : à cadence fixe,
 * les 19 étapes de la vedette prendraient plus de cinq secondes, et les trois
 * étapes d'un autre journal défileraient trop vite pour être vues.
 */
export function cadenceEtapes(nombreEtapes) {
  const etapes = Math.max(nombreEtapes, 1)
  const delai = Math.min(DELAI_MAX, Math.max(DELAI_MIN, Math.round(SEQUENCE_CIBLE / etapes)))

  return {
    delai,
    finTrait: (etapes - 1) * delai + DUREE_TRACE + PAUSE_AVANT_EFFACEMENT,
  }
}

/**
 * L'utilisateur a-t-il demandé à réduire les animations (WCAG 2.3.3) ? Si oui,
 * la révélation est court-circuitée : tout est affiché d'emblée, sans trait.
 */
export function mouvementReduit() {
  return typeof window !== 'undefined'
    && typeof window.matchMedia === 'function'
    && window.matchMedia('(prefers-reduced-motion: reduce)').matches
}
