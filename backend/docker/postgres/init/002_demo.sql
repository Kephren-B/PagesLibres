-- ============================================================================
-- PagesLibres — JEU DE DÉMONSTRATION (développement)
-- ============================================================================
--
-- Chargé automatiquement par `docker-compose.yml` (dev) : `init/` est monté
-- dans /docker-entrypoint-initdb.d. Ce fichier n'atteint JAMAIS la CI ni la
-- production :
--   · la CI charge uniquement `001_mpd.sql` (chemin explicite dans ci.yml) ;
--   · le compose de prod ne copie que `001_mpd.sql` dans son volume d'init.
--
-- Contenu : 4 comptes de démonstration, 10 livres, 12 exemplaires et leurs
-- journaux de voyage du 12/05/2026 au 16/09/2026, plus 2 avis, 1 commentaire,
-- 4 signalements en attente (back-office F10) et 4 badges déjà obtenus (F9).
--
-- Idempotent : chaque insertion est gardée par un NOT EXISTS, le fichier peut
-- être rejoué sans créer de doublon. Les comptes de démonstration partagent le
-- mot de passe public « DemoPagesLibres2026! » (documenté dans DEMARRAGE.md
-- § 9) ; les empreintes Argon2id sont figées ici pour que le jeu soit
-- reproductible après `docker compose down -v`.
--
-- À exécuter APRÈS 001_mpd.sql (schéma). Les 5 badges (F9) sont créés par la
-- migration de données : `bin/console doctrine:migrations:migrate`.
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 1. Comptes de démonstration
-- ---------------------------------------------------------------------------
INSERT INTO utilisateur (pseudo, email, mot_de_passe_hash, bio, role, date_inscription)
SELECT v.pseudo, v.email, v.hash, v.bio, v.role::role_utilisateur, v.dt::timestamp
FROM (VALUES
  ('demo_lecteur', 'membre@demo.pageslibres',
   '$argon2id$v=19$m=65536,t=4,p=1$IBDGHbTS3DbFG+7cyofyag$JdlBcruflxoskLc86qtY+ZNxyIMNWt/bJDlaHtHxQXA',
   'Lecteur de banlieue, libère surtout en gare.', 'membre', '2026-08-25 09:12:00'),
  ('demo_admin', 'admin@demo.pageslibres',
   '$argon2id$v=19$m=65536,t=4,p=1$IBDGHbTS3DbFG+7cyofyag$JdlBcruflxoskLc86qtY+ZNxyIMNWt/bJDlaHtHxQXA',
   'Compte de modération.', 'admin', '2026-08-25 09:12:00'),
  ('marie', 'marie@demo.pageslibres',
   '$argon2id$v=19$m=65536,t=4,p=1$Tz+mnKImjWTF4WdLppadHA$lKk/xm/2M2cw5pvArX2prR00VUit7TJHKnbZPpfKlW4',
   'Bookcrosseuse du dimanche, plutôt parcs et jardins.', 'membre', '2026-09-16 08:04:00'),
  ('theo', 'theo@demo.pageslibres',
   '$argon2id$v=19$m=65536,t=4,p=1$1Uxvrzj1a5sLuT6VYmLRRQ$hkxGT3hTAUurwsvvfaDZV3wYlRn4LQwrWOHxsDvy+94',
   'Trajets quotidiens en train, beaucoup de libérations en gare.', 'membre', '2026-09-16 08:06:00')
) AS v(pseudo, email, hash, bio, role, dt)
WHERE NOT EXISTS (SELECT 1 FROM utilisateur u WHERE u.email = v.email);

