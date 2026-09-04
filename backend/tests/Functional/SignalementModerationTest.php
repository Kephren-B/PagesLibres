<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Utilisateur;
use App\Enum\RoleUtilisateur;
use Doctrine\ORM\EntityManagerInterface;

/**
 * F10 : un membre signale une fiche livre, un admin traite le
 * signalement. Vérifie aussi le point de contrôle d'accès explicitement
 * demandé : un membre non-admin appelant l'endpoint de modération doit
 * recevoir 403, jamais 200.
 */
final class SignalementModerationTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = true;

    /**
     * Réutilise le même client pour tout le test : ApiTestCase/WebTestCase
     * ne suit qu'un seul client "courant" pour les assertions de réponse
     * (self::assertResponseStatusCodeSame etc.) — appeler
     * static::createClient() plusieurs fois désynchronise ce suivi et fait
     * échouer les assertions contre la mauvaise réponse.
     *
     * @return array{0: string, 1: string, 2: array}
     */
    private function inscrireEtConnecter(object $client, string $prefix): array
    {
        $suffix = bin2hex(random_bytes(4));
        $pseudo = "{$prefix}_{$suffix}";
        $email = "{$pseudo}@example.test";
        $password = 'un-mot-de-passe-solide';

        $client->request('POST', '/api/utilisateurs', [
            'headers' => ['Accept' => 'application/json'],
            'json' => ['pseudo' => $pseudo, 'email' => $email, 'plainPassword' => $password],
        ]);
        self::assertResponseStatusCodeSame(201);

        $client->request('POST', '/api/login_check', [
            'headers' => ['Accept' => 'application/json'],
            'json' => ['email' => $email, 'password' => $password],
        ]);
        self::assertResponseIsSuccessful();
        $token = json_decode($client->getResponse()->getContent(), true)['token'];

        return [$pseudo, $email, ['headers' => ['Accept' => 'application/json', 'Authorization' => "Bearer {$token}"]]];
    }

    public function testParcoursModeration(): void
    {
        $client = static::createClient();

        [, , $authSignaleur] = $this->inscrireEtConnecter($client, 'signaleur');
        [$pseudoAdmin, $emailAdmin, $authAdmin] = $this->inscrireEtConnecter($client, 'admin');
        [, , $authAutreMembre] = $this->inscrireEtConnecter($client, 'intrus');

        // Promotion admin : uniquement via la commande console (jamais via
        // l'API), cf. App\Command\PromoteAdminCommand — on simule ici le
        // même effet directement en base pour le test.
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $admin = $em->getRepository(Utilisateur::class)->findOneBy(['email' => $emailAdmin]);
        $admin->setRole(RoleUtilisateur::Admin);
        $em->flush();

        // Un livre à signaler.
        $client->request('POST', '/api/livres', $authSignaleur + ['json' => [
            'titre' => 'Livre à signaler',
            'auteur' => 'X',
            'categorie' => 'Test',
        ]]);
        self::assertResponseStatusCodeSame(201);
        $livre = json_decode($client->getResponse()->getContent(), true);
        $livreIri = "/api/livres/{$livre['idLivre']}";

        // --- F10 : création du signalement (membre authentifié) ---
        $client->request('POST', '/api/signalements', $authSignaleur + ['json' => [
            'livre' => $livreIri,
            'motif' => 'Contenu inapproprié',
        ]]);
        self::assertResponseStatusCodeSame(201);
        $signalement = json_decode($client->getResponse()->getContent(), true);
        $signalementIri = "/api/signalements/{$signalement['idSignalement']}";
        self::assertSame('en_attente', $signalement['statut']);

        // --- F10 (négatif) : ExactlyOneTarget s'applique aussi ici (aucune cible) ---
        $client->request('POST', '/api/signalements', $authSignaleur + ['json' => [
            'motif' => 'Signalement sans cible',
        ]]);
        self::assertResponseStatusCodeSame(422, 'Un signalement sans aucune cible doit être rejeté.');

        // --- Contrôle d'accès : la liste des signalements est réservée à l'admin ---
        $client->request('GET', '/api/signalements', $authSignaleur);
        self::assertResponseStatusCodeSame(403, 'Un membre non-admin ne doit pas pouvoir lister les signalements.');

        // --- Contrôle d'accès : le traitement est réservé à l'admin ---
        $client->request('POST', "{$signalementIri}/traiter", $authAutreMembre);
        self::assertResponseStatusCodeSame(403, 'Un membre non-admin ne doit jamais pouvoir traiter un signalement.');

        // --- L'admin peut lister ---
        $client->request('GET', '/api/signalements?statut=en_attente', $authAdmin);
        self::assertResponseIsSuccessful();

        // --- L'admin traite le signalement ---
        $client->request('POST', "{$signalementIri}/traiter", $authAdmin);
        self::assertResponseIsSuccessful();
        $traite = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('traite', $traite['statut']);
        self::assertNotNull($traite['dateTraitement']);
        self::assertSame($pseudoAdmin, $traite['utilisateurTraitant']['pseudo'] ?? null, 'Le traitant doit être l\'admin authentifié.');
    }
}
