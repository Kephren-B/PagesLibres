import { useEffect, useRef, useState } from 'react'
import L from 'leaflet'
import { MapContainer, Marker, Polyline, Popup, TileLayer } from 'react-leaflet'
import { BoutonPleinEcran, RecalculTaille } from './BoutonPleinEcran'
import { cadenceEtapes, mouvementReduit } from '../etapes'

// Une icône par étiquette, construite une fois : Leaflet en recrée une par
// rendu si on la fabrique à la volée.
const icones = new Map()

/**
 * Pastille numérotée (« 3 · L2 »). L'étiquette ne contient que des chiffres et
 * une lettre fixe, calculés à partir du rang du mouvement : aucune donnée
 * saisie par un utilisateur n'entre dans ce HTML.
 */
function iconeEtape(etiquette) {
  if (!icones.has(etiquette)) {
    icones.set(
      etiquette,
      L.divIcon({
        className: 'etape-marqueur',
        html: `<span class="etape-pastille">${etiquette}</span>`,
        iconSize: [52, 22],
        iconAnchor: [26, 11],
        popupAnchor: [0, -12],
      }),
    )
  }

  return icones.get(etiquette)
}

/**
 * Carte du voyage (F4/F6) : positions déjà arrondies par l'API (jamais de
 * coordonnée exacte manipulée côté front).
 *
 * points : [{ lat, lon, etiquette, libelleParle, label }]
 *
 * Les pastilles apparaissent une par une, dans l'ordre du journal, et un trait
 * unique les relie de proche en proche — puis s'efface, une fois le trajet
 * parcouru.
 */
export function JourneyMap({ points }) {
  // Préférence lue une fois : elle ne change pas en cours de session.
  const [reduit] = useState(() => mouvementReduit())
  const etapes = points ?? []
  const total = etapes.length
  const { delai, finTrait } = cadenceEtapes(total)

  const cadreRef = useRef(null)
  const [bascules, setBascules] = useState(0)
  const [visibles, setVisibles] = useState(reduit ? total : Math.min(total, 1))
  const [traitVisible, setTraitVisible] = useState(!reduit && total > 1)

  useEffect(() => {
    // Un journal d'une seule position n'a rien à révéler, et l'état initial
    // couvre déjà le cas « animations réduites ». Rien à faire dans ces cas :
    // inutile de repasser par un setState, qui déclencherait un second rendu.
    if (reduit || total <= 1) return undefined

    let rang = 1
    const avance = setInterval(() => {
      rang += 1
      setVisibles(rang)
      if (rang >= total) clearInterval(avance)
    }, delai)
    const extinction = setTimeout(() => setTraitVisible(false), finTrait)

    return () => {
      clearInterval(avance)
      clearTimeout(extinction)
    }
  }, [total, delai, finTrait, reduit])

  if (total === 0) {
    return <p>Aucune position à afficher pour le moment.</p>
  }

  const affiches = etapes.slice(0, visibles)
  const trace = affiches.map((p) => [p.lat, p.lon])
  const center = [etapes[total - 1].lat, etapes[total - 1].lon]

  return (
    <div className="carte-cadre" ref={cadreRef}>
      <MapContainer center={center} zoom={12} scrollWheelZoom={false} className="carte-journal">
        <TileLayer
          attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
          url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
        />
        <RecalculTaille declencheur={bascules} />
        {traitVisible && trace.length > 1 && (
          <Polyline positions={trace} pathOptions={{ className: 'trace-voyage' }} />
        )}
        {affiches.map((p, i) => (
          <Marker
            key={`${p.etiquette}-${i}`}
            position={[p.lat, p.lon]}
            icon={iconeEtape(p.etiquette)}
            title={p.libelleParle}
          >
            <Popup>
              <strong className="map-popup-title">{p.etiquette}</strong>
              <span className="map-popup-auteur">{p.label}</span>
            </Popup>
          </Marker>
        ))}
      </MapContainer>
      <BoutonPleinEcran conteneurRef={cadreRef} onChangement={() => setBascules((n) => n + 1)} />
    </div>
  )
}
