import { useEffect } from 'react'
import L from 'leaflet'
import { MapContainer, Marker, TileLayer, useMap, useMapEvents } from 'react-leaflet'

const PARIS = [48.8566, 2.3522]

// Même marqueur « tampon » que les autres cartes du site.
const marqueur = L.divIcon({
  className: 'tampon-marker',
  html: '<span class="tampon-marker-dot"></span>',
  iconSize: [22, 22],
  iconAnchor: [11, 11],
})

/** Place le point : au clic sur la carte, ou en glissant le marqueur. */
function PointChoisi({ position, onChoisir }) {
  useMapEvents({
    click: (evenement) => onChoisir(evenement.latlng.lat, evenement.latlng.lng),
  })

  if (!position) return null

  return (
    <Marker
      position={position}
      icon={marqueur}
      draggable
      eventHandlers={{
        dragend: (evenement) => {
          const point = evenement.target.getLatLng()
          onChoisir(point.lat, point.lng)
        },
      }}
    />
  )
}

/**
 * Recentre seulement sur un déplacement lointain — le bouton « ma position »
 * peut envoyer le point à l'autre bout de la ville, alors qu'un clic sur la
 * carte est déjà à l'écran : le recentrage systématique donnerait l'impression
 * que la carte « saute » sous le doigt.
 */
function RecentrerSiLoin({ position }) {
  const carte = useMap()

  useEffect(() => {
    if (position && carte.getCenter().distanceTo(position) > 300) {
      carte.setView(position, Math.max(carte.getZoom(), 15))
    }
  }, [carte, position])

  return null
}

/**
 * Choix du lieu d'une libération (F3) ou d'une trouvaille (F5).
 *
 * Le cahier des charges décrit F3 comme le « placement d'un point sur la
 * carte » : on clique, et on ajuste en glissant le marqueur. Les champs
 * numériques restent à côté, dans les formulaires : une carte Leaflet n'est pas
 * utilisable au clavier, et proposer une seule saisie visuelle exclurait des
 * utilisateurs — l'alternative textuelle est une exigence d'accessibilité, pas
 * un vestige du formulaire précédent.
 */
export function ChoixPosition({ latitude, longitude, onChoisir }) {
  const lat = Number(latitude)
  const lon = Number(longitude)
  const position = latitude !== '' && longitude !== '' && Number.isFinite(lat) && Number.isFinite(lon)
    ? [lat, lon]
    : null

  return (
    <div className="choix-position">
      <MapContainer
        center={position ?? PARIS}
        zoom={position ? 16 : 12}
        scrollWheelZoom
        className="choix-position-carte"
      >
        <TileLayer
          attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
          url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
        />
        <PointChoisi position={position} onChoisir={onChoisir} />
        <RecentrerSiLoin position={position} />
      </MapContainer>
      <p className="choix-position-legende">
        {position
          ? (
            <>
              Point placé en <strong>{lat.toFixed(5)}, {lon.toFixed(5)}</strong> — cliquez ailleurs
              ou glissez le marqueur pour ajuster.
            </>
          )
          : 'Cliquez sur la carte pour placer le point.'}
      </p>
    </div>
  )
}
