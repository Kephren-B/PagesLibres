<?php

declare(strict_types=1);

namespace App\Tests\Validator;

use App\Entity\Avis;
use App\Entity\Commentaire;
use App\Entity\Livre;
use App\Entity\Signalement;
use App\Validator\ExactlyOneTarget;
use App\Validator\ExactlyOneTargetValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * Cas valides et invalides pour Commentaire (avis XOR livre) et
 * Signalement (exactement une cible parmi 4) — réplique applicative des
 * contraintes CHECK chk_commentaire_une_cible et chk_signalement_une_cible
 * du MPD.
 */
final class ExactlyOneTargetValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): ExactlyOneTargetValidator
    {
        return new ExactlyOneTargetValidator();
    }

    public function testCommentaireValideAvecUniquementAvis(): void
    {
        $commentaire = (new Commentaire())->setAvis(new Avis())->setContenu('Un commentaire.');

        $this->validator->validate($commentaire, new ExactlyOneTarget(fields: ['avis', 'livre']));

        $this->assertNoViolation();
    }

    public function testCommentaireValideAvecUniquementLivre(): void
    {
        $commentaire = (new Commentaire())->setLivre(new Livre())->setContenu('Un commentaire.');

        $this->validator->validate($commentaire, new ExactlyOneTarget(fields: ['avis', 'livre']));

        $this->assertNoViolation();
    }

    public function testCommentaireInvalideSansCible(): void
    {
        $commentaire = (new Commentaire())->setContenu('Un commentaire orphelin.');

        $this->validator->validate(
            $commentaire,
            new ExactlyOneTarget(fields: ['avis', 'livre'], message: 'msg')
        );

        $this->buildViolation('msg')
            ->setParameter('{{ fields }}', 'avis, livre')
            ->assertRaised();
    }

    public function testCommentaireInvalideAvecLesDeuxCibles(): void
    {
        $commentaire = (new Commentaire())
            ->setAvis(new Avis())
            ->setLivre(new Livre())
            ->setContenu('Un commentaire ambigu.');

        $this->validator->validate(
            $commentaire,
            new ExactlyOneTarget(fields: ['avis', 'livre'], message: 'msg')
        );

        $this->buildViolation('msg')
            ->setParameter('{{ fields }}', 'avis, livre')
            ->assertRaised();
    }

    public function testSignalementValideAvecUneSeuleCible(): void
    {
        $signalement = (new Signalement())->setLivre(new Livre())->setMotif('Contenu inapproprié');

        $this->validator->validate(
            $signalement,
            new ExactlyOneTarget(fields: ['livre', 'exemplaire', 'avis', 'commentaire'])
        );

        $this->assertNoViolation();
    }

    public function testSignalementInvalideSansCible(): void
    {
        $signalement = (new Signalement())->setMotif('Motif sans cible');

        $this->validator->validate(
            $signalement,
            new ExactlyOneTarget(fields: ['livre', 'exemplaire', 'avis', 'commentaire'], message: 'msg')
        );

        $this->buildViolation('msg')
            ->setParameter('{{ fields }}', 'livre, exemplaire, avis, commentaire')
            ->assertRaised();
    }

    public function testSignalementInvalideAvecPlusieursCibles(): void
    {
        $signalement = (new Signalement())
            ->setLivre(new Livre())
            ->setAvis(new Avis())
            ->setMotif('Motif ambigu');

        $this->validator->validate(
            $signalement,
            new ExactlyOneTarget(fields: ['livre', 'exemplaire', 'avis', 'commentaire'], message: 'msg')
        );

        $this->buildViolation('msg')
            ->setParameter('{{ fields }}', 'livre, exemplaire, avis, commentaire')
            ->assertRaised();
    }
}
