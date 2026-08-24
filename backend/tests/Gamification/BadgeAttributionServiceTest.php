<?php

declare(strict_types=1);

namespace App\Tests\Gamification;

use App\Entity\Exemplaire;
use App\Entity\Livre;
use App\Entity\Mouvement;
use App\Entity\ObtentionBadge;
use App\Entity\Utilisateur;
use App\Enum\RoleUtilisateur;
use App\Enum\TypeMouvement;
use App\Gamification\BadgeAttributionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * F9 : le badge "premiere_liberation" doit être attribué après la
 * première libération, et jamais deux fois (UNIQUE (id_utilisateur,
 * id_badge) en base ; comportement applicatif attendu : ignorer
 * silencieusement, pas planter).
 */
final class BadgeAttributionServiceTest extends KernelTestCase
{
    public function testBadgeAttribueUneSeuleFois(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var BadgeAttributionService $service */
        $service = $container->get(BadgeAttributionService::class);

        $suffix = bin2hex(random_bytes(4));
        $utilisateur = (new Utilisateur())
            ->setPseudo("badge_{$suffix}")
            ->setEmail("badge_{$suffix}@example.test")
            ->setMotDePasseHash('x')
            ->setRole(RoleUtilisateur::Membre);
        $em->persist($utilisateur);

        $livre = (new Livre())->setTitre('Badge Test')->setAuteur('X')->setCategorie('Test');
        $em->persist($livre);

        $exemplaire = (new Exemplaire())->setLivre($livre)->setCodeBcid("BADGE-{$suffix}");
        $em->persist($exemplaire);
        $em->flush();

        $mouvement = (new Mouvement())
            ->setExemplaire($exemplaire)
            ->setUtilisateur($utilisateur)
            ->setTypeMouvement(TypeMouvement::Liberation)
            ->setLatitude('48.858370')
            ->setLongitude('2.294481');
        $em->persist($mouvement);
        $em->flush();

        // 1er appel : le badge "Premier envol" doit être attribué.
        $service->evaluerEtAttribuer($utilisateur);

        $obtentions = $em->getRepository(ObtentionBadge::class)->findBy(['utilisateur' => $utilisateur]);
        self::assertCount(1, $obtentions, 'Le badge "premiere_liberation" doit être attribué après la première libération.');
        self::assertSame('premiere_liberation', $obtentions[0]->getBadge()->getCritereType());

        // Appels répétés : jamais de doublon (ni erreur, ni nouvelle ligne).
        $service->evaluerEtAttribuer($utilisateur);
        $service->evaluerEtAttribuer($utilisateur);

        $obtentionsApresRepetition = $em->getRepository(ObtentionBadge::class)->findBy(['utilisateur' => $utilisateur]);
        self::assertCount(1, $obtentionsApresRepetition, 'Un badge ne doit jamais être attribué deux fois au même utilisateur.');
    }
}
