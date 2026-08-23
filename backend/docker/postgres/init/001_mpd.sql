-- PagesLibres — Modèle Physique de Données (PostgreSQL 18)

CREATE EXTENSION IF NOT EXISTS postgis;

CREATE TYPE role_utilisateur AS ENUM ('membre', 'admin');
CREATE TYPE statut_exemplaire AS ENUM ('en_circulation', 'trouve', 'signale', 'retire');
CREATE TYPE type_mouvement AS ENUM ('liberation', 'trouvaille');
CREATE TYPE statut_signalement AS ENUM ('en_attente', 'traite', 'rejete');

CREATE TABLE utilisateur (
  id_utilisateur     SERIAL PRIMARY KEY,
  pseudo             VARCHAR(50)  NOT NULL UNIQUE,
  email              VARCHAR(255) NOT NULL UNIQUE,
  mot_de_passe_hash  VARCHAR(255) NOT NULL,
  avatar_url         VARCHAR(255),
  bio                TEXT,
  role               role_utilisateur NOT NULL DEFAULT 'membre',
  date_inscription   TIMESTAMP NOT NULL DEFAULT now()
);

CREATE TABLE livre (
  id_livre           SERIAL PRIMARY KEY,
  isbn               VARCHAR(20) UNIQUE,
  titre              VARCHAR(255) NOT NULL,
  auteur             VARCHAR(255) NOT NULL,
  annee_publication  SMALLINT,
  categorie          VARCHAR(50) NOT NULL,
  resume             TEXT,
  couverture_url     VARCHAR(255)
);
CREATE INDEX idx_livre_titre  ON livre(titre);
CREATE INDEX idx_livre_auteur ON livre(auteur);

-- « position » = dernière localisation connue de l'exemplaire (dénormalisée
-- depuis le dernier MOUVEMENT) afin de permettre une recherche géospatiale
-- performante (F4 « livres à proximité ») sans jointure ni tri à la volée.
CREATE TABLE exemplaire (
  id_exemplaire   SERIAL PRIMARY KEY,
  id_livre        INT NOT NULL REFERENCES livre(id_livre) ON DELETE CASCADE,
  code_bcid       VARCHAR(20) NOT NULL UNIQUE,
  statut          statut_exemplaire NOT NULL DEFAULT 'en_circulation',
  position        GEOMETRY(Point, 4326),
  date_creation   TIMESTAMP NOT NULL DEFAULT now()
);
CREATE INDEX idx_exemplaire_livre    ON exemplaire(id_livre);
CREATE INDEX idx_exemplaire_position ON exemplaire USING GIST (position);

-- id_utilisateur en ON DELETE SET NULL (et nullable) : la suppression d'un
-- compte anonymise ses mouvements plutôt que de les effacer, afin de ne pas
-- rompre le journal de voyage d'un exemplaire (règle RGPD posée au Jalon 1).
CREATE TABLE mouvement (
  id_mouvement    SERIAL PRIMARY KEY,
  id_exemplaire   INT NOT NULL REFERENCES exemplaire(id_exemplaire) ON DELETE CASCADE,
  id_utilisateur  INT REFERENCES utilisateur(id_utilisateur) ON DELETE SET NULL,
  type_mouvement  type_mouvement NOT NULL,
  latitude        DECIMAL(9,6) NOT NULL,
  longitude       DECIMAL(9,6) NOT NULL,
  message         TEXT,
  date_mouvement  TIMESTAMP NOT NULL DEFAULT now()
);
CREATE INDEX idx_mouvement_exemplaire ON mouvement(id_exemplaire, date_mouvement);

-- Trigger : à chaque mouvement, la position courante de l'exemplaire est
-- recalculée à partir des coordonnées (arrondies) du mouvement inséré.
CREATE OR REPLACE FUNCTION maj_position_exemplaire() RETURNS TRIGGER AS $$
BEGIN
  UPDATE exemplaire
  SET position = ST_SetSRID(ST_MakePoint(NEW.longitude, NEW.latitude), 4326)
  WHERE id_exemplaire = NEW.id_exemplaire;
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_maj_position_exemplaire
  AFTER INSERT ON mouvement
  FOR EACH ROW EXECUTE FUNCTION maj_position_exemplaire();

