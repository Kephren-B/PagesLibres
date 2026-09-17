/**
 * Icônes de l'interface — SVG en ligne, sans dépendance externe.
 *
 * Toutes tracent en `currentColor` (elles suivent donc la charte et les deux
 * thèmes), toutes sont `aria-hidden` : elles sont décoratives, le sens est porté
 * par le texte adjacent ou par le libellé accessible de l'élément qui les porte.
 */

const commun = {
  viewBox: '0 0 24 24',
  width: 18,
  height: 18,
  fill: 'none',
  stroke: 'currentColor',
  strokeWidth: 2,
  strokeLinecap: 'round',
  strokeLinejoin: 'round',
  'aria-hidden': true,
  focusable: false,
}

export function IconeLoupe() {
  return (
    <svg {...commun}>
      <circle cx="11" cy="11" r="7" />
      <line x1="16.5" y1="16.5" x2="21" y2="21" />
    </svg>
  )
}

/**
 * Tracés des deux types de mouvement, en **chaînes**.
 *
 * Ils servent deux rendus : le composant React de la frise, et les marqueurs
 * Leaflet, qui attendent du HTML. Une seule source, donc, et des icônes qui ne
 * peuvent pas diverger entre la frise et la carte.
 *
 * Ce sont des tracés fixes écrits ici : **aucune donnée utilisateur** n'entre
 * dans cette chaîne. C'est ce qui autorise son injection dans un marqueur — la
 * même garantie que pour les étiquettes d'étape.
 */
const TRACES_MOUVEMENT = {
  // Un livre posé et une flèche qui s'en éloigne : le livre est laissé sur place.
  liberation:
    '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H11v15H6.5A2.5 2.5 0 0 0 4 20.5z"/><line x1="14" y1="8" x2="21" y2="8"/><path d="M18.5 5.5 21 8l-2.5 2.5"/>',
  // La loupe : le livre a été retrouvé.
  trouvaille:
    '<circle cx="10.5" cy="10.5" r="6.5"/><line x1="15.5" y1="15.5" x2="21" y2="21"/>',
}

/** Types connus, dans l'ordre du catalogue — sert aussi de liste blanche. */
export const TYPES_MOUVEMENT = Object.keys(TRACES_MOUVEMENT)

/**
 * Icône du type de mouvement. Décorative : le sens est porté par le libellé
 * parlé de l'étape, ou par le texte adjacent.
 */
export function IconeMouvement({ type, taille = 15 }) {
  const trace = TRACES_MOUVEMENT[type]
  if (!trace) return null

  return (
    <svg
      {...commun}
      width={taille}
      height={taille}
      dangerouslySetInnerHTML={{ __html: trace }}
    />
  )
}

/** Même icône, en chaîne cette fois : Leaflet construit ses marqueurs en HTML. */
export function marqueurMouvement(type, taille = 14) {
  if (!TRACES_MOUVEMENT[type]) return ''

  return `<svg viewBox="0 0 24 24" width="${taille}" height="${taille}" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">${TRACES_MOUVEMENT[type]}</svg>`
}

export function IconeCroix() {
  return (
    <svg {...commun} width={16} height={16}>
      <line x1="6" y1="6" x2="18" y2="18" />
      <line x1="18" y1="6" x2="6" y2="18" />
    </svg>
  )
}

export function IconeEtiquette() {
  return (
    <svg {...commun} width={16} height={16}>
      <path d="M4 4h8l8 8-7 7-9-9V4z" />
      <circle cx="8" cy="8" r="1.4" />
    </svg>
  )
}

export function IconeCible() {
  return (
    <svg {...commun} width={16} height={16}>
      <circle cx="12" cy="12" r="8" />
      <circle cx="12" cy="12" r="2.6" />
    </svg>
  )
}

export function IconeOeil() {
  return (
    <svg {...commun} width={16} height={16}>
      <path d="M2.5 12S6 6.5 12 6.5 21.5 12 21.5 12 18 17.5 12 17.5 2.5 12 2.5 12z" />
      <circle cx="12" cy="12" r="2.8" />
    </svg>
  )
}

export function IconeEpingle() {
  return (
    <svg {...commun} width={16} height={16}>
      <path d="M12 21c4.2-4.3 6.5-7.6 6.5-10.4a6.5 6.5 0 0 0-13 0C5.5 13.4 7.8 16.7 12 21z" />
      <circle cx="12" cy="10.3" r="2.3" />
    </svg>
  )
}

export function IconeChevron() {
  return (
    <svg {...commun}>
      <path d="m6 9 6 6 6-6" />
    </svg>
  )
}

export function IconePleinEcran() {
  return (
    <svg {...commun} width={16} height={16}>
      <path d="M4 9V4h5" />
      <path d="M20 9V4h-5" />
      <path d="M4 15v5h5" />
      <path d="M20 15v5h-5" />
    </svg>
  )
}

export function IconeReduire() {
  return (
    <svg {...commun} width={16} height={16}>
      <path d="M9 4v5H4" />
      <path d="M15 4v5h5" />
      <path d="M9 20v-5H4" />
      <path d="M15 20v-5h5" />
    </svg>
  )
}
