import { useEffect, useState } from 'react'
import { useMap } from 'react-leaflet'
import { IconePleinEcran, IconeReduire } from './Icones'

/**
 * Bouton de mise en plein écran : agrandit l'élément pointé par `conteneurRef`
 * (le cadre de la carte), pas la page entière.
 *
 * `onChangement` est appelé à chaque bascule — une carte Leaflet garde en
 * mémoire les dimensions qu'elle avait à sa création : sans `invalidateSize()`,
 * elle s'afficherait en plein écran sur la taille qu'elle occupait avant.
 */
export function BoutonPleinEcran({ conteneurRef, onChangement }) {
  const [pleinEcran, setPleinEcran] = useState(false)

  useEffect(() => {
    function surChangement() {
      setPleinEcran(document.fullscreenElement === conteneurRef.current)
      onChangement?.()
    }

    document.addEventListener('fullscreenchange', surChangement)
    return () => document.removeEventListener('fullscreenchange', surChangement)
  }, [conteneurRef, onChangement])

  // Le plein écran n'est pas disponible partout (Safari sur iPhone, navigateurs
  // anciens) : le bouton disparaît plutôt que d'échouer en silence.
  const disponible = typeof document !== 'undefined'
    && typeof document.documentElement.requestFullscreen === 'function'

  if (!disponible) return null

  const libelle = pleinEcran ? 'Quitter le plein écran' : 'Afficher la carte en plein écran'

  async function basculer() {
    try {
      if (document.fullscreenElement === conteneurRef.current) {
        await document.exitFullscreen()
      } else {
        await conteneurRef.current.requestFullscreen()
      }
    } catch {
      // Refus du navigateur (geste absent, cadre sans permission…) : la carte
      // reste telle quelle, sans message d'erreur pour un confort d'affichage.
    }
  }

  return (
    <button type="button" className="carte-plein-ecran" onClick={basculer} title={libelle}>
      {pleinEcran ? <IconeReduire /> : <IconePleinEcran />}
      <span className="sr-only">{libelle}</span>
    </button>
  )
}

/**
 * Force Leaflet à recalculer ses dimensions. Rend `null` : à placer n'importe
 * où dans le `<MapContainer>`, il n'affiche rien.
 */
export function RecalculTaille({ declencheur }) {
  const carte = useMap()

  useEffect(() => {
    carte?.invalidateSize()
  }, [carte, declencheur])

  return null
}
