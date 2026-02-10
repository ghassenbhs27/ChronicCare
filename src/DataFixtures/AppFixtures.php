<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        // Créer un utilisateur de simulation
        $userSimulation = new User();
        $userSimulation->setNom('Simulation');
        $userSimulation->setPrenom('Utilisateur');
        $userSimulation->setEmail('simulation@chroniccare.local');
        $userSimulation->setTelephone('0000000000');
        $userSimulation->setAdresse('Adresse de simulation');
        $userSimulation->setMaladieChronique('Simulation');
        $userSimulation->setRoles(['ROLE_USER']);

        // Hash the password
        $hashedPassword = $this->passwordHasher->hashPassword($userSimulation, 'simulation123');
        $userSimulation->setPassword($hashedPassword);

        $manager->persist($userSimulation);
        $manager->flush();
    }
}
