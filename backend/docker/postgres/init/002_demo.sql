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
-- Contenu : 4 comptes de démonstration, 50 livres, 52 exemplaires et leurs
-- journaux de voyage du 12/05/2026 au 16/09/2026, plus 2 avis, 1 commentaire,
-- 4 signalements en attente (back-office F10) et 4 badges déjà obtenus (F9).
--
-- Dix livres sont écrits à la main (métadonnées, ISBN, couvertures, journaux
-- soignés) ; les quarante autres remplissent le catalogue et sont engendrés par
-- un bloc déterministe (section 3.b) — sans ISBN, pour ne pas inventer de
-- référence bibliographique. Les journaux vont d'une seule libération à dix.
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
-- 2. Catalogue — 50 livres
--    Les 10 premiers sont ceux du jeu curé (journal écrit à la main plus bas).
--    Les 40 suivants n'ont pas d'ISBN : le jeu de démonstration n'invente pas de
--    référence bibliographique — c'est la recherche par ISBN (F2) qui la renseigne
--    quand un livre est réellement créé.
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
   'La rencontre d''Augustin Meaulnes et du domaine perdu, entre rêve et nostalgie.'),
  -- Les 40 suivants : fond de catalogue, sans ISBN (voir l'en-tête de section).
  (NULL, 'Le Rouge et le Noir', 'Stendhal', 1830, 'Classique', 'Julien Sorel, l''ambition et les sentiments, entre séminaire et salons.'),
  (NULL, 'Madame Bovary', 'Gustave Flaubert', 1857, 'Classique', 'Emma Bovary cherche dans l''adultère l''illusion qu''elle doit au roman.'),
  (NULL, 'Les Misérables', 'Victor Hugo', 1862, 'Classique', 'Jean Valjean, Cosette et la misère du peuple, de Digne aux barricades.'),
  (NULL, 'Germinal', 'Émile Zola', 1885, 'Classique', 'Étienne Lantier et la grève des mineurs du Nord.'),
  (NULL, 'Bel-Ami', 'Guy de Maupassant', 1885, 'Classique', 'Georges Duroy, arriviste sans scrupule, gravit la presse parisienne.'),
  (NULL, 'Le Père Goriot', 'Honoré de Balzac', 1835, 'Classique', 'Un père ruiné par ses filles, dans la pension Vauquer.'),
  (NULL, 'La Chartreuse de Parme', 'Stendhal', 1839, 'Classique', 'Fabrice del Dongo, de Waterloo à la prison de Parme.'),
  (NULL, 'Vingt mille lieues sous les mers', 'Jules Verne', 1870, 'Aventure', 'Le Nautilus du capitaine Nemo et son tour des océans.'),
  (NULL, 'Le Tour du monde en quatre-vingts jours', 'Jules Verne', 1872, 'Aventure', 'Phileas Fogg parie de boucler le globe dans les délais.'),
  (NULL, 'Voyage au centre de la Terre', 'Jules Verne', 1864, 'Aventure', 'Un vieux manuscrit mène au centre du monde, par le Sneffels.'),
  (NULL, 'L''Île mystérieuse', 'Jules Verne', 1875, 'Aventure', 'Cinq naufragés organisent leur survie, aidés par un inconnu.'),
  (NULL, 'Les Trois Mousquetaires', 'Alexandre Dumas', 1844, 'Aventure', 'D''Artagnan et les mousquetaires du roi contre Richelieu.'),
  (NULL, 'Vingt ans après', 'Alexandre Dumas', 1845, 'Aventure', 'Les quatre amis, vingt ans plus tard, entre Fronde et restauration.'),
  (NULL, 'La Reine Margot', 'Alexandre Dumas', 1845, 'Historique', 'Marguerite de Valois, prise entre Charles IX et Henri de Navarre.'),
  (NULL, 'Les Fleurs du mal', 'Charles Baudelaire', 1857, 'Poésie', 'Spleen et Idéal : le recueil qui fit scandale en 1857.'),
  (NULL, 'Alcools', 'Guillaume Apollinaire', 1913, 'Poésie', 'Le Pont Mirabeau, Zone, et la modernité en vers libres.'),
  (NULL, 'Une saison en enfer', 'Arthur Rimbaud', 1873, 'Poésie', 'Le seul recueil publié de son vivant, entre révolte et adieu.'),
  (NULL, 'Les Contemplations', 'Victor Hugo', 1856, 'Poésie', 'Vingt-cinq ans de vie, du bonheur à la mort de Léopoldine.'),
  (NULL, 'Cyrano de Bergerac', 'Edmond Rostand', 1897, 'Théâtre', 'Le nez, le panache, et une lettre écrite pour un autre.'),
  (NULL, 'Le Malade imaginaire', 'Molière', 1673, 'Théâtre', 'Argan, ses médecins, et la ruse d''Angélique.'),
  (NULL, 'L''Avare', 'Molière', 1668, 'Théâtre', 'Harpagon, sa cassette, et la folie de l''économie.'),
  (NULL, 'Le Misanthrope', 'Molière', 1666, 'Théâtre', 'Alceste dit toujours la vérité, et le paie cher.'),
  (NULL, 'Antigone', 'Jean Anouilh', 1944, 'Théâtre', 'La jeune fille face à Créon, sous l''Occupation.'),
  (NULL, 'La Peste', 'Albert Camus', 1947, 'Roman', 'Oran fermée par l''épidémie, et la solidarité qui naît.'),
  (NULL, 'La Chute', 'Albert Camus', 1956, 'Roman', 'Le monologue d''un juge pénitent dans un bar d''Amsterdam.'),
  (NULL, 'Le Mythe de Sisyphe', 'Albert Camus', 1942, 'Essai', 'L''absurde, et l''idée qu''il faut imaginer Sisyphe heureux.'),
  (NULL, 'La Nausée', 'Jean-Paul Sartre', 1938, 'Roman', 'Roquentin, Bouville, et la découverte de l''existence.'),
  (NULL, 'Le Deuxième Sexe', 'Simone de Beauvoir', 1949, 'Essai', '« On ne naît pas femme, on le devient. »'),
  (NULL, 'L''Amant', 'Marguerite Duras', 1984, 'Roman', 'L''enfance indochinoise et la passion interdite, prix Goncourt.'),
  (NULL, 'Moderato cantabile', 'Marguerite Duras', 1958, 'Roman', 'Une femme et un inconnu autour d''un crime, dans un café.'),
  (NULL, 'La Vie devant soi', 'Romain Gary', 1975, 'Roman', 'Mommo, les enfants de Madame Rosa, et Belleville.'),
  (NULL, 'Les Liaisons dangereuses', 'Choderlos de Laclos', 1782, 'Classique', 'Le duel épistolaire de Merteuil et Valmont.'),
  (NULL, 'Manon Lescaut', 'Abbé Prévost', 1731, 'Classique', 'Des Grieux, Manon, et une passion qui ne raisonne pas.'),
  (NULL, 'La Princesse de Clèves', 'Madame de La Fayette', 1678, 'Classique', 'L''aveu impossible, à la cour d''Henri II.'),
  (NULL, 'Le Cid', 'Pierre Corneille', 1637, 'Théâtre', 'Chimène et Rodrigue, entre amour et honneur.'),
  (NULL, 'Andromaque', 'Jean Racine', 1667, 'Théâtre', 'La chaîne des passions, jusqu''au sacrifice.'),
  (NULL, 'Le Petit Nicolas', 'René Goscinny', 1960, 'Jeunesse', 'La bande de copains, la maîtresse, et le ballon.'),
  (NULL, 'Le Club des cinq', 'Enid Blyton', 1942, 'Jeunesse', 'Quatre enfants, un chien, et des vacances qui tournent court.'),
  (NULL, 'Harry Potter à l''école des sorciers', 'J. K. Rowling', 1997, 'Jeunesse', 'Un cousin malheureux découvre qu''il est sorcier.'),
  (NULL, 'Le Seigneur des anneaux', 'J. R. R. Tolkien', 1954, 'Fantasy', 'La Communauté, l''Anneau, et la Terre du Milieu.')
) AS v(isbn, titre, auteur, annee, categorie, resume)
WHERE NOT EXISTS (SELECT 1 FROM livre l WHERE l.titre = v.titre);

