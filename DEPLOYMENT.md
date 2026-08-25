# Déploiement — PagesLibres

Ce document décrit comment **mettre en production** PagesLibres à partir des
images publiées sur Docker Hub, sans avoir à cloner ni compiler le code source.

---

## 1. Images publiées

| Image | Contenu | Lien Docker Hub |
|---|---|---|
| `kbib/pageslibres-backend` | PHP-FPM + nginx (API Symfony/API Platform), dépendances `--no-dev` | https://hub.docker.com/r/kbib/pageslibres-backend |
| `kbib/pageslibres-frontend` | nginx servant le bundle statique Vite (SPA React) | https://hub.docker.com/r/kbib/pageslibres-frontend |

Chaque tag `v*` publie l'image **tagguée `v<version>`** et **`latest`**.

---

## 2. Démarrage rapide (pour le jury)

Il suffit de récupérer **deux fichiers** : `docker-compose.prod.yml` et
`.env.prod.example` (dans le dossier racine du dépôt), puis :

```bash
cp .env.prod.example .env.prod          # puis adapter les mots de passe/secrets
docker compose -f docker-compose.prod.yml --env-file .env.prod up -d
```

Accès :

- **Front (SPA)** : http://localhost:5173
- **API** (doc API Platform) : http://localhost:8090/api
- **Base de données** : PostgreSQL + PostGIS, persistance sur un volume nommé
  (`db_data`) — les données survivent aux redémarrages.

Le schéma est initialisé automatiquement au premier démarrage : le conteneur
`mpd-init` copie le MPD validé au Jalon 3 (embarqué dans l'image backend) vers
le volume d'init SQL de PostgreSQL. **Aucun accès au code source requis.**

Arrêt / redémarrage :

```bash
docker compose -f docker-compose.prod.yml --env-file .env.prod down
docker compose -f docker-compose.prod.yml --env-file .env.prod up -d
```

---

## 3. Comptes de démonstration

Le jury peut tester sans créer de compte :

1. **Créer un membre** via le formulaire d'inscription (ou `POST /api/utilisateurs`).
2. **Promouvoir un compte en administrateur** (console uniquement, aucune route
   HTTP — cf. Jalon 5) :

```bash
docker compose -f docker-compose.prod.yml --env-file .env.prod exec backend \
    php bin/console app:user:promote-admin <email_du_membre>
```

Un admin peut alors consulter/traiter les signalements (back-office F10).

---

## 4. Sécurité — en-têtes (état Jalon 6, avec preuve)

| En-tête | État | Preuve |
|---|---|---|
| `X-Powered-By` (version PHP) | ✅ masqué | `expose_php = Off` (php.ini) ; vérifié par `curl -I` : header absent |
| `X-Content-Type-Options: nosniff` | ✅ | ajouté sur API + front ; vérifié par `curl -I` |
| `X-Frame-Options: DENY` | ✅ | ajouté sur API + front ; vérifié par `curl -I` |
| `Server` / `server_tokens` | ✅ | `server_tokens off` sur nginx API + front |
| `Content-Security-Policy` | ✅ front | tuiles OSM, Google Books, API PagesLibres autorisées ; Leaflet + recherche ISBN testés après ajout |
| `Strict-Transport-Security` | 🟡 documenté | **non activé** : requiert TLS ; à activer derrière un vrai HTTPS (voir §6) |

> Note CSP : `style-src 'unsafe-inline'` est requis par Leaflet (styles inline
> sur marqueurs/tuiles). Pas de `unsafe-eval`, pas de `unsafe-inline` sur
> `script-src`.

---

## 5. Publication des images (CI)

Sur un push de tag `v*`, le workflow `.github/workflows/release.yml` :

1. se connecte à Docker Hub (secrets `DOCKERHUB_USERNAME` / `DOCKERHUB_TOKEN`) ;
2. construit et pousse `kbib/pageslibres-backend` (multi-stage, `--no-dev`) ;
3. construit et pousse `kbib/pageslibres-frontend` (build Vite → nginx statique).

Exemple :

```bash
git tag v1.0 && git push origin v1.0
```

Le run correspondant est visible dans l'onglet Actions du dépôt
(https://github.com/Kephren-B/PagesLibres/actions).

---

## 6. Stratégie de mise en production

Les images publiées sur Docker Hub rendent le déploiement reproductible. Pour
une vraie mise en production (au-delà de la démo locale), la stratégie retenue
serait :

- **Bascule bleu/vert** : deux stacks identiques tournent côte à côte ; on
  déploie la nouvelle version sur la stack « verte », on valide, puis on
  bascule le routage (reverse-proxy) en quelques secondes. Repli immédiat en
  re-basculant sur « bleue ».
- **Rolling update** : en orchestrateur (Docker Swarm / Kubernetes), mise à
  jour conteneur par conteneur avec vérification de santé, sans interruption
  de service.
- **HTTPS** : derrière un reverse-proxy TLS (Caddy/Traefik/Nginx), activer
  alors `Strict-Transport-Security` (HSTS) — jamais sur un déploiement HTTP
  seul, sinon le navigateur bloque l'accès.
- **Migrations de schéma** : toute évolution passe par une **migration
  Doctrine**, jamais par `doctrine:schema:update` (qui détruirait l'index GiST
  et les ENUM natifs).

---

## 7. Environnements

| Env | Fichier | Source des images |
|---|---|---|
| **dev** | `docker-compose.yml` | build local (code bind-monté, hot reload) |
| **prod** | `docker-compose.prod.yml` | **Docker Hub** (`kbib/...`), aucun build local |
