<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Utilisateur;
use App\Enum\RoleUtilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Promotion admin volontairement absente de l'API HTTP : le seul chemin
 * est cette commande console, exécutable uniquement avec un accès serveur
 * (SSH/exec Docker). UtilisateurProcessor force RoleUtilisateur::Membre à
 * l'inscription publique — ce comportement reste intact, cette commande
 * ne le contourne pas, elle agit après coup sur un compte existant.
 */
#[AsCommand(
    name: 'app:user:promote-admin',
    description: 'Promeut un compte existant au rôle admin (aucun équivalent HTTP, volontairement).',
)]
final class PromoteAdminCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, "Email du compte à promouvoir");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');

        $utilisateur = $this->em->getRepository(Utilisateur::class)->findOneBy(['email' => $email]);
        if (!$utilisateur instanceof Utilisateur) {
            $io->error(sprintf('Aucun compte trouvé pour "%s".', $email));

            return Command::FAILURE;
        }

        if ($utilisateur->getRole() === RoleUtilisateur::Admin) {
            $io->note(sprintf('%s est déjà admin.', $email));

            return Command::SUCCESS;
        }

        $utilisateur->setRole(RoleUtilisateur::Admin);
        $this->em->flush();

        $io->success(sprintf('%s (%s) est maintenant admin.', $utilisateur->getPseudo(), $email));

        return Command::SUCCESS;
    }
}