-- ---------------------------------------------------------------------------
-- 3. Exemplaires et journaux de voyage
--    Un bloc par exemplaire : l'exemplaire n'est créé que s'il n'existe pas
--    (clé = code_bcid), et ses mouvements sont insérés dans la même requête.
--    Les dates vont de mai à septembre 2026, dans l'ordre chronologique des
--    messages.
-- ---------------------------------------------------------------------------

-- Le Comte de Monte-Cristo — exemplaire 1 : le journal le plus long du jeu
-- (10 libérations, 19 étapes, du 12/05 au 16/09/2026). C'est la frise à montrer
-- pour F6.
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
  ('trouvaille', 48.879000, 2.309000, 'Trouvé au parc Monceau, oublié sur un banc.', '2026-05-20 17:10:00', 'theo@demo.pageslibres'),
  ('liberation', 48.833000, 2.376000, 'Libéré devant la BnF François-Mitterrand.', '2026-05-28 13:15:00', 'theo@demo.pageslibres'),
  ('trouvaille', 48.846200, 2.337200, 'Trouvé au jardin du Luxembourg, sous le kiosque à musique.', '2026-06-03 18:40:00', 'theo@demo.pageslibres'),
  ('liberation', 48.843000, 2.360000, 'Libéré au Jardin des Plantes, près de la ménagerie.', '2026-06-11 10:05:00', 'marie@demo.pageslibres'),
  ('trouvaille', 48.872000, 2.365000, 'Trouvé au bord du canal Saint-Martin.', '2026-06-18 19:25:00', 'theo@demo.pageslibres'),
  ('liberation', 48.804900, 2.120400, 'Relâché à Versailles, dans les jardins du château.', '2026-06-24 11:05:00', 'theo@demo.pageslibres'),
  ('trouvaille', 48.880900, 2.382000, 'Trouvé au parc des Buttes-Chaumont, au bord du lac.', '2026-06-30 15:50:00', 'marie@demo.pageslibres'),
  ('liberation', 48.849000, 2.373000, 'Libéré sur la coulée verte René-Dumont.', '2026-07-07 12:00:00', 'marie@demo.pageslibres'),
  ('trouvaille', 48.855500, 2.365500, 'Trouvé sous les arcades de la place des Vosges.', '2026-07-14 18:05:00', 'theo@demo.pageslibres'),
  ('liberation', 48.863500, 2.327000, 'Libéré au jardin des Tuileries, près du bassin.', '2026-07-19 09:45:00', 'theo@demo.pageslibres'),
  ('trouvaille', 48.862000, 2.240000, 'Retrouvé au bois de Boulogne, abandonné sur un banc.', '2026-07-22 16:15:00', 'marie@demo.pageslibres'),
  ('liberation', 48.886700, 2.343100, 'Libéré sur les marches du Sacré-Cœur, à Montmartre.', '2026-07-30 20:30:00', 'marie@demo.pageslibres'),
  ('trouvaille', 48.841000, 2.320000, 'Trouvé à la gare Montparnasse, salle des Pas Perdus.', '2026-08-07 08:15:00', 'theo@demo.pageslibres'),
  ('liberation', 48.858000, 2.346500, 'Libéré aux Halles, sur une borne du forum.', '2026-08-16 14:20:00', 'theo@demo.pageslibres'),
  ('trouvaille', 48.833000, 2.433000, 'Trouvé au bois de Vincennes, sur un muret.', '2026-08-25 19:00:00', 'marie@demo.pageslibres'),
  ('liberation', 48.838000, 2.418000, 'Relibéré au bord du lac Daumesnil.', '2026-09-02 10:40:00', 'marie@demo.pageslibres'),
  ('trouvaille', 48.846200, 2.337500, 'Retrouvé au jardin du Luxembourg, sur une chaise verte.', '2026-09-09 17:55:00', 'theo@demo.pageslibres'),
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
-- 3.b Exemplaires et journaux des quarante livres de fond de catalogue
--
--     Écrit de façon déterministe plutôt qu'à la main : quarante journaux
--     représenteraient plusieurs centaines de lignes pour un intérêt de
--     démonstration nul. Rien n'est tiré au hasard — tout dérive de l'identifiant
--     du livre — donc rejouer le fichier redonne exactement le même jeu.
--
--     Chaque journal commence par une libération, alterne libération et
--     trouvaille, et se termine par une libération : l'exemplaire reste donc
--     « en circulation ». Les dates s'étalent du 12/05 au 15/09/2026, comme le
--     reste du jeu, et les lieux sont ceux du jeu curé.
--
--     Nombre de libérations : 1 à 3 pour l'essentiel, 5 et 7 pour quelques
--     livres — le maximum du jeu (10) restant réservé à la vedette ci-dessus.
-- ---------------------------------------------------------------------------

