<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;

/**
 * Profil du membre (F9) : historique chronologique et pseudo modifiable.
 *
 * Les deux défauts corrigés ici ont été constatés **par la mesure** avant
 * d'écrire la moindre ligne de code :
 *
 * · `GET /api/mouvements` rendait les lignes par ordre d'insertion (sur les
 *   comptes de démonstration : 2026-09-16 puis 2026-05-12, entremêlés), et
 *   `?order[dateMouvement]=asc|desc` était **ignoré en silence** — la même
 *   famille de piège que `itemsPerPage` plafonné ;
 * · `Utilisateur` n'exposait ni `Patch` ni `Put` : le pseudo était figé.
 *
 * Le contrat vérifié est celui de l'API, pas celui du formulaire : c'est ce que
 * voit n'importe quel client.
 */
final class ProfilTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = true;

    /**
     * Un historique n'a de sens que chronologique. Les trois mouvements sont
     * créés dans la même seconde, donc de date identique : c'est `idMouvement`
     * qui départage, et c'est précisément le cas qui casserait une pagination
     * sans ordre stable (une ligne répétée ou sautée).
     */
    public function testHistoriqueDuPlusRecentAuPlusAncien(): void
    {
        $client = static::createClient();
        $suffix = bin2hex(random_bytes(4));
        [$auth, $utilisateur] = $this->creerEtAuthentifier($client, $suffix);

        $crees = [];
        for ($rang = 0; $rang < 3; ++$rang) {
            $crees[] = $this->creerMouvement($client, $auth, $suffix, $rang);
        }

        $client->request('GET', "/api/mouvements?utilisateur=/api/utilisateurs/{$utilisateur}", $auth);
        self::assertResponseIsSuccessful();

        self::assertSame(
            array_reverse($crees),
            array_column($this->decoder($client), 'idMouvement'),
            'Le mouvement le plus récent doit venir en tête, et l\'ordre rester stable à date égale.',
        );
    }

    public function testHistoriqueTriableDansLesDeuxSens(): void
    {
        $client = static::createClient();
        $suffix = bin2hex(random_bytes(4));
        [$auth, $utilisateur] = $this->creerEtAuthentifier($client, $suffix);

        $crees = [];
        for ($rang = 0; $rang < 3; ++$rang) {
            $crees[] = $this->creerMouvement($client, $auth, $suffix, $rang);
        }

        $chemin = "/api/mouvements?utilisateur=/api/utilisateurs/{$utilisateur}";

        $client->request('GET', $chemin . '&order[dateMouvement]=desc&order[idMouvement]=desc', $auth);
        self::assertResponseIsSuccessful();
        $decroissant = array_column($this->decoder($client), 'idMouvement');

        $client->request('GET', $chemin . '&order[dateMouvement]=asc&order[idMouvement]=asc', $auth);
        self::assertResponseIsSuccessful();
        $croissant = array_column($this->decoder($client), 'idMouvement');

        self::assertSame(array_reverse($crees), $decroissant);
        self::assertSame($crees, $croissant, 'Le tri croissant doit être honoré, pas ignoré.');
        self::assertNotSame($decroissant, $croissant, 'Les deux sens doivent réellement différer.');
    }

    public function testLeMembreModifieSonPseudo(): void
    {
        $client = static::createClient();
        $suffix = bin2hex(random_bytes(4));
        [$auth, $utilisateur] = $this->creerEtAuthentifier($client, $suffix);

        $this->patch($client, $auth, "/api/utilisateurs/{$utilisateur}", ['pseudo' => "renomme_{$suffix}"]);

        self::assertResponseIsSuccessful();
        self::assertSame("renomme_{$suffix}", $this->decoder($client)['pseudo']);
    }

    /**
     * Le verrou porte sur l'objet, pas sur le rôle : un membre ne renomme que
     * lui-même. Sans ce contrôle, l'opération serait ouverte à tous.
     */
    public function testOnNeModifiePasLeProfilDunAutre(): void
    {
        $client = static::createClient();
        $suffix = bin2hex(random_bytes(4));
        [, $utilisateur] = $this->creerEtAuthentifier($client, $suffix);
        [$autreAuth] = $this->creerEtAuthentifier($client, "autre_{$suffix}");

        $this->patch($client, $autreAuth, "/api/utilisateurs/{$utilisateur}", ['pseudo' => 'pirate']);
        self::assertResponseStatusCodeSame(403);

        // La cible est intacte — constaté, pas supposé.
        $client->request('GET', "/api/utilisateurs/{$utilisateur}", ['headers' => ['Accept' => 'application/json']]);
        self::assertResponseIsSuccessful();
        self::assertSame("profil_{$suffix}", $this->decoder($client)['pseudo']);
    }

    /**
     * Deux verrous, mesurés sur la réponse : le contexte de dénormalisation
     * n'accepte que le pseudo (`email` et `role` envoyés sont ignorés), et
     * `role` ne figure dans aucun groupe d'écriture — aucune auto-promotion.
     */
    public function testLePatchIgnoreRoleEtEmail(): void
    {
        $client = static::createClient();
        $suffix = bin2hex(random_bytes(4));
        [$auth, $utilisateur] = $this->creerEtAuthentifier($client, $suffix);

        $this->patch($client, $auth, "/api/utilisateurs/{$utilisateur}", [
            'pseudo' => "renomme_{$suffix}",
            'role' => 'admin',
            'email' => "pirate_{$suffix}@example.test",
        ]);

        self::assertResponseIsSuccessful();
        $profil = $this->decoder($client);
        self::assertSame("renomme_{$suffix}", $profil['pseudo']);
        self::assertSame("profil_{$suffix}@example.test", $profil['email']);
        self::assertSame('membre', $profil['role']);
    }

    public function testPseudoDejaPrisRefuse(): void
    {
        $client = static::createClient();
        $suffix = bin2hex(random_bytes(4));
        [$auth, $utilisateur] = $this->creerEtAuthentifier($client, $suffix);

        // Le pseudo visé appartient à un **autre** compte : réutiliser le sien
        // ne violerait rien, UniqueEntity ignorant l'entité courante.
        $this->creerEtAuthentifier($client, "voisin_{$suffix}");
        $this->patch($client, $auth, "/api/utilisateurs/{$utilisateur}", ['pseudo' => "profil_voisin_{$suffix}"]);

        // 422 et non 500 : sans UniqueEntity, c'est l'index unique de PostgreSQL
        // qui parlait, et l'erreur remontait en erreur serveur.
        self::assertResponseStatusCodeSame(422);
        // ⚠️ Le corps JSON échappe les accents (« d\u00e9j\u00e0 ») : une
        // assertion sur la chaîne brute échouerait alors que la réponse est
        // juste. On lit donc la violation décodée.
        $violations = $this->decoder($client)['violations'];
        self::assertSame('pseudo', $violations[0]['propertyPath']);
        self::assertSame('Ce pseudo est déjà pris.', $violations[0]['message']);
    }

    public function testPseudoVideRefuse(): void
    {
        $client = static::createClient();
        $suffix = bin2hex(random_bytes(4));
        [$auth, $utilisateur] = $this->creerEtAuthentifier($client, $suffix);

        $this->patch($client, $auth, "/api/utilisateurs/{$utilisateur}", ['pseudo' => '']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(
            'pseudo',
            $this->decoder($client)['violations'][0]['propertyPath'],
            'La validation doit porter sur le pseudo, pas sur le mot de passe d\'inscription.',
        );
    }

    /**
     * `PATCH` exige `application/merge-patch+json` : avec `application/json`,
     * API Platform interprète la requête comme un remplacement complet et non
     * comme une fusion. L'opérateur `+` de PHP ne suffisait pas à poser
     * l'en-tête, puisqu'il ne remplace pas une clé déjà présente.
     *
     * @param array{headers: array<string, string>} $auth
     * @param array<string, mixed> $charge
     */
    private function patch(Client $client, array $auth, string $uri, array $charge): void
    {
        $client->request('PATCH', $uri, [
            'headers' => $auth['headers'] + ['Content-Type' => 'application/merge-patch+json'],
            'json' => $charge,
        ]);
    }

    /** @return array{0: array{headers: array<string, string>}, 1: int} */
    private function creerEtAuthentifier(Client $client, string $suffix): array
    {
        $email = "profil_{$suffix}@example.test";
        $motDePasse = "Mot-De-Passe-Profil-{$suffix}!";

        $client->request('POST', '/api/utilisateurs', [
            'headers' => ['Accept' => 'application/json'],
            'json' => ['pseudo' => "profil_{$suffix}", 'email' => $email, 'plainPassword' => $motDePasse],
        ]);
        self::assertResponseStatusCodeSame(201);
        $utilisateur = $this->decoder($client)['idUtilisateur'];

        $client->request('POST', '/api/login_check', [
            'headers' => ['Accept' => 'application/json'],
            'json' => ['email' => $email, 'password' => $motDePasse],
        ]);
        self::assertResponseIsSuccessful();
        $token = json_decode((string) $client->getResponse()->getContent(), true)['token'];

        return [['headers' => ['Accept' => 'application/json', 'Authorization' => "Bearer {$token}"]], $utilisateur];
    }

    /** @param array{headers: array<string, string>} $auth */
    private function creerMouvement(Client $client, array $auth, string $suffix, int $rang): int
    {
        $client->request('POST', '/api/livres', $auth + ['json' => [
            'titre' => "Historique {$suffix} #{$rang}",
            'auteur' => 'Suite F9',
            'categorie' => 'Test',
        ]]);
        self::assertResponseStatusCodeSame(201);
        $livre = $this->decoder($client)['idLivre'];

        $client->request('POST', '/api/exemplaires', $auth + ['json' => [
            'livre' => "/api/livres/{$livre}",
        ]]);
        self::assertResponseStatusCodeSame(201);
        $exemplaire = $this->decoder($client)['idExemplaire'];

        $client->request('POST', '/api/mouvements', $auth + ['json' => [
            'exemplaire' => "/api/exemplaires/{$exemplaire}",
            'typeMouvement' => 'liberation',
            'latitude' => '48.844300',
            'longitude' => '2.374300',
            'message' => "Libération de contrôle #{$rang}.",
        ]]);
        self::assertResponseStatusCodeSame(201);

        return $this->decoder($client)['idMouvement'];
    }

    /**
     * ⚠️ `getContent(false)` : sans le drapeau, `Response::getContent()` lève
     * dès que le code est un 4xx — or plusieurs tests décodent ici une réponse
     * d'erreur attendue (422), ce qui faisait échouer le test sur une exception
     * au lieu de l'assertion.
     *
     * @return array<string, mixed>
     */
    private function decoder(Client $client): array
    {
        return json_decode((string) $client->getResponse()->getContent(false), true);
    }
}
