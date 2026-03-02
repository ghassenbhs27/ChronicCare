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

$email = $argv[1] ?? 'prosante@example.com';
$password = $argv[2] ?? 'ChangeMe123!';
$nom = $argv[3] ?? 'Professionnel';
$prenom = $argv[4] ?? 'Santé';
$genre = $argv[5] ?? 'Homme';

$repo = $entityManager->getRepository(User::class);
$existing = $repo->findOneBy(['email' => $email]);
if ($existing) {
    echo "User with email $email already exists\n";
    exit(0);
}

$pro = new User();
$pro->setEmail($email);
$pro->setNom($nom);
$pro->setPrenom($prenom);
$pro->setGenre($genre);
$pro->setRoles(['ROLE_NUTRITIONNISTE']);
$pro->setIsActive(true);
$pro->setApprovalStatus('approved');

// Hash the password properly
$password_hash = password_hash($password, PASSWORD_BCRYPT);
$pro->setPassword($password_hash);

$entityManager->persist($pro);
$entityManager->flush();

echo "Professional healthcare created: $email / $password\n";
echo "You can now login at: http://localhost:8000/login\n";
