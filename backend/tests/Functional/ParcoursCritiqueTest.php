<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;

/**
 * Un seul test de bout en bout sur le parcours critique (F1, F2, F3, F5,
 * F6) plutôt qu'une couverture exhaustive prématurée (cf. directive
 * Jalon 5 Phase 1) :
 * inscription -> connexion -> création d'un livre -> libération d'un
 * exemplaire -> déclaration de trouvaille -> lecture du journal de
 * voyage. Vérifie au passage la règle non négociable : aucune position
 * exacte ne doit jamais sortir de l'API.
 */
final class ParcoursCritiqueTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = true;

    public function testParcoursCritiqueDeBoutEnBout(): void
    {
        $client = static::createClient();
        $suffix = bin2hex(random_bytes(4));
        $email = "lecteur_{$suffix}@example.test";
        $motDePasse = 'un-mot-de-passe-solide';

        // --- F1 : inscription ---
        $client->request('POST', '/api/utilisateurs', [
            'headers' => ['Accept' => 'application/json'],
            'json' => [
                'pseudo' => "lecteur_{$suffix}",
                'email' => $email,
                'plainPassword' => $motDePasse,
            ],
        ]);
        self::assertResponseStatusCodeSame(201);
        $utilisateur = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayNotHasKey('motDePasseHash', $utilisateur, 'Le hash du mot de passe ne doit jamais être exposé.');
        self::assertArrayNotHasKey('plainPassword', $utilisateur);

        // --- F1 : connexion ---
        $client->request('POST', '/api/login_check', [
            'headers' => ['Accept' => 'application/json'],
            'json' => ['email' => $email, 'password' => $motDePasse],
        ]);
        self::assertResponseIsSuccessful();
        $token = json_decode($client->getResponse()->getContent(), true)['token'] ?? null;
        self::assertNotNull($token, 'La connexion doit renvoyer un JWT.');

        $auth = ['headers' => ['Accept' => 'application/json', 'Authorization' => "Bearer {$token}"]];

        // --- F2 : création d'un livre ---
        $client->request('POST', '/api/livres', $auth + ['json' => [
            'titre' => 'Le Parcours Critique',
            'auteur' => 'Suite E2E',
            'categorie' => 'Test',
        ]]);
        self::assertResponseStatusCodeSame(201);
        $livre = json_decode($client->getResponse()->getContent(), true);
        $livreIri = "/api/livres/{$livre['idLivre']}";

        // --- F2 (négatif) : la validation Assert doit s'appliquer en entrée ---
        $client->request('POST', '/api/livres', $auth + ['json' => [
            'titre' => '',
            'auteur' => 'Suite E2E',
            'categorie' => 'Test',
        ]]);
        self::assertResponseStatusCodeSame(422, 'Un titre vide doit être rejeté par les contraintes Assert, avant toute persistance.');

        // --- F3 : création d'un exemplaire (catalogue -> objet physique) ---
        $client->request('POST', '/api/exemplaires', $auth + ['json' => [
            'livre' => $livreIri,
            'codeBcid' => "E2E-{$suffix}",
        ]]);
        self::assertResponseStatusCodeSame(201);
        $exemplaire = json_decode($client->getResponse()->getContent(), true);
        $exemplaireIri = "/api/exemplaires/{$exemplaire['idExemplaire']}";
        self::assertSame('en_circulation', $exemplaire['statut']);

        // --- F3 : libération (premier mouvement) ---
        $client->request('POST', '/api/mouvements', $auth + ['json' => [
            'exemplaire' => $exemplaireIri,
            'typeMouvement' => 'liberation',
            'latitude' => '48.858370',
            'longitude' => '2.294481',
        ]]);
        self::assertResponseStatusCodeSame(201);
        $mouvementLiberation = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayNotHasKey('latitude', $mouvementLiberation, 'La latitude exacte ne doit jamais être exposée par l\'API.');
        self::assertArrayNotHasKey('longitude', $mouvementLiberation, 'La longitude exacte ne doit jamais être exposée par l\'API.');
        self::assertArrayHasKey('positionArrondie', $mouvementLiberation);

        // --- F5 : déclaration de trouvaille ---
        $client->request('POST', '/api/mouvements', $auth + ['json' => [
            'exemplaire' => $exemplaireIri,
            'typeMouvement' => 'trouvaille',
            'latitude' => '48.860000',
            'longitude' => '2.300000',
            'message' => 'Trouvé sur un banc au Jardin du Luxembourg.',
        ]]);
        self::assertResponseStatusCodeSame(201);
        $mouvementTrouvaille = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayNotHasKey('latitude', $mouvementTrouvaille);
        self::assertArrayNotHasKey('longitude', $mouvementTrouvaille);

        // --- F5 (négatif) : on ne peut pas déclarer trouvé un exemplaire déjà trouvé ---
        $client->request('POST', '/api/mouvements', $auth + ['json' => [
            'exemplaire' => $exemplaireIri,
            'typeMouvement' => 'trouvaille',
            'latitude' => '48.860000',
            'longitude' => '2.300000',
        ]]);
        self::assertResponseStatusCodeSame(422, 'Une seconde trouvaille sur un exemplaire déjà "trouve" doit être rejetée.');

        // --- F6 : lecture du journal de voyage ---
        $client->request('GET', $exemplaireIri, $auth);
        self::assertResponseIsSuccessful();
        $exemplaireDetail = json_decode($client->getResponse()->getContent(), true);

        self::assertSame('trouve', $exemplaireDetail['statut'], 'Le statut doit avoir basculé après la trouvaille (logique métier du processor, pas du trigger).');
        self::assertArrayHasKey('positionArrondie', $exemplaireDetail);
        self::assertArrayNotHasKey('position', $exemplaireDetail, 'La géométrie brute ne doit jamais être exposée.');

        self::assertCount(2, $exemplaireDetail['mouvements'], 'Le journal de voyage doit contenir les 2 mouvements.');
        self::assertSame('liberation', $exemplaireDetail['mouvements'][0]['typeMouvement'], 'Le journal doit être trié chronologiquement.');
        self::assertSame('trouvaille', $exemplaireDetail['mouvements'][1]['typeMouvement']);

        foreach ($exemplaireDetail['mouvements'] as $mouvement) {
            self::assertArrayNotHasKey('latitude', $mouvement);
            self::assertArrayNotHasKey('longitude', $mouvement);
            self::assertArrayHasKey('positionArrondie', $mouvement);
        }

        // --- F4 : recherche de proximité doit retrouver l'exemplaire ---
        $client->request('GET', '/api/exemplaires/proximite?lat=48.858370&lon=2.294481&rayon=50000', $auth);
        self::assertResponseIsSuccessful();
        $proches = json_decode($client->getResponse()->getContent(), true);
        $idsProches = array_column($proches['member'] ?? $proches, 'idExemplaire');
        self::assertContains($exemplaire['idExemplaire'], $idsProches, 'La recherche de proximité doit retrouver l\'exemplaire libéré.');

        // --- F7 : publication d'un avis noté sur le livre ---
        $client->request('POST', '/api/avis', $auth + ['json' => [
            'livre' => $livreIri,
            'note' => 5,
            'commentaire' => 'Un excellent parcours de lecture !',
        ]]);
        self::assertResponseStatusCodeSame(201);
        $avis = json_decode($client->getResponse()->getContent(), true);
        self::assertSame("lecteur_{$suffix}", $avis['utilisateur']['pseudo'] ?? null, 'L\'auteur de l\'avis doit être l\'utilisateur authentifié, jamais fourni par le client.');

        // --- F7 (négatif) : une note hors de 1-5 est rejetée ---
        $client->request('POST', '/api/avis', $auth + ['json' => [
            'livre' => $livreIri,
            'note' => 9,
        ]]);
        self::assertResponseStatusCodeSame(422, 'Une note hors de l\'intervalle 1-5 doit être rejetée.');

        // --- F7 (négatif) : un second avis du même utilisateur sur le même
        // livre doit être un 422 propre (UNIQUE en base), pas un 500 ---
        $client->request('POST', '/api/avis', $auth + ['json' => [
            'livre' => $livreIri,
            'note' => 3,
        ]]);
        self::assertResponseStatusCodeSame(422, 'Un doublon d\'avis (même utilisateur, même livre) doit être un 422, pas un 500.');

        // --- F7 : commentaire sur l'avis ---
        $client->request('POST', '/api/commentaires', $auth + ['json' => [
            'avis' => "/api/avis/{$avis['idAvis']}",
            'contenu' => 'Totalement d\'accord avec cet avis.',
        ]]);
        self::assertResponseStatusCodeSame(201);

        // --- F7 (négatif) : un commentaire ne peut cibler avis ET livre à la fois ---
        $client->request('POST', '/api/commentaires', $auth + ['json' => [
            'avis' => "/api/avis/{$avis['idAvis']}",
            'livre' => $livreIri,
            'contenu' => 'Double cible interdite.',
        ]]);
        self::assertResponseStatusCodeSame(422, 'Un commentaire avec avis ET livre doit être rejeté (ExactlyOneTarget).');

        // --- F7 : lecture publique des avis filtrés par livre ---
        $client->request('GET', "/api/avis?livre={$livre['idLivre']}", $auth);
        self::assertResponseIsSuccessful();
        $avisListe = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(1, $avisListe['member'] ?? $avisListe, 'Le filtre par livre doit renvoyer l\'avis créé.');

        // --- F9 : profil de l'utilisateur courant ---
        $client->request('GET', '/api/moi', $auth);
        self::assertResponseIsSuccessful();
        $profil = json_decode($client->getResponse()->getContent(), true);
        self::assertSame("lecteur_{$suffix}", $profil['pseudo']);
        self::assertSame($email, $profil['email'], 'L\'utilisateur voit son propre email sur /api/moi (ApiProperty::security).');

        // --- F9 : historique des mouvements du lecteur courant ---
        $client->request('GET', "/api/mouvements?utilisateur=/api/utilisateurs/{$profil['idUtilisateur']}", $auth);
        self::assertResponseIsSuccessful();
        $mesMouvements = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(2, $mesMouvements['member'] ?? $mesMouvements, 'La libération et la trouvaille doivent apparaître dans l\'historique.');

        // --- F9 : le badge "premiere_liberation" doit avoir été attribué
        // automatiquement après la libération (MouvementProcessor). ---
        $client->request('GET', "/api/obtention_badges?utilisateur=/api/utilisateurs/{$profil['idUtilisateur']}", $auth);
        self::assertResponseIsSuccessful();
        $badges = json_decode($client->getResponse()->getContent(), true);
        $badges = $badges['member'] ?? $badges;
        self::assertNotEmpty($badges, 'Le badge "premiere_liberation" doit être attribué après une première libération.');
        self::assertSame('Premier envol', $badges[0]['badge']['nom'] ?? null);
    }
}
