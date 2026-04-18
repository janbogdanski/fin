<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Console;

use App\Identity\Application\Port\MagicLinkTokenGeneratorPort;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsCommand(
    name: 'app:dev:login-link',
    description: 'Generate a magic login link for local development. Do NOT use in production.',
)]
final class GenerateDevLoginLinkCommand extends Command
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly MagicLinkTokenGeneratorPort $tokenGenerator,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $appEnv,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::OPTIONAL, 'User email', 'dev@taxpilot.local');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (! in_array($this->appEnv, ['dev', 'test'], true)) {
            $output->writeln('<error>This command is only allowed in dev/test environments.</error>');

            return Command::FAILURE;
        }

        $email = (string) $input->getArgument('email');
        $user = $this->userRepository->findByEmail($email);

        if ($user === null) {
            $output->writeln(sprintf('<error>User "%s" not found. Run: make seed</error>', $email));

            return Command::FAILURE;
        }

        $token = $this->tokenGenerator->generate($user);
        $user->setMagicLinkToken($token);
        $this->userRepository->save($user);
        $this->userRepository->flush();

        $url = $this->urlGenerator->generate(
            'auth_verify',
            [
                'token' => $token->token(),
            ],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $output->writeln('');
        $output->writeln('<info>Login link (valid 15 min):</info>');
        $output->writeln($url);
        $output->writeln('');
        $output->writeln('<comment>WARNING: This URL is an authentication credential. Do not share, log, or commit it.</comment>');
        $output->writeln('');

        return Command::SUCCESS;
    }
}
