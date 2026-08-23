# PagesLibres — code applicatif

Bookcrossing géolocalisé. Repo de code du projet fil rouge CDA (voir le
workspace de documentation `ProjetCDA` pour le cahier des charges, la
méthodologie et la modélisation BD complète — ce repo n'en est que
l'implémentation, à partir du MPD validé au Jalon 3).

## Structure

```
backend/    Symfony 7.4 + API Platform (PHP 8.5)
frontend/   React 19 + Vite
docker-compose.yml
```

## État actuel (Jalon 4)

- `docker-compose.yml`, Dockerfiles et config Nginx : prêts.
- `backend/docker/postgres/init/001_mpd.sql` : copie du MPD validé
  (`jalons/jalon-3-modelisation-bd/pageslibres_mpd.sql` dans le repo de
  documentation), auto-exécuté au premier démarrage du volume PostgreSQL.
- `backend/` : squelette Symfony 7.4 installé (`composer create-project
  symfony/skeleton`), avec API Platform, Doctrine ORM/DBAL, Doctrine
  Migrations, le Validator, le Serializer et `jsor/doctrine-postgis`
  (mapping du type `geometry` requis par `Exemplaire::position`, configuré
  dans `config/packages/doctrine.yaml` et
  `config/packages/jsor_doctrine_postgis.yaml`). Les 9 entités du MPD sont
  reconnues par Doctrine (`bin/console doctrine:mapping:info`) et le
  container compile (`bin/console lint:container`).
- `frontend/` : squelette React 19 + Vite généré (`pnpm create vite`),
  dépendances installées (`pnpm install`).
- **Stack validée de bout en bout** avec `docker compose up` : les 9
  entités écrivent/lisent correctement à travers les types `ENUM`
  PostgreSQL natifs (mapping ajouté dans `config/packages/doctrine.yaml`),
  le trigger `trg_maj_position_exemplaire` met bien à jour la position
  géospatiale de l'exemplaire à l'insertion d'un mouvement, et la
  suppression d'un compte anonymise ses mouvements (`SET NULL`) sans les
  effacer. Les 4 services (API, frontend, DB, Adminer) répondent.
  `doctrine:schema:validate` signale des différences de style avec le MPD
  (IDENTITY vs SERIAL, VARCHAR vs ENUM natif) — attendu et volontaire : le
  script SQL reste la source de vérité du schéma, pas
  `doctrine:schema:update`.

## Démarrage local

```bash
cp .env.example .env                    # ajuster les secrets locaux si besoin
cp backend/.env.example backend/.env    # puis générer un vrai APP_SECRET
docker compose up --build
```

- Backend : http://localhost:8090 (API Platform sur `/api`)
- Frontend : http://localhost:5173
- Adminer : http://localhost:8081
- PostgreSQL : localhost:55432

> Ports 8090/55432 choisis pour éviter des conflits courants avec d'autres
> services locaux (8080, 5432 par défaut) — à ajuster dans
> `docker-compose.yml` si besoin sur une autre machine.

## Règles de conception non négociables

- Aucune suppression en cascade sur les FK vers `utilisateur` (mouvement,
  avis, commentaire, signalement) — toujours `ON DELETE SET NULL` (RGPD).
- L'API n'expose jamais une position géographique exacte — arrondi à la
  centaine de mètres au niveau du serializer, jamais en base.
- Contraintes d'exclusivité CHECK du MPD répliquées côté validation
  Symfony (`App\Validator\ExactlyOneTarget`), en plus du CHECK PostgreSQL.
- Scan de code-barres/QR hors périmètre — ne pas l'implémenter.