-- ---------------------------------------------------------------------------
-- 2. Catalogue — 10 livres
-- ---------------------------------------------------------------------------
INSERT INTO livre (isbn, titre, auteur, annee_publication, categorie, resume)
SELECT v.isbn, v.titre, v.auteur, v.annee::smallint, v.categorie, v.resume
FROM (VALUES
  ('8655466954091', 'Le Comte de Monte-Cristo', 'Alexandre Dumas', 1844, 'Roman',
   'Edmond Dantès, trahi le jour de ses fiançailles, construit sa vengeance depuis le château d''If.'),
  ('9782413016632', 'Les Légendaires Saga Tome 1', 'Patrick Sobral', 2020, 'Bande dessinée',
   'Premier tome de la saga : le monde d''Alysia et ses héros transformés.'),
  (NULL, 'Les Racines du ciel', 'Romain Gary', 1956, 'Roman',
   'Prix Goncourt 1956 : la défense des éléphants d''Afrique, entre aventure et Engagement.'),
  ('9782070612758', 'Le Petit Prince', 'Antoine de Saint-Exupéry', 1943, 'Conte',
   'Un aviateur en panne dans le désert rencontre un enfant venu d''une autre planète.'),
  ('9782070360024', 'L''Étranger', 'Albert Camus', 1942, 'Roman',
   'Meursault, un homme indifférent au monde, commet un meurtre sous le soleil d''Alger.'),
  ('9782253096337', 'Notre-Dame de Paris', 'Victor Hugo', 1831, 'Classique',
   'Quasimodo, Esmeralda et la cathédrale : le Paris du XVe siècle.'),
  ('9782012101333', 'Astérix le Gaulois', 'René Goscinny', 1961, 'Bande dessinée',
   'Le village gaulois résiste encore et toujours à l''envahisseur.'),
  ('9782266320481', 'Dune', 'Frank Herbert', 1965, 'Science-fiction',
   'Arrakis, l''épice, et la destinée de Paul Atréides.'),
  ('9782253006268', 'Le Mystère de la chambre jaune', 'Gaston Leroux', 1907, 'Policier',
   'Une chambre close, une victime, et Rouletabille pour démêler l''affaire.'),
  ('9782253006244', 'Le Grand Meaulnes', 'Alain-Fournier', 1913, 'Roman',
   'La rencontre d''Augustin Meaulnes et du domaine perdu, entre rêve et nostalgie.')
) AS v(isbn, titre, auteur, annee, categorie, resume)
WHERE NOT EXISTS (SELECT 1 FROM livre l WHERE l.titre = v.titre);

-- ---------------------------------------------------------------------------
-- 3. Exemplaires et journaux de voyage
--    Un bloc par exemplaire : l'exemplaire n'est créé que s'il n'existe pas
--    (clé = code_bcid), et ses mouvements sont insérés dans la même requête.
--    Les dates vont de mai à septembre 2026, dans l'ordre chronologique des
--    messages.
-- ---------------------------------------------------------------------------

-- Le Comte de Monte-Cristo — exemplaire 1 : le journal complet (5 étapes).
WITH nouvel AS (
  INSERT INTO exemplaire (id_livre, code_bcid, statut, date_creation)
  SELECT l.id_livre, 'PL-7F8KT-FR', 'en_circulation', '2026-05-12'
  FROM livre l
  WHERE l.titre = 'Le Comte de Monte-Cristo'
    AND NOT EXISTS (SELECT 1 FROM exemplaire e WHERE e.code_bcid = 'PL-7F8KT-FR')
  RETURNING id_exemplaire
)
INSERT INTO mouvement (id_exemplaire, id_utilisateur, type_mouvement, latitude, longitude, message, date_mouvement)
SELECT n.id_exemplaire, u.id_utilisateur, m.type::type_mouvement, m.lat, m.lon, m.msg, m.dt::timestamp
FROM nouvel n
CROSS JOIN (VALUES
  ('liberation', 48.844300, 2.374300, 'Libéré près de la gare de Lyon, sur un banc du hall.', '2026-05-12 09:20:00', 'marie@demo.pageslibres'),
  ('trouvaille', 48.846200, 2.337200, 'Trouvé au jardin du Luxembourg, sous le kiosque à musique.', '2026-06-03 18:40:00', 'theo@demo.pageslibres'),
  ('liberation', 48.804900, 2.120400, 'Relâché à Versailles, dans les jardins du château.', '2026-06-24 11:05:00', 'theo@demo.pageslibres'),
  ('trouvaille', 48.862000, 2.240000, 'Retrouvé au bois de Boulogne, abandonné sur un banc.', '2026-07-22 16:15:00', 'marie@demo.pageslibres'),
  ('liberation', 48.822500, 2.337500, 'Relibéré au parc Montsouris, à l''ombre d''un cèdre.', '2026-09-16 08:30:00', 'marie@demo.pageslibres')
) AS m(type, lat, lon, msg, dt, email)
JOIN utilisateur u ON u.email = m.email;

