<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Kernel;
use App\Entity\User;
use Symfony\Component\Dotenv\Dotenv;

$env = 'dev';
$debug = true;

$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    (new Dotenv())->loadEnv($envFile);
}

$kernel = new Kernel($env, $debug);
$kernel->boot();

$container = $kernel->getContainer();
$entityManager = $container->get('doctrine')->getManager();

$email = $argv[1] ?? 'coach@example.com';
$role = $argv[2] ?? 'ROLE_COACH';

$repo = $entityManager->getRepository(User::class);
$user = $repo->findOneBy(['email' => $email]);

if (!$user) {
    echo "User with email $email not found\n";
    exit(1);
}

$user->setRoles([$role]);
$entityManager->persist($user);
$entityManager->flush();

echo "User $email updated with role $role\n";
