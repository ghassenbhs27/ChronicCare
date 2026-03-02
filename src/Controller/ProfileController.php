<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ProfileType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class ProfileController extends AbstractController
{
    #[Route('/profile/modal', name: 'app_profile_modal', methods: ['GET'])]
    public function modal(): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        return $this->render('profile/modal_content.html.twig', [
            'mode' => 'view',
        ]);
    }

    #[Route('/profile/edit', name: 'app_profile_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        SluggerInterface $slugger
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Utilisateur non authentifie.');
        }

        $form = $this->createForm(ProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // updatedAt
            $user->setUpdatedAt(new \DateTimeImmutable());

            // Mot de passe optionnel
            $newPassword = $form->get('newPassword')->getData();
            if ($newPassword) {
                $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
            }
            $role = $form->get('mainRole')->getData();
            if ($role) {
                $user->setRoles([$role]); // ou array_unique([...$user->getRoles(), $role])
            }

            /** @var UploadedFile|null $photoFile */
            $photoFile = $form->get('photoProfil')->getData();
            if ($photoFile instanceof UploadedFile) {
                $safeName = $slugger->slug(pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME));
                $newFilename = $safeName . '-' . bin2hex(random_bytes(6)) . '.' . $photoFile->guessExtension();
                $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/profile';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0775, true);
                }
                $photoFile->move($uploadDir, $newFilename);
                $user->setPhotoProfil($newFilename);
            }
            
            $em->flush();
            
            return $this->render('profile/modal_content.html.twig', [
                'mode' => 'view',
                'saved' => true,
            ]);
        }

        return $this->render('profile/modal_content.html.twig', [
            'mode' => 'edit',
            'form' => $form->createView(),
        ]);
    }
}