-- Le Comte de Monte-Cristo — exemplaire 2 : montre qu'un même livre a
-- plusieurs trajectoires indépendantes (LIVRE ≠ EXEMPLAIRE).
WITH nouvel AS (
  INSERT INTO exemplaire (id_livre, code_bcid, statut, date_creation)
  SELECT l.id_livre, 'PL-4B8YT-FR', 'trouve', '2026-05-30'
  FROM livre l
  WHERE l.titre = 'Le Comte de Monte-Cristo'
    AND NOT EXISTS (SELECT 1 FROM exemplaire e WHERE e.code_bcid = 'PL-4B8YT-FR')
  RETURNING id_exemplaire
)
INSERT INTO mouvement (id_exemplaire, id_utilisateur, type_mouvement, latitude, longitude, message, date_mouvement)
SELECT n.id_exemplaire, u.id_utilisateur, m.type::type_mouvement, m.lat, m.lon, m.msg, m.dt::timestamp
FROM nouvel n
CROSS JOIN (VALUES
  ('liberation', 48.835200, 2.240100, 'Libéré sur le parvis de la mairie, à Boulogne.', '2026-05-30 07:50:00', 'membre@demo.pageslibres'),
  ('trouvaille', 48.812300, 2.238500, 'Trouvé sur un banc de la forêt de Meudon.', '2026-06-14 20:10:00', 'marie@demo.pageslibres')
) AS m(type, lat, lon, msg, dt, email)
JOIN utilisateur u ON u.email = m.email;

-- Les Légendaires Saga Tome 1
WITH nouvel AS (
  INSERT INTO exemplaire (id_livre, code_bcid, statut, date_creation)
  SELECT l.id_livre, 'PL-2M4QW-FR', 'en_circulation', '2026-09-04'
  FROM livre l
  WHERE l.titre = 'Les Légendaires Saga Tome 1'
    AND NOT EXISTS (SELECT 1 FROM exemplaire e WHERE e.code_bcid = 'PL-2M4QW-FR')
  RETURNING id_exemplaire
)
INSERT INTO mouvement (id_exemplaire, id_utilisateur, type_mouvement, latitude, longitude, message, date_mouvement)
SELECT n.id_exemplaire, u.id_utilisateur, m.type::type_mouvement, m.lat, m.lon, m.msg, m.dt::timestamp
FROM nouvel n
CROSS JOIN (VALUES
  ('liberation', 48.886700, 2.343100, 'Libéré près du Sacré-Cœur, sur un banc.', '2026-09-04 07:11:49', 'membre@demo.pageslibres')
) AS m(type, lat, lon, msg, dt, email)
JOIN utilisateur u ON u.email = m.email;

-- Les Racines du ciel — exemplaire 1 (trouvé, laissé à l'abandon : il porte un
-- signalement en attente pour le back-office).
WITH nouvel AS (
  INSERT INTO exemplaire (id_livre, code_bcid, statut, date_creation)
  SELECT l.id_livre, 'PL-7X29K-FR', 'trouve', '2026-08-25'
  FROM livre l
  WHERE l.titre = 'Les Racines du ciel'
    AND NOT EXISTS (SELECT 1 FROM exemplaire e WHERE e.code_bcid = 'PL-7X29K-FR')
  RETURNING id_exemplaire
)
INSERT INTO mouvement (id_exemplaire, id_utilisateur, type_mouvement, latitude, longitude, message, date_mouvement)
SELECT n.id_exemplaire, u.id_utilisateur, m.type::type_mouvement, m.lat, m.lon, m.msg, m.dt::timestamp
FROM nouvel n
CROSS JOIN (VALUES
  ('liberation', 48.858370, 2.294481, 'Libéré sur les quais, près des bouquinistes.', '2026-08-25 10:49:34', 'theo@demo.pageslibres'),
  ('trouvaille', 48.860000, 2.300000, 'Trouvé sur un banc au jardin des Tuileries.', '2026-08-27 14:20:00', 'marie@demo.pageslibres')
) AS m(type, lat, lon, msg, dt, email)
JOIN utilisateur u ON u.email = m.email;

