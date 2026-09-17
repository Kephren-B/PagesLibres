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
