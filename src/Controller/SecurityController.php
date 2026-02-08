<?php
namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends BaseController
{
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Si l'utilisateur est déjà connecté
        if ($this->getUser()) {
            // Redirect admins to user management
            if ($this->isGranted('ROLE_ADMIN')) {
                return $this->redirectToRoute('admin_dashboard');
            }
            // Redirect regular users to homepage
            return $this->redirectToRoute('index');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastEmail = $authenticationUtils->getLastUsername();

        return $this->render('front/connexion.html.twig', [
        'last_email' => $lastEmail,
        'error' => $error,
        ]);
    }

    #[Route('/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): void
    {
        // Symfony gère le logout automatiquement via security.yaml
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
public function register(
    Request $request,
    UserPasswordHasherInterface $passwordHasher,
    EntityManagerInterface $entityManager
): Response {
    // Si déjà connecté, rediriger
    if ($this->getUser()) {
        return $this->redirectToRoute('index');
    }

    $user = new User();
    $form = $this->createForm(UserType::class, $user);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        // ✅ AJOUTER DES LOGS POUR DEBUGGER
        dump('Formulaire soumis et valide !');
        
        // Hash du mot de passe
        $plainPassword = $form->get('password')->getData();
        if ($plainPassword) {
            $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);
            dump('Password hashé : ' . substr($hashedPassword, 0, 20) . '...');
        }

        // Définir le rôle par défaut
        $selectedRole = $form->get('accountType')->getData();
        $user->setRoles([$selectedRole]);
        //dump('Rôles : ', $user->getRoles());

        // Définir les dates
        $user->setCreatedAt(new \DateTimeImmutable());
        $user->setUpdatedAt(new \DateTimeImmutable());
        dump('Dates définies');

        try {
            $entityManager->persist($user);
            $entityManager->flush();
            
            dump('User enregistré avec ID : ' . $user->getId());

            $this->addFlash('success', '✅ Compte créé avec succès ! Vous pouvez maintenant vous connecter.');

            return $this->redirectToRoute('app_login');
        } catch (\Exception $e) {
            dump('ERREUR : ' . $e->getMessage());
            $this->addFlash('error', '❌ Erreur : ' . $e->getMessage());
        }
    }

    // ✅ AFFICHER LES ERREURS DU FORMULAIRE
    if ($form->isSubmitted() && !$form->isValid()) {
        dump('Formulaire soumis mais INVALIDE');
        dump('Erreurs : ', $form->getErrors(true, false));
    }

    return $this->render('front/inscription.html.twig', [
        'form' => $form->createView(),
    ]);

}
}