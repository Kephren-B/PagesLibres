import { Link } from 'react-router-dom'

/**
 * Politique de confidentialité (RGPD).
 *
 * Chaque affirmation de cette page est vérifiable dans le code ou a été
 * mesurée, plutôt que recopiée d'un modèle :
 *
 * · l'arrondi est `App\Geo\GeoRounding::PRECISION = 3` (≈ 100 m) et la position
 *   exacte reste en base — la page dit donc « jamais publiée », pas « jamais
 *   conservée » ;
 * · les empreintes de mots de passe sont en Argon2id (`$argon2id$…` en base) ;
 * · le jeton d'authentification vit dans le stockage local, pas dans un cookie ;
 * · la suppression d'un compte laisse ses mouvements en place avec
 *   `id_utilisateur` à NULL — règle du MPD, exercée par `002_demo.sql` § 8.
 */
export function ConfidentialitePage() {
  return (
    <div className="page">
      <h1>Confidentialité et données personnelles</h1>
      <p className="subtitle">
        PagesLibres est un projet fil rouge de formation (RNCP 37873), sans finalité
        commerciale. Aucune donnée n'est vendue, louée ni transmise à des fins
        publicitaires, et le site n'utilise ni mesure d'audience ni traceur tiers.
      </p>

      <h2>Ce qui est enregistré</h2>
      <dl className="details-liste">
        <div>
          <dt>Compte</dt>
          <dd>
            Pseudo, adresse email, et l'empreinte du mot de passe — jamais le mot de
            passe lui-même. L'empreinte est calculée en Argon2id, un algorithme
            volontairement lent et coûteux en mémoire.
          </dd>
        </div>
        <div>
          <dt>Voyages</dt>
          <dd>
            Pour chaque libération ou trouvaille : la date, le type, le message
            facultatif, et la position du livre.
          </dd>
        </div>
        <div>
          <dt>Contributions</dt>
          <dd>
            Avis (note et commentaire), commentaires, signalements — et les badges
            obtenus par vos actions.
          </dd>
        </div>
        <div>
          <dt>Sur votre navigateur</dt>
          <dd>
            Un jeton de connexion, conservé dans le stockage local pour vous
            reconnaître d'une visite à l'autre. Aucun cookie publicitaire, aucun
            cookie de mesure d'audience.
          </dd>
        </div>
      </dl>

      <h2>La position d'un livre</h2>
      <p>
        C'est le point sensible du projet : une position exacte, répétée, révélerait
        les habitudes de déplacement d'un membre. La position n'est donc{' '}
        <strong>jamais publiée en clair</strong> : l'API n'expose qu'un point arrondi à
        trois décimales, soit environ 100 mètres. Un parcours ne peut pas être
        reconstitué au mètre près, et la carte ne montre que des quartiers.
      </p>
      <p>
        En toute transparence : la position transmise est aujourd'hui{' '}
        <strong>enregistrée telle quelle</strong> en base, et c'est l'exposition qui est
        arrondie. Arrondir aussi à l'écriture est une piste d'amélioration identifiée,
        qui réduirait encore la donnée conservée.
      </p>

      <h2>Suppression du compte</h2>
      <p>
        Le droit à l'effacement s'exerce sur demande auprès du responsable de
        traitement. La suppression du compte <strong>n'efface pas</strong> les
        mouvements que vous avez déclarés : ils sont conservés de façon{' '}
        <strong>anonymisée</strong>, sans plus aucun lien vers votre compte.
      </p>
      <p>
        Ce choix est assumé. Effacer ces mouvements romprait l'historique de voyage
        d'un livre, qui appartient aussi aux personnes qui l'ont trouvé après vous.
        L'anonymisation protège votre identité sans effacer l'histoire de l'objet. La
        frise et la carte affichent alors « par un membre supprimé ».
      </p>

      <h2>Services tiers appelés</h2>
      <dl className="details-liste">
        <div>
          <dt>Google Books</dt>
          <dd>
            Recherche d'un livre par ISBN, appelée directement depuis votre
            navigateur : la requête ne passe pas par nos serveurs.
          </dd>
        </div>
        <div>
          <dt>OpenStreetMap (via Leaflet)</dt>
          <dd>
            Fond de carte. Les images des cartes sont chargées depuis leurs serveurs,
            qui voient donc votre adresse IP.
          </dd>
        </div>
        <div>
          <dt>Open Library</dt>
          <dd>Photographies de couverture de certains livres.</dd>
        </div>
        <div>
          <dt>Pwned Passwords</dt>
          <dd>
            À l'inscription, le mot de passe est comparé à une base de mots de passe
            compromis. Seuls les <strong>cinq premiers caractères</strong> de
            l'empreinte SHA-1 sont transmis : le mot de passe, et même son empreinte
            complète, ne quittent jamais le serveur.
          </dd>
        </div>
      </dl>

      <h2>Vos droits</h2>
      <p>
        Vous pouvez consulter les données de votre compte depuis{' '}
        <Link to="/profil">votre profil</Link> et y corriger votre pseudo — c'est le
        seul champ que l'API accepte de modifier, avec le mot de passe. Pour toute
        autre demande — accès, rectification, effacement, opposition — écrivez au
        responsable de traitement.
      </p>
      <p>
        Aucune donnée n'est conservée au-delà de la vie du compte, hormis les
        mouvements anonymisés décrits plus haut.
      </p>
    </div>
  )
}