-- Un exemplaire pour chaque livre qui n'en a pas encore : les dix livres curés
-- plus haut en ont déjà un, ou deux.
INSERT INTO exemplaire (id_livre, code_bcid, statut, date_creation)
SELECT l.id_livre,
       'PL-' || upper(substr(md5(l.id_livre::text || l.titre), 1, 5)) || '-FR',
       'en_circulation'::statut_exemplaire,
       date '2026-05-12'
FROM livre l
WHERE NOT EXISTS (SELECT 1 FROM exemplaire e WHERE e.id_livre = l.id_livre);

-- Puis le journal de chaque exemplaire encore vierge de tout mouvement.
WITH lieux(id, lat, lon, precision) AS (VALUES
  (1, 48.844300, 2.374300, 'près de la gare de Lyon, sur un banc du hall.'),
  (2, 48.846200, 2.337500, 'au jardin du Luxembourg, sous la grille.'),
  (3, 48.858000, 2.346500, 'aux Halles, sur une borne du forum.'),
  (4, 48.860600, 2.337600, 'sous la pyramide du Louvre.'),
  (5, 48.886700, 2.343100, 'sur les marches du Sacré-Cœur.'),
  (6, 48.833000, 2.376000, 'devant la BnF François-Mitterrand.'),
  (7, 48.841000, 2.320000, 'à la gare Montparnasse, salle des Pas Perdus.'),
  (8, 48.849000, 2.373000, 'sur la coulée verte René-Dumont.')
),
besoins AS (
  SELECT e.id_exemplaire,
         l.id_livre,
         CASE
           WHEN l.id_livre % 17 = 0 THEN 7
           WHEN l.id_livre % 11 = 0 THEN 5
           ELSE 1 + (l.id_livre % 3)
         END AS nb_liberations
  FROM exemplaire e
  JOIN livre l ON l.id_livre = e.id_livre
  WHERE NOT EXISTS (SELECT 1 FROM mouvement m WHERE m.id_exemplaire = e.id_exemplaire)
),
etapes AS (
  SELECT b.id_exemplaire,
         b.id_livre,
         k AS numero,
         k % 2 = 1 AS est_liberation,
         ((k - 1) * 126) / greatest(2 * b.nb_liberations - 2, 1) AS jours
  FROM besoins b
  CROSS JOIN LATERAL generate_series(1, 2 * b.nb_liberations - 1) AS k
)
INSERT INTO mouvement (id_exemplaire, id_utilisateur, type_mouvement, latitude, longitude, message, date_mouvement)
SELECT e.id_exemplaire,
       u.id_utilisateur,
       (CASE WHEN e.est_liberation THEN 'liberation' ELSE 'trouvaille' END)::type_mouvement,
       v.lat,
       v.lon,
       (CASE WHEN e.est_liberation THEN 'Libéré ' ELSE 'Trouvé ' END) || v.precision,
       date '2026-05-12' + e.jours
