import { Link } from 'react-router-dom'

export function Footer() {
  return (
    <footer className="footer">
      <div className="footer-contenu">
        <p className="footer-titre">PagesLibres</p>
        <p>
          Bookcrossing géolocalisé — projet fil rouge CDA (RNCP 37873). Un livre est
          libéré dans un lieu public, trouvé, lu, puis relâché ; son voyage se suit
          grâce au code inscrit sur l'exemplaire.
        </p>
        <p>
          Fond de carte{' '}
          <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noreferrer">
            © OpenStreetMap
          </a>{' '}
          via Leaflet ; métadonnées de livres par Google Books. Photographies de
          couverture fournies par Open Library.
        </p>
        <p>
          <strong>Positions jamais publiées en clair</strong> : l'API n'expose qu'un point
          arrondi à trois décimales, soit environ 100 mètres. Le détail des données
          conservées et des droits exercables est décrit dans le dossier de projet.
        </p>
        <p className="footer-utilitaire">
          <Link to="/">Catalogue</Link>
          {' · '}
          <Link to="/trouvaille">Déclarer une trouvaille</Link>
        </p>
      </div>
    </footer>
  )
}