-- Les Racines du ciel — exemplaire 2 (en circulation : le livre satisfait donc
-- la règle « au moins un exemplaire en circulation »).
WITH nouvel AS (
  INSERT INTO exemplaire (id_livre, code_bcid, statut, date_creation)
  SELECT l.id_livre, 'PL-5RJ2N-FR', 'en_circulation', '2026-05-21'
  FROM livre l
  WHERE l.titre = 'Les Racines du ciel'
    AND NOT EXISTS (SELECT 1 FROM exemplaire e WHERE e.code_bcid = 'PL-5RJ2N-FR')
  RETURNING id_exemplaire
)
INSERT INTO mouvement (id_exemplaire, id_utilisateur, type_mouvement, latitude, longitude, message, date_mouvement)
SELECT n.id_exemplaire, u.id_utilisateur, m.type::type_mouvement, m.lat, m.lon, m.msg, m.dt::timestamp
FROM nouvel n
CROSS JOIN (VALUES
  ('liberation', 48.849700, 2.355300, 'Libéré sur les quais, devant les bouquinistes.', '2026-05-21 12:40:00', 'theo@demo.pageslibres'),
  ('trouvaille', 48.774200, 2.301000, 'Trouvé au parc de Sceaux, près du bassin.', '2026-06-19 09:15:00', 'marie@demo.pageslibres'),
  ('liberation', 48.843700, 2.359700, 'Relibéré au jardin des Plantes, devant la ménagerie.', '2026-07-15 17:30:00', 'marie@demo.pageslibres')
) AS m(type, lat, lon, msg, dt, email)
JOIN utilisateur u ON u.email = m.email;

-- Le Petit Prince
WITH nouvel AS (
  INSERT INTO exemplaire (id_livre, code_bcid, statut, date_creation)
  SELECT l.id_livre, 'PL-6K3VD-FR', 'en_circulation', '2026-06-07'
  FROM livre l
  WHERE l.titre = 'Le Petit Prince'
    AND NOT EXISTS (SELECT 1 FROM exemplaire e WHERE e.code_bcid = 'PL-6K3VD-FR')
  RETURNING id_exemplaire
)
INSERT INTO mouvement (id_exemplaire, id_utilisateur, type_mouvement, latitude, longitude, message, date_mouvement)
SELECT n.id_exemplaire, u.id_utilisateur, m.type::type_mouvement, m.lat, m.lon, m.msg, m.dt::timestamp
FROM nouvel n
CROSS JOIN (VALUES
  ('liberation', 48.863500, 2.327000, 'Libéré aux Tuileries, près du bassin rond.', '2026-06-07 15:20:00', 'marie@demo.pageslibres'),
  ('trouvaille', 48.898200, 2.093400, 'Trouvé sur la terrasse de Saint-Germain-en-Laye.', '2026-08-28 11:45:00', 'theo@demo.pageslibres'),
  ('liberation', 48.896600, 2.090300, 'Relibéré près du château, sur le parvis.', '2026-08-29 09:05:00', 'theo@demo.pageslibres')
) AS m(type, lat, lon, msg, dt, email)
JOIN utilisateur u ON u.email = m.email;

-- L'Étranger
WITH nouvel AS (
  INSERT INTO exemplaire (id_livre, code_bcid, statut, date_creation)
  SELECT l.id_livre, 'PL-9F4WM-FR', 'en_circulation', '2026-05-19'
  FROM livre l
  WHERE l.titre = 'L''Étranger'
    AND NOT EXISTS (SELECT 1 FROM exemplaire e WHERE e.code_bcid = 'PL-9F4WM-FR')
  RETURNING id_exemplaire
)
INSERT INTO mouvement (id_exemplaire, id_utilisateur, type_mouvement, latitude, longitude, message, date_mouvement)
SELECT n.id_exemplaire, u.id_utilisateur, m.type::type_mouvement, m.lat, m.lon, m.msg, m.dt::timestamp
FROM nouvel n
CROSS JOIN (VALUES
  ('liberation', 48.871000, 2.366000, 'Libéré sur les berges du canal Saint-Martin.', '2026-05-19 08:10:00', 'marie@demo.pageslibres')
) AS m(type, lat, lon, msg, dt, email)
JOIN utilisateur u ON u.email = m.email;