CREATE TABLE avis (
  id_avis         SERIAL PRIMARY KEY,
  id_livre        INT NOT NULL REFERENCES livre(id_livre) ON DELETE CASCADE,
  id_utilisateur  INT REFERENCES utilisateur(id_utilisateur) ON DELETE SET NULL,
  note            SMALLINT NOT NULL CHECK (note BETWEEN 1 AND 5),
  commentaire     TEXT,
  date_creation   TIMESTAMP NOT NULL DEFAULT now(),
  UNIQUE (id_livre, id_utilisateur)
);

-- Un commentaire répond soit à un AVIS, soit directement à une fiche LIVRE
-- (F7 « commentaires sur les fiches livres ») — jamais les deux à la fois.
CREATE TABLE commentaire (
  id_commentaire  SERIAL PRIMARY KEY,
  id_avis         INT REFERENCES avis(id_avis) ON DELETE CASCADE,
  id_livre        INT REFERENCES livre(id_livre) ON DELETE CASCADE,
  id_utilisateur  INT REFERENCES utilisateur(id_utilisateur) ON DELETE SET NULL,
  contenu         TEXT NOT NULL,
  date_creation   TIMESTAMP NOT NULL DEFAULT now(),
  CONSTRAINT chk_commentaire_une_cible CHECK (
    (CASE WHEN id_avis IS NOT NULL THEN 1 ELSE 0 END +
     CASE WHEN id_livre IS NOT NULL THEN 1 ELSE 0 END) = 1
  )
);

CREATE TABLE badge (
  id_badge        SERIAL PRIMARY KEY,
  nom             VARCHAR(100) NOT NULL,
  description     VARCHAR(255) NOT NULL,
  icone           VARCHAR(255),
  critere_type    VARCHAR(50) NOT NULL,
  critere_valeur  INT NOT NULL
);

CREATE TABLE obtention_badge (
  id_obtention    SERIAL PRIMARY KEY,
  id_utilisateur  INT NOT NULL REFERENCES utilisateur(id_utilisateur) ON DELETE CASCADE,
  id_badge        INT NOT NULL REFERENCES badge(id_badge) ON DELETE CASCADE,
  date_obtention  TIMESTAMP NOT NULL DEFAULT now(),
  UNIQUE (id_utilisateur, id_badge)
);

CREATE TABLE signalement (
  id_signalement            SERIAL PRIMARY KEY,
  id_utilisateur_signaleur  INT REFERENCES utilisateur(id_utilisateur) ON DELETE SET NULL,
  id_utilisateur_traitant   INT REFERENCES utilisateur(id_utilisateur) ON DELETE SET NULL,
  id_livre                  INT REFERENCES livre(id_livre) ON DELETE CASCADE,
  id_exemplaire             INT REFERENCES exemplaire(id_exemplaire) ON DELETE CASCADE,
  id_avis                   INT REFERENCES avis(id_avis) ON DELETE CASCADE,
  id_commentaire            INT REFERENCES commentaire(id_commentaire) ON DELETE CASCADE,
  motif                     VARCHAR(100) NOT NULL,
  statut                    statut_signalement NOT NULL DEFAULT 'en_attente',
  date_creation             TIMESTAMP NOT NULL DEFAULT now(),
  date_traitement           TIMESTAMP,
  CONSTRAINT chk_signalement_une_cible CHECK (
    (CASE WHEN id_livre IS NOT NULL THEN 1 ELSE 0 END +
     CASE WHEN id_exemplaire IS NOT NULL THEN 1 ELSE 0 END +
     CASE WHEN id_avis IS NOT NULL THEN 1 ELSE 0 END +
     CASE WHEN id_commentaire IS NOT NULL THEN 1 ELSE 0 END) = 1
  )
);