<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ProfileType;
use App\Service\SecurityMonitoringService;
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
        SluggerInterface $slugger,
        SecurityMonitoringService $securityMonitoringService
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        $previousEmail = mb_strtolower(trim((string) $user->getEmail()));

        $isAdmin = $this->isGranted('ROLE_ADMIN');
        $form = $this->createForm(ProfileType::class, $user, ['is_admin' => $isAdmin]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle profile image upload
            $profileImageFile = $form->get('profileImage')->getData();
            if ($profileImageFile) {
                try {
                    $originalFilename = pathinfo($profileImageFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename . '-' . uniqid() . '.' . $profileImageFile->guessExtension();

                    $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/profiles';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    // Delete old image if exists
                    if ($user->getProfileImage()) {
                        $oldImagePath = $uploadDir . '/' . $user->getProfileImage();
                        if (file_exists($oldImagePath)) {
                            unlink($oldImagePath);
                        }
                    }

                    $profileImageFile->move($uploadDir, $newFilename);
                    $user->setProfileImage($newFilename);
                } catch (\Exception $e) {
                    // Handle error silently or log it
                }
            }

            // updatedAt
            $user->setUpdatedAt(new \DateTimeImmutable());

            // Mot de passe optionnel
            $newPassword = $form->get('newPassword')->getData();
            if ($newPassword) {
                $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
            }
            if ($form->has('mainRole')) {
                $role = $form->get('mainRole')->getData();
                if ($role) {
                    $user->setRoles([$role]);
                }
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

            $newEmail = mb_strtolower(trim((string) $user->getEmail()));
            if ($newEmail !== '' && $newEmail !== $previousEmail) {
                $securityMonitoringService->trackEmailChange($request, $user, $previousEmail, $newEmail);
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