-- Notre-Dame de Paris
WITH nouvel AS (
  INSERT INTO exemplaire (id_livre, code_bcid, statut, date_creation)
  SELECT l.id_livre, 'PL-3H8QZ-FR', 'en_circulation', '2026-07-02'
  FROM livre l
  WHERE l.titre = 'Notre-Dame de Paris'
    AND NOT EXISTS (SELECT 1 FROM exemplaire e WHERE e.code_bcid = 'PL-3H8QZ-FR')
  RETURNING id_exemplaire
)
INSERT INTO mouvement (id_exemplaire, id_utilisateur, type_mouvement, latitude, longitude, message, date_mouvement)
SELECT n.id_exemplaire, u.id_utilisateur, m.type::type_mouvement, m.lat, m.lon, m.msg, m.dt::timestamp
FROM nouvel n
CROSS JOIN (VALUES
  ('liberation', 48.853000, 2.349900, 'Libéré sur le parvis, face à la cathédrale.', '2026-07-02 10:30:00', 'theo@demo.pageslibres')
) AS m(type, lat, lon, msg, dt, email)
JOIN utilisateur u ON u.email = m.email;

-- Astérix le Gaulois
WITH nouvel AS (
  INSERT INTO exemplaire (id_livre, code_bcid, statut, date_creation)
  SELECT l.id_livre, 'PL-8N5TC-FR', 'en_circulation', '2026-08-21'
  FROM livre l
  WHERE l.titre = 'Astérix le Gaulois'
    AND NOT EXISTS (SELECT 1 FROM exemplaire e WHERE e.code_bcid = 'PL-8N5TC-FR')
  RETURNING id_exemplaire
)
INSERT INTO mouvement (id_exemplaire, id_utilisateur, type_mouvement, latitude, longitude, message, date_mouvement)
SELECT n.id_exemplaire, u.id_utilisateur, m.type::type_mouvement, m.lat, m.lon, m.msg, m.dt::timestamp
FROM nouvel n
CROSS JOIN (VALUES
  ('liberation', 48.880900, 2.382100, 'Libéré aux Buttes-Chaumont, près du temple de la Sibylle.', '2026-08-21 18:05:00', 'marie@demo.pageslibres')
) AS m(type, lat, lon, msg, dt, email)
JOIN utilisateur u ON u.email = m.email;

-- Dune — un livre qui a été trouvé puis relibéré.
WITH nouvel AS (
  INSERT INTO exemplaire (id_livre, code_bcid, statut, date_creation)
  SELECT l.id_livre, 'PL-1V6HJ-FR', 'en_circulation', '2026-05-28'
  FROM livre l
  WHERE l.titre = 'Dune'
    AND NOT EXISTS (SELECT 1 FROM exemplaire e WHERE e.code_bcid = 'PL-1V6HJ-FR')
  RETURNING id_exemplaire
)
INSERT INTO mouvement (id_exemplaire, id_utilisateur, type_mouvement, latitude, longitude, message, date_mouvement)
SELECT n.id_exemplaire, u.id_utilisateur, m.type::type_mouvement, m.lat, m.lon, m.msg, m.dt::timestamp
FROM nouvel n
CROSS JOIN (VALUES
  ('liberation', 48.833000, 2.376000, 'Libéré devant la BnF François-Mitterrand.', '2026-05-28 13:15:00', 'theo@demo.pageslibres'),
  ('trouvaille', 48.833000, 2.433000, 'Trouvé au bois de Vincennes, sur un muret.', '2026-06-12 19:25:00', 'marie@demo.pageslibres'),
  ('liberation', 48.838000, 2.418000, 'Relibéré au bord du lac Daumesnil.', '2026-06-14 10:40:00', 'marie@demo.pageslibres')
) AS m(type, lat, lon, msg, dt, email)
JOIN utilisateur u ON u.email = m.email;

