<?php
namespace App\Command;

use App\Entity\User;
use App\Entity\Admin;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:create-admin-user', description: 'Create a User with ROLE_ADMIN and optionally link to an Admin entity')]
class CreateAdminUserCommand extends Command
{
    public function __construct(private EntityManagerInterface $em, private UserPasswordHasherInterface $passwordHasher)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_OPTIONAL, 'Admin user email')
            ->addOption('password', null, InputOption::VALUE_OPTIONAL, 'Admin user password')
            ->addOption('admin-id', null, InputOption::VALUE_OPTIONAL, 'Link created User to existing Admin id');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');

        $email = $input->getOption('email');
        if (!$email) {
            $question = new Question('Email: ');
            $email = $helper->ask($input, $output, $question);
        }

        $password = $input->getOption('password');
        if (!$password) {
            $question = new Question('Password: ');
            $question->setHidden(true);
            $question->setHiddenFallback(false);
            $password = $helper->ask($input, $output, $question);
        }

        if (!$email || !$password) {
            $output->writeln('<error>Email and password are required.</error>');
            return Command::FAILURE;
        }

        $existing = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existing) {
            $output->writeln('<error>A user with that email already exists.</error>');
            return Command::FAILURE;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setRoles(['ROLE_ADMIN']);
        $user->setUserType('admin');
        $user->setFullName('Administrator');
        $hashed = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashed);

        $this->em->persist($user);

        $adminId = $input->getOption('admin-id');
        if ($adminId) {
            $admin = $this->em->getRepository(Admin::class)->find($adminId);
            if (!$admin) {
                $output->writeln('<error>Admin with id ' . $adminId . ' not found.</error>');
                return Command::FAILURE;
            }
            $admin->setUser($user);
            $this->em->persist($admin);
        }

        $this->em->flush();

        $output->writeln('<info>Admin user created successfully.</info>');
        $output->writeln('Email: ' . $email);
        if ($adminId) {
            $output->writeln('Linked to Admin id: ' . $adminId);
        }

        return Command::SUCCESS;
    }
}
