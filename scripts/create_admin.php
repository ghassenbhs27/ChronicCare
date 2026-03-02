<?php
$require_line = "require __DIR__ . '/../vendor/autoload.php';";
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

$email = $argv[1] ?? 'admin@example.com';
$password = $argv[2] ?? 'ChangeMe123!';

$repo = $entityManager->getRepository(User::class);
$existing = $repo->findOneBy(['email' => $email]);
if ($existing) {
    echo "User with email $email already exists\n";
    exit(0);
}

$admin = new User();
$admin->setEmail($email);
$admin->setNom('Admin');
$admin->setPrenom('Super');
$admin->setGenre('Homme');
$admin->setRoles(['ROLE_ADMIN']);
$admin->setIsActive(true);
$hashed = password_hash($password, PASSWORD_BCRYPT);
$admin->setPassword($hashed);

$entityManager->persist($admin);
$entityManager->flush();

echo "Admin created: $email / $password\n";
