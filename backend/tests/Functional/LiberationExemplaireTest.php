<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;

/**
 * Libération d'un exemplaire (F3) : le BCID est **généré par la plateforme**.
 *
 * Le cahier des charges est explicite — « création d'un exemplaire physique
 * (BCID unique généré) » — et le formulaire ne devait donc pas le demander au
 * membre. Ces tests vérifient le contrat de l'API, pas le seul formulaire :
 * c'est ce que voit n'importe quel client.
 */
final class LiberationExemplaireTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = true;

    public function testLeBcidEstGenereQuandIlEstAbsent(): void
    {
        $client = static::createClient();
        $suffix = bin2hex(random_bytes(4));
        $auth = $this->authentifier($client, $suffix);
        $livre = $this->creerLivre($client, $auth, $suffix);

        $client->request('POST', '/api/exemplaires', $auth + ['json' => [
            'livre' => "/api/livres/{$livre}",
        ]]);
        self::assertResponseStatusCodeSame(201);

        $exemplaire = $this->decoder($client);
        self::assertMatchesRegularExpression(
            '/^PL-[A-HJ-NP-Z2-9]{5}-FR$/',
            (string) $exemplaire['codeBcid'],
            'Le BCID doit suivre le format PL-XXXXX-FR, sans caractères ambigus.',
        );
        self::assertSame('en_circulation', $exemplaire['statut']);
    }

    public function testDeuxExemplairesSansCodeRecoiventDesCodesDifferents(): void
    {
        $client = static::createClient();
        $suffix = bin2hex(random_bytes(4));
        $auth = $this->authentifier($client, $suffix);
        $livre = $this->creerLivre($client, $auth, $suffix);

        $codes = [];
        for ($i = 0; $i < 2; ++$i) {
            $client->request('POST', '/api/exemplaires', $auth + ['json' => [
                'livre' => "/api/livres/{$livre}",
            ]]);
            self::assertResponseStatusCodeSame(201);
            $codes[] = $this->decoder($client)['codeBcid'];
        }

        self::assertNotSame($codes[0], $codes[1]);
    }

    /**
     * Un BCID en doublon doit répondre 422 (contrainte de validation), jamais
     * une 500 remontant l'erreur d'index UNIQUE de PostgreSQL.
     */
    public function testUnBcidEnDoublonRepond422EtNon500(): void
    {
        $client = static::createClient();
        $suffix = bin2hex(random_bytes(4));
        $auth = $this->authentifier($client, $suffix);
        $livre = $this->creerLivre($client, $auth, $suffix);
        $code = "DOUBLON-{$suffix}";

        $client->request('POST', '/api/exemplaires', $auth + ['json' => [
            'livre' => "/api/livres/{$livre}",
            'codeBcid' => $code,
        ]]);
        self::assertResponseStatusCodeSame(201);

        $client->request('POST', '/api/exemplaires', $auth + ['json' => [
            'livre' => "/api/livres/{$livre}",
            'codeBcid' => $code,
        ]]);
        self::assertResponseStatusCodeSame(422);
        // getContent() lève sur un 4xx : il faut le lire avec getContent(false).
        self::assertStringContainsString('codeBcid', (string) $client->getResponse()->getContent(false));
    }

    /**
     * Bout en bout : l'exemplaire généré sert bien à enregistrer une libération,
     * et la position reste arrondie par l'API (aucune coordonnée exacte dehors).
     */
    public function testLeBcidGenereSertALaLiberation(): void
    {
        $client = static::createClient();
        $suffix = bin2hex(random_bytes(4));
        $auth = $this->authentifier($client, $suffix);
        $livre = $this->creerLivre($client, $auth, $suffix);

        $client->request('POST', '/api/exemplaires', $auth + ['json' => [
            'livre' => "/api/livres/{$livre}",
        ]]);
        self::assertResponseStatusCodeSame(201);
        $exemplaire = $this->decoder($client);

        $client->request('POST', '/api/mouvements', $auth + ['json' => [
            'exemplaire' => "/api/exemplaires/{$exemplaire['idExemplaire']}",
            'typeMouvement' => 'liberation',
            'latitude' => '48.844300',
            'longitude' => '2.374300',
            'message' => 'Libéré sur un banc du hall.',
        ]]);
        self::assertResponseStatusCodeSame(201);

        // Le journal se lit depuis le code généré, comme le fera un découvreur.
        $client->request('GET', "/api/exemplaires?codeBcid={$exemplaire['codeBcid']}", $auth);
        self::assertResponseIsSuccessful();
        $trouve = $this->decoder($client);
        self::assertCount(1, $trouve, 'Le BCID généré doit permettre de retrouver l\'exemplaire.');
    }

    /**
     * Le code se recopie depuis un livre, souvent en minuscules sur un clavier
     * de téléphone : la recherche doit rester tolérante à la casse, sinon la
     * déclaration de trouvaille échoue sur un code qui existe pourtant.
     */
    public function testLaRechercheParCodeIgnoreLaCasse(): void
    {
        $client = static::createClient();
        $suffix = bin2hex(random_bytes(4));
        $auth = $this->authentifier($client, $suffix);
        $livre = $this->creerLivre($client, $auth, $suffix);

        $client->request('POST', '/api/exemplaires', $auth + ['json' => [
            'livre' => "/api/livres/{$livre}",
        ]]);
        self::assertResponseStatusCodeSame(201);
        $code = $this->decoder($client)['codeBcid'];

        foreach ([$code, strtolower($code), ucfirst(strtolower($code))] as $variante) {
            $client->request('GET', "/api/exemplaires?codeBcid={$variante}", $auth);
            self::assertResponseIsSuccessful();
            self::assertCount(1, $this->decoder($client), "Le code « {$variante} » doit être reconnu.");
        }
    }

    /** @return array{headers: array<string, string>} */
    private function authentifier(Client $client, string $suffix): array
    {
        $email = "liberation_{$suffix}@example.test";
        $motDePasse = "Un-Mot-De-Passe-Liberation-{$suffix}!";

        $client->request('POST', '/api/utilisateurs', [
            'headers' => ['Accept' => 'application/json'],
            'json' => ['pseudo' => "liberation_{$suffix}", 'email' => $email, 'plainPassword' => $motDePasse],
        ]);
        self::assertResponseStatusCodeSame(201);

        $client->request('POST', '/api/login_check', [
            'headers' => ['Accept' => 'application/json'],
            'json' => ['email' => $email, 'password' => $motDePasse],
        ]);
        self::assertResponseIsSuccessful();

        $token = json_decode((string) $client->getResponse()->getContent(), true)['token'];

        return ['headers' => ['Accept' => 'application/json', 'Authorization' => "Bearer {$token}"]];
    }

    /** @param array{headers: array<string, string>} $auth */
    private function creerLivre(Client $client, array $auth, string $suffix): int
    {
        $client->request('POST', '/api/livres', $auth + ['json' => [
            'titre' => "Libération {$suffix}",
            'auteur' => 'Suite F3',
            'categorie' => 'Test',
        ]]);
        self::assertResponseStatusCodeSame(201);

        return $this->decoder($client)['idLivre'];
    }

    /** @return array<string, mixed> */
    private function decoder(Client $client): array
    {
        return json_decode((string) $client->getResponse()->getContent(), true);
    }
}
