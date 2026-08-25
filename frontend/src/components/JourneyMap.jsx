import L from 'leaflet'
import { MapContainer, Marker, Polyline, Popup, TileLayer } from 'react-leaflet'

const tamponIcon = L.divIcon({
  className: 'tampon-marker',
  html: '<span class="tampon-marker-dot"></span>',
  iconSize: [22, 22],
  iconAnchor: [11, 11],
  popupAnchor: [0, -12],
})

/**
 * Carte sommaire (F4/F6) : affiche des positions déjà arrondies par
 * l'API (jamais de coordonnée exacte manipulée côté front). points:
 * [{ lat, lon, label }].
 */
export function JourneyMap({ points }) {
  if (!points || points.length === 0) {
    return <p>Aucune position à afficher pour le moment.</p>
  }

  const center = [points[points.length - 1].lat, points[points.length - 1].lon]
  const polyline = points.map((p) => [p.lat, p.lon])

  return (
    <MapContainer center={center} zoom={12} scrollWheelZoom={false} style={{ height: 320, width: '100%' }}>
      <TileLayer
        attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
      />
      {polyline.length > 1 && <Polyline positions={polyline} />}
      {points.map((p, i) => (
        <Marker key={i} position={[p.lat, p.lon]} icon={tamponIcon}>
          <Popup>{p.label}</Popup>
        </Marker>
      ))}
    </MapContainer>
  )
}