FROM etapes e
JOIN lieux v ON v.id = 1 + ((e.id_livre + e.numero) % 8)
JOIN utilisateur u ON u.email = CASE WHEN e.numero % 2 = 1
                                     THEN 'marie@demo.pageslibres'
                                     ELSE 'theo@demo.pageslibres' END;

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
--    épuisé (429 constaté) ; la seule qui vienne de Google est celle du livre
--    créé par la recherche par ISBN.
--
--    La correspondance se fait sur le TITRE : les quarante livres de fond de
--    catalogue n'ont pas d'ISBN (voir section 2).
--    Idempotent : ne remplit qu'une couverture encore vide.
-- ---------------------------------------------------------------------------
UPDATE livre
SET couverture_url = v.url
FROM (VALUES
  -- Les dix livres du jeu curé
  ('Le Comte de Monte-Cristo', NULL),
  ('Les Légendaires Saga Tome 1', 'https://books.google.com/books/content?id=FdJqzQEACAAJ&printsec=frontcover&img=1&zoom=2&source=gbs_api'),
  ('Les Racines du ciel', NULL),
  ('Le Petit Prince', 'https://covers.openlibrary.org/b/isbn/9782070612758-M.jpg'),
  ('L''Étranger', 'https://covers.openlibrary.org/b/isbn/9782070360024-M.jpg'),
  ('Notre-Dame de Paris', 'https://covers.openlibrary.org/b/isbn/9782253096337-M.jpg'),
  ('Astérix le Gaulois', 'https://covers.openlibrary.org/b/isbn/9782012101333-M.jpg'),
  ('Dune', 'https://covers.openlibrary.org/b/isbn/9782266320481-M.jpg'),
  ('Le Mystère de la chambre jaune', 'https://covers.openlibrary.org/b/isbn/9782253006268-M.jpg'),
  ('Le Grand Meaulnes', NULL),
  -- Les quarante livres de fond de catalogue
  ('Le Rouge et le Noir', 'https://covers.openlibrary.org/b/id/8231413-M.jpg'),
  ('Madame Bovary', 'https://covers.openlibrary.org/b/id/12993424-M.jpg'),
  ('Les Misérables', 'https://covers.openlibrary.org/b/id/12721865-M.jpg'),
  ('Germinal', 'https://covers.openlibrary.org/b/id/8236935-M.jpg'),
  ('Bel-Ami', 'https://covers.openlibrary.org/b/id/997432-M.jpg'),
  ('Le Père Goriot', 'https://covers.openlibrary.org/b/id/15156928-M.jpg'),
  ('La Chartreuse de Parme', 'https://covers.openlibrary.org/b/id/104382-M.jpg'),
  ('Vingt mille lieues sous les mers', 'https://covers.openlibrary.org/b/id/6573517-M.jpg'),
  ('Le Tour du monde en quatre-vingts jours', 'https://covers.openlibrary.org/b/id/6976035-M.jpg'),
  ('Voyage au centre de la Terre', 'https://covers.openlibrary.org/b/id/5890987-M.jpg'),
  ('L''Île mystérieuse', 'https://covers.openlibrary.org/b/id/1277096-M.jpg'),
  ('Les Trois Mousquetaires', 'https://covers.openlibrary.org/b/id/11929973-M.jpg'),
  ('Vingt ans après', 'https://covers.openlibrary.org/b/id/14564526-M.jpg'),
  ('La Reine Margot', 'https://covers.openlibrary.org/b/id/14557277-M.jpg'),
  ('Les Fleurs du mal', 'https://covers.openlibrary.org/b/id/8236412-M.jpg'),
  ('Alcools', 'https://covers.openlibrary.org/b/id/4647361-M.jpg'),
  ('Une saison en enfer', 'https://covers.openlibrary.org/b/id/4601563-M.jpg'),
  ('Les Contemplations', 'https://covers.openlibrary.org/b/id/8247081-M.jpg'),
  ('Cyrano de Bergerac', 'https://covers.openlibrary.org/b/id/8236320-M.jpg'),
  ('Le Malade imaginaire', 'https://covers.openlibrary.org/b/id/8243180-M.jpg'),
  ('L''Avare', 'https://covers.openlibrary.org/b/id/10776163-M.jpg'),
  ('Le Misanthrope', 'https://covers.openlibrary.org/b/id/6523174-M.jpg'),
  ('Antigone', 'https://covers.openlibrary.org/b/id/116843-M.jpg'),
  ('La Peste', 'https://covers.openlibrary.org/b/id/13151272-M.jpg'),
  ('La Chute', 'https://covers.openlibrary.org/b/id/8296477-M.jpg'),
  ('Le Mythe de Sisyphe', 'https://covers.openlibrary.org/b/id/1014395-M.jpg'),
  ('La Nausée', 'https://covers.openlibrary.org/b/id/9393973-M.jpg'),
  ('Le Deuxième Sexe', 'https://covers.openlibrary.org/b/id/78169-M.jpg'),
  ('L''Amant', 'https://covers.openlibrary.org/b/id/5401955-M.jpg'),
  ('Moderato cantabile', 'https://covers.openlibrary.org/b/id/983837-M.jpg'),
  ('La Vie devant soi', 'https://covers.openlibrary.org/b/id/10374575-M.jpg'),
  ('Les Liaisons dangereuses', 'https://covers.openlibrary.org/b/id/5258265-M.jpg'),
  ('Manon Lescaut', 'https://covers.openlibrary.org/b/id/8236918-M.jpg'),
  ('La Princesse de Clèves', NULL),
  ('Le Cid', 'https://covers.openlibrary.org/b/id/8236984-M.jpg'),
  ('Andromaque', 'https://covers.openlibrary.org/b/id/8231426-M.jpg'),
  ('Le Petit Nicolas', 'https://covers.openlibrary.org/b/id/12856967-M.jpg'),
  ('Le Club des cinq', 'https://covers.openlibrary.org/b/id/962680-M.jpg'),
  ('Harry Potter à l''école des sorciers', NULL),
  ('Le Seigneur des anneaux', NULL)
) AS v(titre, url)
WHERE livre.titre = v.titre
  AND v.url IS NOT NULL
  AND livre.couverture_url IS NULL;