-- Le Mystère de la chambre jaune
WITH nouvel AS (
  INSERT INTO exemplaire (id_livre, code_bcid, statut, date_creation)
  SELECT l.id_livre, 'PL-9D3KF-FR', 'en_circulation', '2026-09-11'
  FROM livre l
  WHERE l.titre = 'Le Mystère de la chambre jaune'
    AND NOT EXISTS (SELECT 1 FROM exemplaire e WHERE e.code_bcid = 'PL-9D3KF-FR')
  RETURNING id_exemplaire
)
INSERT INTO mouvement (id_exemplaire, id_utilisateur, type_mouvement, latitude, longitude, message, date_mouvement)
SELECT n.id_exemplaire, u.id_utilisateur, m.type::type_mouvement, m.lat, m.lon, m.msg, m.dt::timestamp
FROM nouvel n
CROSS JOIN (VALUES
  ('liberation', 48.841000, 2.320000, 'Libéré à la gare Montparnasse, salle des Pas Perdus.', '2026-09-11 17:45:00', 'theo@demo.pageslibres')
) AS m(type, lat, lon, msg, dt, email)
JOIN utilisateur u ON u.email = m.email;

-- Le Grand Meaulnes
WITH nouvel AS (
  INSERT INTO exemplaire (id_livre, code_bcid, statut, date_creation)
  SELECT l.id_livre, 'PL-2W8GC-FR', 'en_circulation', '2026-06-15'
  FROM livre l
  WHERE l.titre = 'Le Grand Meaulnes'
    AND NOT EXISTS (SELECT 1 FROM exemplaire e WHERE e.code_bcid = 'PL-2W8GC-FR')
  RETURNING id_exemplaire
)
INSERT INTO mouvement (id_exemplaire, id_utilisateur, type_mouvement, latitude, longitude, message, date_mouvement)
SELECT n.id_exemplaire, u.id_utilisateur, m.type::type_mouvement, m.lat, m.lon, m.msg, m.dt::timestamp
FROM nouvel n
CROSS JOIN (VALUES
  ('liberation', 48.849000, 2.373000, 'Libéré sur la coulée verte René-Dumont.', '2026-06-15 12:00:00', 'marie@demo.pageslibres')
) AS m(type, lat, lon, msg, dt, email)
JOIN utilisateur u ON u.email = m.email;

-- ---------------------------------------------------------------------------
-- 4. Recalage de la position courante
--    Le trigger trg_maj_position_exemplaire met à jour exemplaire.position à
--    chaque INSERT de mouvement — mais sur un chargement en masse, l'ordre de
--    traitement des lignes n'est pas garanti : la position pourrait refléter
--    un mouvement intermédiaire. On la recalcule donc depuis le mouvement le
--    plus récent. Idempotent.
-- ---------------------------------------------------------------------------
UPDATE exemplaire e
SET position = ST_SetSRID(ST_MakePoint(d.longitude, d.latitude), 4326)
FROM (
  SELECT DISTINCT ON (id_exemplaire) id_exemplaire, longitude, latitude
  FROM mouvement
  ORDER BY id_exemplaire, date_mouvement DESC, id_mouvement DESC
) AS d
WHERE e.id_exemplaire = d.id_exemplaire;

-- ---------------------------------------------------------------------------
-- 5. Avis, commentaire et signalements (matière du back-office F10)
-- ---------------------------------------------------------------------------

-- Deux avis sur le même livre, par deux membres différents (contrainte
-- UNIQUE(id_livre, id_utilisateur)).
INSERT INTO avis (id_livre, id_utilisateur, note, commentaire, date_creation)
SELECT l.id_livre, u.id_utilisateur, v.note::smallint, v.txt, v.dt::timestamp
FROM (VALUES
  ('Le Comte de Monte-Cristo', 'theo@demo.pageslibres', 5,
   'Relu après dix ans : le feuilleton tient toujours. Pouvoir suivre le voyage de l''exemplaire rend la lecture plus vivante.',
   '2026-07-25 21:10:00'),
  ('Le Comte de Monte-Cristo', 'marie@demo.pageslibres', 4,
   'Trouvé dans une boîte à livres puis relibéré : le carnet de voyage colle parfaitement à ce roman feuilleton.',
   '2026-08-02 18:35:00')
) AS v(titre, email, note, txt, dt)
JOIN livre l ON l.titre = v.titre
JOIN utilisateur u ON u.email = v.email
WHERE NOT EXISTS (
  SELECT 1 FROM avis a WHERE a.id_livre = l.id_livre AND a.id_utilisateur = u.id_utilisateur
);

