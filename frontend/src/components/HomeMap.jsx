import L from 'leaflet'
import { Link } from 'react-router-dom'
import { MapContainer, Marker, Popup, TileLayer } from 'react-leaflet'

// Marqueur "tampon" : pastille encre avec anneau tampon, plutôt que le
// pin bleu par défaut de Leaflet — cohérent avec la charte du Jalon 2.
const tamponIcon = L.divIcon({
  className: 'tampon-marker',
  html: '<span class="tampon-marker-dot"></span>',
  iconSize: [22, 22],
  iconAnchor: [11, 11],
  popupAnchor: [0, -12],
})

/**
 * Carte de proximité (F4, accueil) : positions déjà arrondies par l'API.
 * markers: [{ idExemplaire, codeBcid, lat, lon, livre: { idLivre, titre, auteur } }]
 */
export function HomeMap({ center, markers }) {
  return (
    <MapContainer center={center} zoom={13} scrollWheelZoom style={{ height: 440, width: '100%' }} className="home-map">
      <TileLayer
        attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
      />
      {markers.map((m) => (
        <Marker key={m.idExemplaire} position={[m.lat, m.lon]} icon={tamponIcon}>
          <Popup>
            <div className="map-popup">
              {m.livre ? (
                <>
                  <strong className="map-popup-title">{m.livre.titre}</strong>
                  <span className="map-popup-auteur">{m.livre.auteur}</span>
                </>
              ) : (
                <strong className="map-popup-title">Livre en cours de chargement…</strong>
              )}
              <code className="stamp stamp-small">{m.codeBcid}</code>
              {m.livre && (
                <Link to={`/livres/${m.livre.idLivre}`} className="map-popup-link">
                  Voir la fiche
                </Link>
              )}
            </div>
          </Popup>
        </Marker>
      ))}
    </MapContainer>
  )
}