-- ---------------------------------------------------------------------------
-- 8. Un membre qui a exercé son droit à l'effacement (RGPD)
--
--    La règle vient du MPD et n'est pas négociable : toutes les clés étrangères
--    vers `utilisateur` sont en ON DELETE SET NULL, jamais CASCADE —
--    « id_utilisateur mis à NULL si le compte est supprimé, sans effacer
--    l'événement ». Les contributions restent donc, anonymisées, pour ne pas
--    rompre l'historique de voyage d'un livre.
--
--    Ce bloc le met en scène au lieu de l'affirmer : un compte est créé,
--    déclare deux trouvailles, puis est supprimé. Les deux mouvements
--    subsistent sans auteur, et la frise du livre affiche « par un membre
--    supprimé ».
--
--    Placé en fin de fichier pour ne pas décaler les récits numérotés
--    ci-dessus, et bâti pour être rejouable : on repart d'un état propre avant
--    de rejouer la scène, si bien qu'aucun doublon ne s'accumule.
-- ---------------------------------------------------------------------------

-- a) État initial : les mouvements anonymes de ces deux exemplaires, et leur
--    statut. Un rejeu repart donc de zéro.
DELETE FROM mouvement
 WHERE id_utilisateur IS NULL
   AND id_exemplaire IN (SELECT id_exemplaire FROM exemplaire WHERE code_bcid IN ('PL-2M4QW-FR', 'PL-5RJ2N-FR'));

