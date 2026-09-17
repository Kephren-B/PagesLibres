<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;

/**
 * F8 — recherche et filtres (catégorie, statut), et F4 côté carte : les mêmes
 * filtres sur la route de proximité. C'était l'angle mort de la suite : aucune
 * requête filtrée n'était exercée avant ce test.
 *
 * Les données sont créées via l'API avec des valeurs suffixées aléatoirement,
 * la base de test n'étant pas remise à zéro entre deux exécutions. Chaque
 * assertion reste donc exacte et insensible à l'accumulation.
 */
final class FiltresF8Test extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = true;

    private const LAT_PARIS = '48.856600';
    private const LON_PARIS = '2.352200';

    public function testFiltresCategorieEtStatut(): void
    {
        $client = static::createClient();
        $suffix = bin2hex(random_bytes(4));
        $categorieA = "Roman-{$suffix}";
        $categorieB = "BD-{$suffix}";
        $bcidA = "F8A-{$suffix}";
        $bcidB = "F8B-{$suffix}";

        $auth = $this->authentifier($client, $suffix);

        $livreA = $this->creerLivreEtExemplaireLibere($client, $auth, "Filtre A {$suffix}", $categorieA, $bcidA, self::LAT_PARIS, self::LON_PARIS);
        $livreB = $this->creerLivreEtExemplaireLibere($client, $auth, "Filtre B {$suffix}", $categorieB, $bcidB, '48.864000', '2.360000');

        // --- Catalogue : filtre par catégorie ---
        self::assertSame(
            [$livreA],
            $this->idsLivres($client, $auth, "?categorie={$categorieA}"),
            'Le filtre catégorie ne doit renvoyer que les livres de cette catégorie.',
        );

        // --- Catalogue : la catégorie se combine à la recherche par titre ---
        self::assertSame(
            [$livreB],
            $this->idsLivres($client, $auth, "?titre=Filtre&categorie={$categorieB}"),
            'La recherche par titre et le filtre catégorie doivent pouvoir se combiner.',
        );

        // --- Catalogue : catégorie inconnue ---
        self::assertSame([], $this->idsLivres($client, $auth, "?categorie=inexistante-{$suffix}"));

        // --- Exemplaires : filtre par statut ---
        // Assertions ciblées sur un BCID connu : elles restent justes quels que
        // soient les exemplaires laissés par les exécutions précédentes.
        self::assertCount(
            1,
            $this->bcidsExemplaires($client, $auth, "?codeBcid={$bcidA}&statut=en_circulation"),
        );
        self::assertCount(
            0,
            $this->bcidsExemplaires($client, $auth, "?codeBcid={$bcidA}&statut=trouve"),
            'Un exemplaire en circulation ne doit pas remonter dans le filtre "trouve" (en lecture).',
        );

        // --- Proximité (F4) : sans filtre, les deux exemplaires sont à portée ---
        $proches = $this->bcidsProximite($client, $auth, '');
        self::assertContains($bcidA, $proches);
        self::assertContains($bcidB, $proches);

        // --- Proximité : filtre par catégorie ---
        self::assertSame(
            [$bcidB],
            $this->bcidsProximite($client, $auth, "&categorie={$categorieB}"),
            'La catégorie étant propre à ce jeu de test, un seul exemplaire doit ressortir.',
        );

        // --- F5 : une trouvaille fait passer l'exemplaire « en lecture » ---
        $client->request('POST', '/api/mouvements', $auth + ['json' => [
            'exemplaire' => "/api/exemplaires/{$this->idExemplaire($client, $auth, $bcidA)}",
            'typeMouvement' => 'trouvaille',
            'latitude' => self::LAT_PARIS,
            'longitude' => self::LON_PARIS,
            'message' => 'Trouvé pendant la suite de tests F8.',
        ]]);
        self::assertResponseStatusCodeSame(201);

        self::assertCount(
            1,
            $this->bcidsExemplaires($client, $auth, "?codeBcid={$bcidA}&statut=trouve"),
            'Après une trouvaille, l\'exemplaire doit se retrouver dans le filtre "trouve".',
        );

        // --- Proximité : le filtre de statut change le jeu de résultats ---
        $proches = $this->bcidsProximite($client, $auth, '&statut=trouve');
        self::assertContains($bcidA, $proches);
        self::assertNotContains($bcidB, $proches, 'Un exemplaire en circulation ne doit pas apparaître dans le filtre "en lecture".');

        // --- Statut hors vocabulaire : 400 explicite, jamais une 500 de cast SQL ---
        $client->request(
            'GET',
            '/api/exemplaires/proximite?lat=' . self::LAT_PARIS . '&lon=' . self::LON_PARIS . '&statut=nimportequoi',
            $auth,
        );
        self::assertResponseStatusCodeSame(400);
    }

    /**
     * @return array{headers: array<string, string>}
     */
    private function authentifier(Client $client, string $suffix): array
    {
        $email = "filtres_{$suffix}@example.test";
        $motDePasse = 'un-mot-de-passe-solide';

        $client->request('POST', '/api/utilisateurs', [
            'headers' => ['Accept' => 'application/json'],
            'json' => ['pseudo' => "filtres_{$suffix}", 'email' => $email, 'plainPassword' => $motDePasse],
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

    /**
     * Crée un livre, son exemplaire, puis le libère (ce qui renseigne sa position
     * via le trigger SQL) et renvoie l'identifiant du livre créé.
     *
     * @param array{headers: array<string, string>} $auth
     */
    private function creerLivreEtExemplaireLibere(
        Client $client,
        array $auth,
        string $titre,
        string $categorie,
        string $bcid,
        string $lat,
        string $lon,
    ): int {
        $client->request('POST', '/api/livres', $auth + ['json' => [
            'titre' => $titre,
            'auteur' => 'Suite F8',
            'categorie' => $categorie,
        ]]);
        self::assertResponseStatusCodeSame(201);
        $livre = json_decode((string) $client->getResponse()->getContent(), true);

        $client->request('POST', '/api/exemplaires', $auth + ['json' => [
            'livre' => "/api/livres/{$livre['idLivre']}",
            'codeBcid' => $bcid,
        ]]);
        self::assertResponseStatusCodeSame(201);
        $exemplaire = json_decode((string) $client->getResponse()->getContent(), true);

        $client->request('POST', '/api/mouvements', $auth + ['json' => [
            'exemplaire' => "/api/exemplaires/{$exemplaire['idExemplaire']}",
            'typeMouvement' => 'liberation',
            'latitude' => $lat,
            'longitude' => $lon,
        ]]);
        self::assertResponseStatusCodeSame(201);

        return $livre['idLivre'];
    }

    /** @param array{headers: array<string, string>} $auth */
    private function idExemplaire(Client $client, array $auth, string $bcid): int
    {
        $client->request('GET', "/api/exemplaires?codeBcid={$bcid}", $auth);
        self::assertResponseIsSuccessful();

        return $this->decoder($client)[0]['idExemplaire'];
    }

    /**
     * @param array{headers: array<string, string>} $auth
     *
     * @return list<int>
     */
    private function idsLivres(Client $client, array $auth, string $query): array
    {
        $client->request('GET', "/api/livres{$query}", $auth);
        self::assertResponseIsSuccessful();

        return array_column($this->decoder($client), 'idLivre');
    }

    /**
     * @param array{headers: array<string, string>} $auth
     *
     * @return list<string>
     */
    private function bcidsExemplaires(Client $client, array $auth, string $query): array
    {
        $client->request('GET', "/api/exemplaires{$query}", $auth);
        self::assertResponseIsSuccessful();

        return array_column($this->decoder($client), 'codeBcid');
    }

    /**
     * @param array{headers: array<string, string>} $auth
     *
     * @return list<string>
     */
    private function bcidsProximite(Client $client, array $auth, string $filtres): array
    {
        $url = '/api/exemplaires/proximite?lat=' . self::LAT_PARIS . '&lon=' . self::LON_PARIS . '&rayon=20000' . $filtres;
        $client->request('GET', $url, $auth);
        self::assertResponseIsSuccessful();

        return array_column($this->decoder($client), 'codeBcid');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decoder(Client $client): array
    {
        $donnees = json_decode((string) $client->getResponse()->getContent(), true);

        return is_array($donnees) ? array_values($donnees) : [];
    }
}
