<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;

/**
 * Politique de robustesse des mots de passe (F1, Jalon 5 § IX).
 *
 * Trois contrôles sont éprouvés par l'API d'inscription, et non par le
 * validateur seul : c'est le refus réel que voit un client qui compte.
 *
 * ⚠️ `NotCompromisedPassword` interroge l'API publique « Pwned Passwords ». Les
 * mots de passe utilisés ici sont assez rares pour ne pas y figurer : le test
 * reste donc stable, même si la CI a accès au réseau.
 */
final class MotDePasseRobustesseTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = true;

    /** @return array<string, array{string}> Les mots de passe refusés, par libellé. */
    public static function motsDePasseRefuses(): array
    {
        return [
            'trop court' => ['Court1!'],
            'huit chiffres' => ['12345678'],
            'une suite de touches' => ['azertyuiop'],
            'un mot du dictionnaire' => ['motdepasse'],
            'trois classes mais devinable' => ['Admin2026!'],
            'au-delà de la limite' => [str_repeat('Ab1!.', 40)],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('motsDePasseRefuses')]
    public function testMotDePasseRefuse(string $motDePasse): void
    {
        $client = static::createClient();
        $suffix = bin2hex(random_bytes(4));

        $client->request('POST', '/api/utilisateurs', [
            'headers' => ['Accept' => 'application/json'],
            'json' => [
                'pseudo' => "robuste_{$suffix}",
                'email' => "robuste_{$suffix}@example.test",
                'plainPassword' => $motDePasse,
            ],
        ]);

        // 422 : la violation de validation est renvoyée côté API Platform.
        // ⚠️ getResponse()->getContent() lève une exception dès que le code est
        // un 4xx (Response::getContent($throw = true)) : sur une réponse
        // d'erreur attendue, il faut lire le corps avec getContent(false).
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString(
            'plainPassword',
            (string) $client->getResponse()->getContent(false),
        );
    }

    public function testMotDePasseSolideAccepte(): void
    {
        $client = static::createClient();
        $suffix = bin2hex(random_bytes(4));
        $motDePasse = "Un-Mot-De-Passe-Vraiment-Long-{$suffix}!";

        $client->request('POST', '/api/utilisateurs', [
            'headers' => ['Accept' => 'application/json'],
            'json' => [
                'pseudo' => "solide_{$suffix}",
                'email' => "solide_{$suffix}@example.test",
                'plainPassword' => $motDePasse,
            ],
        ]);

        self::assertResponseStatusCodeSame(201);

        // Le mot de passe haché n'est jamais renvoyé au client.
        self::assertStringNotContainsString($motDePasse, (string) $client->getResponse()->getContent());

        // Et il permet réellement de se connecter.
        $client->request('POST', '/api/login_check', [
            'headers' => ['Accept' => 'application/json'],
            'json' => ['email' => "solide_{$suffix}@example.test", 'password' => $motDePasse],
        ]);
        self::assertResponseIsSuccessful();
    }

    /**
     * Le mot de passe du jeu de démonstration doit rester accepté : le durcir
     * sans le vérifier aurait rendu les quatre comptes de démonstration
     * impossibles à recréer.
     */
    public function testMotDePasseDuJeuDeDemonstrationAccepte(): void
    {
        $client = static::createClient();
        $suffix = bin2hex(random_bytes(4));

        $client->request('POST', '/api/utilisateurs', [
            'headers' => ['Accept' => 'application/json'],
            'json' => [
                'pseudo' => "demo_{$suffix}",
                'email' => "demo_{$suffix}@example.test",
                'plainPassword' => 'DemoPagesLibres2026!',
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
    }
}