-- Un commentaire rattaché à l'avis de theo (id_livre NULL : la contrainte
-- chk_commentaire_une_cible impose exactement une cible).
INSERT INTO commentaire (id_avis, id_livre, id_utilisateur, contenu, date_creation)
SELECT a.id_avis, NULL, u.id_utilisateur, v.txt, v.dt::timestamp
FROM (VALUES
  ('D''accord sur le rythme, même si la fin me paraît expédiée. Je le relibère au parc Montsouris pour avoir un autre avis.',
   '2026-08-05 09:50:00')
) AS v(txt, dt)
JOIN avis a ON a.id_livre = (SELECT id_livre FROM livre WHERE titre = 'Le Comte de Monte-Cristo')
JOIN utilisateur au ON au.id_utilisateur = a.id_utilisateur AND au.email = 'theo@demo.pageslibres'
JOIN utilisateur u ON u.email = 'marie@demo.pageslibres'
WHERE NOT EXISTS (SELECT 1 FROM commentaire c WHERE c.contenu = v.txt);

-- Quatre signalements en attente, ciblant chacun une ressource DIFFÉRENTE
-- (exemplaire, avis, commentaire, livre) : les quatre cas de modération.
-- Chaque insertion porte sa cible directement : chk_signalement_une_cible
-- exige exactement une cible renseignée dès l'INSERT.

-- 5.a — un exemplaire signalé comme perdu
INSERT INTO signalement (id_utilisateur_signaleur, id_exemplaire, motif, statut, date_creation)
SELECT u.id_utilisateur, e.id_exemplaire,
       'Livre signalé comme perdu ou laissé à l''abandon', 'en_attente'::statut_signalement,
       '2026-09-01 10:12:00'
FROM utilisateur u, exemplaire e
WHERE u.email = 'marie@demo.pageslibres' AND e.code_bcid = 'PL-7X29K-FR'
  AND NOT EXISTS (SELECT 1 FROM signalement g
                  WHERE g.motif = 'Livre signalé comme perdu ou laissé à l''abandon');

-- 5.b — spam présumé dans l'avis de theo
INSERT INTO signalement (id_utilisateur_signaleur, id_avis, motif, statut, date_creation)
SELECT u.id_utilisateur, a.id_avis, 'Spam présumé dans un avis',
       'en_attente'::statut_signalement, '2026-09-02 14:30:00'
FROM utilisateur u
JOIN avis a ON a.id_livre = (SELECT id_livre FROM livre WHERE titre = 'Le Comte de Monte-Cristo')
JOIN utilisateur au ON au.id_utilisateur = a.id_utilisateur AND au.email = 'theo@demo.pageslibres'
WHERE u.email = 'theo@demo.pageslibres'
  AND NOT EXISTS (SELECT 1 FROM signalement g WHERE g.motif = 'Spam présumé dans un avis');

-- 5.c — propos inappropriés dans le commentaire
INSERT INTO signalement (id_utilisateur_signaleur, id_commentaire, motif, statut, date_creation)
SELECT u.id_utilisateur, c.id_commentaire,
       'Propos inappropriés dans un commentaire', 'en_attente'::statut_signalement,
       '2026-09-05 16:45:00'
FROM utilisateur u
JOIN commentaire c ON c.contenu LIKE 'D''accord sur le rythme%'
WHERE u.email = 'theo@demo.pageslibres'
  AND NOT EXISTS (SELECT 1 FROM signalement g
                  WHERE g.motif = 'Propos inappropriés dans un commentaire');

-- 5.d — doublon présumé au catalogue
INSERT INTO signalement (id_utilisateur_signaleur, id_livre, motif, statut, date_creation)
SELECT u.id_utilisateur, l.id_livre,
       'Doublon présumé avec un autre titre du catalogue', 'en_attente'::statut_signalement,
       '2026-09-08 11:05:00'