UPDATE exemplaire SET statut = 'en_circulation' WHERE code_bcid IN ('PL-2M4QW-FR', 'PL-5RJ2N-FR');

-- b) Le compte, créé pour la démonstration. Son mot de passe est celui des
--    autres comptes du jeu (DemoPagesLibres2026!) : il disparaît à l'étape d).
INSERT INTO utilisateur (pseudo, email, mot_de_passe_hash, bio, role, date_inscription)
SELECT 'membre_parti', 'parti@demo.pageslibres',
       '$argon2id$v=19$m=65536,t=4,p=1$IBDGHbTS3DbFG+7cyofyag$JdlBcruflxoskLc86qtY+ZNxyIMNWt/bJDlaHtHxQXA',
       'A exercé son droit à l''effacement — ses trouvailles restent.', 'membre', '2026-08-01 09:00:00'
 WHERE NOT EXISTS (SELECT 1 FROM utilisateur WHERE email = 'parti@demo.pageslibres');

-- c) Ses deux trouvailles, après le dernier mouvement de chaque exemplaire.
INSERT INTO mouvement (id_exemplaire, id_utilisateur, type_mouvement, latitude, longitude, message, date_mouvement)
SELECT e.id_exemplaire, u.id_utilisateur, 'trouvaille'::type_mouvement, v.lat, v.lon, v.msg, v.dt::timestamp
FROM exemplaire e
JOIN utilisateur u ON u.email = 'parti@demo.pageslibres'
CROSS JOIN (VALUES
  ('PL-2M4QW-FR', 48.880900, 2.355300, 'Trouvé aux Halles, sur une borne du forum.', '2026-09-14 18:10:00'),
  ('PL-5RJ2N-FR', 48.841200, 2.343000, 'Trouvé au jardin des Plantes, sur un banc à l''ombre.', '2026-09-12 11:05:00')
) AS v(code_bcid, lat, lon, msg, dt)
WHERE e.code_bcid = v.code_bcid;

