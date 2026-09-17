<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Exemplaire;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function sprintf;
use function strlen;
use function trim;

/**
 * Libération d'un exemplaire (F3) : le BCID est **attribué par la plateforme**,
 * pas saisi par le membre. Le cahier des charges est explicite — « création d'un
 * exemplaire physique (BCID unique généré) » — et c'est aussi le geste réel du
 * bookcrossing : le site délivre le code, le membre l'inscrit dans le livre.
 *
 * Format : `PL-XXXXX-FR`, comme les codes du jeu de démonstration. L'alphabet
 * exclut I, O, 0 et 1 — un BCID se lit sur un papier, souvent à la main, et se
 * recopie dans un formulaire : les caractères ambigus y produisent des erreurs
 * de saisie.
 *
 * Le tirage passe par `random_int` (source cryptographique) : un BCID devinable
 * permettrait de déclarer la trouvaille d'un livre qu'on n'a jamais eu.
 *
 * Un code fourni par le client est conservé tel quel : le jeu de démonstration
 * et les tests s'en servent, et rien n'exige de l'interdire à un membre. Le
 * doublon, lui, est refusé par UniqueEntity sur l'entité (422, pas 500).
 *
 * @implements ProcessorInterface<Exemplaire, Exemplaire>
 */
final class ExemplaireProcessor implements ProcessorInterface
{
    /** Sans I, O, 0 ni 1 : 32 caractères, soit 33 millions de combinaisons. */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const TENTATIVES = 10;

    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof Exemplaire && trim((string) $data->getCodeBcid()) === '') {
            $data->setCodeBcid($this->genererCodeUnique());
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }

    private function genererCodeUnique(): string
    {
        $depot = $this->entityManager->getRepository(Exemplaire::class);

        for ($tentative = 0; $tentative < self::TENTATIVES; ++$tentative) {
            $code = sprintf('PL-%s-FR', $this->cinqCaracteres());

            if (null === $depot->findOneBy(['codeBcid' => $code])) {
                return $code;
            }
        }

        // Statistiquement inatteignable ; l'index UNIQUE de la table reste le
        // dernier rempart, mais autant échouer avec un message explicite.
        throw new RuntimeException('Impossible de générer un code BCID unique après ' . self::TENTATIVES . ' tentatives.');
    }

    private function cinqCaracteres(): string
    {
        $code = '';

        for ($i = 0; $i < 5; ++$i) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $code;
    }
}