FROM utilisateur u, livre l
WHERE u.email = 'marie@demo.pageslibres' AND l.titre = 'Les Légendaires Saga Tome 1'
  AND NOT EXISTS (SELECT 1 FROM signalement g
                  WHERE g.motif = 'Doublon présumé avec un autre titre du catalogue');

-- ---------------------------------------------------------------------------
-- 6. Badges déjà obtenus (F9)
--    Les 5 badges sont créés par la migration de données ; leurs OBTENTIONS
--    sont attribuées par le service au fil des actions réelles. Un chargement
--    SQL n'en produit aucune : on les fige ici pour que le profil d'un compte
--    de démonstration montre quelque chose après un `docker compose down -v`.
-- ---------------------------------------------------------------------------
INSERT INTO obtention_badge (id_utilisateur, id_badge, date_obtention)
SELECT u.id_utilisateur, b.id_badge, v.dt::timestamp
FROM (VALUES
  ('membre@demo.pageslibres', 'Premier envol',  '2026-08-25 09:30:00'),
  ('membre@demo.pageslibres', 'Globe-trotteur', '2026-09-02 18:20:00'),
  ('marie@demo.pageslibres',  'Premier envol',  '2026-09-16 08:10:00'),
  ('theo@demo.pageslibres',   'Premier envol',  '2026-09-16 08:12:00')
) AS v(email, badge, dt)
JOIN utilisateur u ON u.email = v.email
JOIN badge b ON b.nom = v.badge
WHERE NOT EXISTS (
  SELECT 1 FROM obtention_badge o
  WHERE o.id_utilisateur = u.id_utilisateur AND o.id_badge = b.id_badge
);

-- ---------------------------------------------------------------------------
-- 7. Couvertures du jeu de démonstration
--    Les livres sont insérés en SQL, donc sans passer par la recherche par ISBN
--    (F2) qui, elle, renseigne la couverture. On la fournit ici pour que la
--    fiche livre en montre une.
--
--    Source : Open Library (covers.openlibrary.org) — sans clé ni quota. Google
--    Books ne sert pas d'image pour ces éditions et son quota anonyme est vite
--    épuisé (429 constaté). Les ouvrages sans couverture connue restent à NULL :
--    la fiche n'affiche alors simplement pas d'image.
--
--    Idempotent : ne remplit qu'une couverture encore vide.
-- ---------------------------------------------------------------------------
UPDATE livre
SET couverture_url = v.url
FROM (VALUES
  ('9782253096337', 'https://covers.openlibrary.org/b/isbn/9782253096337-M.jpg'),
  ('9782266320481', 'https://covers.openlibrary.org/b/isbn/9782266320481-M.jpg'),
  ('9782012101333', 'https://covers.openlibrary.org/b/isbn/9782012101333-M.jpg'),
  ('9782070360024', 'https://covers.openlibrary.org/b/isbn/9782070360024-M.jpg'),
  ('9782070612758', 'https://covers.openlibrary.org/b/isbn/9782070612758-M.jpg'),
  ('9782253006268', 'https://covers.openlibrary.org/b/isbn/9782253006268-M.jpg')
) AS v(isbn, url)
WHERE livre.isbn = v.isbn
  AND livre.couverture_url IS NULL;

-- ============================================================================
-- Contrôle rapide (à lancer à la main) :
--
--   SELECT count(*) AS livres FROM livre;                       -- 10
--   SELECT count(*) AS exemplaires FROM exemplaire;              -- 12
--   SELECT count(*) AS en_circulation FROM exemplaire
--     WHERE statut = 'en_circulation';                           -- 10
--   SELECT l.titre, count(*) FILTER (WHERE e.statut = 'en_circulation') AS dispo
--     FROM livre l LEFT JOIN exemplaire e ON e.id_livre = l.id_livre
--     GROUP BY l.titre ORDER BY dispo;                           -- aucune ligne à 0
--   SELECT min(date_mouvement)::date, max(date_mouvement)::date
--     FROM mouvement;                     -- 2026-05-12 → 2026-09-16
--   SELECT count(*) FROM obtention_badge;                       -- 4
-- ============================================================================