UPDATE exemplaire SET statut = 'trouve' WHERE code_bcid IN ('PL-2M4QW-FR', 'PL-5RJ2N-FR');

-- d) Le droit à l'effacement. Le compte disparaît ; les deux mouvements écrits
--    en c) restent en base, avec id_utilisateur à NULL. C'est la seule ligne
--    de ce fichier qui exerce réellement la règle du MPD.
DELETE FROM utilisateur WHERE email = 'parti@demo.pageslibres';

-- ============================================================================
-- Contrôle rapide (à lancer à la main) :
--
--   SELECT count(*) AS livres FROM livre;                        -- 50
--   SELECT count(*) AS exemplaires FROM exemplaire;              -- 52
--   SELECT count(*) AS en_circulation FROM exemplaire
--     WHERE statut = 'en_circulation';                           -- 48
--   SELECT count(*) AS mouvements FROM mouvement;                -- 202
--   SELECT count(*) FROM mouvement
--     WHERE id_utilisateur IS NULL;                              -- 2
--   SELECT l.titre, count(*) FILTER (WHERE e.statut = 'en_circulation') AS dispo
--     FROM livre l LEFT JOIN exemplaire e ON e.id_livre = l.id_livre
--     GROUP BY l.titre ORDER BY dispo;
--     -- deux titres à 0 : leurs exemplaires uniques sont entre les mains d'un
--     -- trouveur. C'est le cas normal d'un livre relâché, et la section 8 en
--     -- fait justement une démonstration.
--   SELECT min(date_mouvement)::date, max(date_mouvement)::date
--     FROM mouvement;                     -- 2026-05-12 → 2026-09-16
--   SELECT count(*) FILTER (WHERE couverture_url IS NOT NULL) AS avec,
--          count(*) FILTER (WHERE couverture_url IS NULL) AS sans
--     FROM livre;                                                -- 44 / 6
--   SELECT count(*) FROM obtention_badge;                        -- 4
--   SELECT count(*) FROM signalement WHERE statut = 'en_attente'; -- 4
--
-- Et la vedette de la démonstration, le journal le plus long du jeu :
--
--   SELECT count(*) FROM mouvement m JOIN exemplaire e USING (id_exemplaire)
--     WHERE e.code_bcid = 'PL-7F8KT-FR';                         -- 19 étapes
--   SELECT count(*) FROM mouvement m JOIN exemplaire e USING (id_exemplaire)
--     WHERE e.code_bcid = 'PL-7F8KT-FR'
--       AND m.type_mouvement = 'liberation';                     -- 10 libérations
--
-- Rejouer le fichier ne change aucun de ces chiffres (idempotence).
-- ============================================================================
