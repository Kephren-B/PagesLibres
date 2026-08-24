<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * F9 : seed des 5 badges du périmètre fermé (Jalon 1). Données de
 * référence (catalogue), pas de schéma — ne touche ni l'index GiST ni le
 * trigger trg_maj_position_exemplaire. critere_type est lu par
 * App\Gamification\BadgeAttributionService pour choisir la métrique à
 * calculer ; critere_valeur est le seuil.
 *
 * Rappel : périmètre fermé à ces 5 badges, ne pas en ajouter d'autres.
 */
final class Version20260824150304 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'F9 : seed des 5 badges (périmètre fermé, Jalon 1).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO badge (nom, description, critere_type, critere_valeur) VALUES
                ('Premier envol', 'Libérer un premier exemplaire.', 'premiere_liberation', 1),
                ('Grand libérateur', 'Libérer 10 exemplaires.', 'liberations', 10),
                ('Globe-trotteur', 'Faire voyager des livres sur 50 km cumulés.', 'distance_km', 50),
                ('Relais', 'Relibérer 5 exemplaires précédemment trouvés.', 'chaine_relibrations', 5),
                ('Vétéran', 'Être membre depuis 90 jours.', 'anciennete_jours', 90)
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DELETE FROM badge WHERE critere_type IN (
                'premiere_liberation', 'liberations', 'distance_km', 'chaine_relibrations', 'anciennete_jours'
            )
            SQL);
    }
}
