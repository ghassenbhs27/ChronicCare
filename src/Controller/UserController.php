<?php
namespace App\Controller;

use Dompdf\Dompdf;
use Dompdf\Options;
use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    #[Route('/new', name: 'app_user_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $user = new User();

        // admin mode (roles + password optionnel)
        $form = $this->createForm(UserType::class, $user, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // hash password si fourni
            $plainPassword = $form->get('password')->getData();
            if ($plainPassword) {
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            }

            // timestamps si tu veux
            $user->setCreatedAt(new \DateTimeImmutable());
            $user->setUpdatedAt(new \DateTimeImmutable());

            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', '🎉 Utilisateur créé avec succès !');
            return $this->redirectToRoute('app_user_index');
        }

        return $this->render('back/users/new.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }
    #[Route('/', name: 'app_user_index', methods: ['GET'])]
    public function index(Request $request, UserRepository $userRepository): Response
    {
        $sort = $request->query->get('sort', 'createdAt');
        $order = $request->query->get('order', 'DESC');
        $search = $request->query->get('q');

        $users = $userRepository->findAllSortedAndFiltered($search, $sort, $order);

        return $this->render('back/users/index.html.twig', [
            'users' => $users,
            'currentSort' => $sort,
            'currentOrder' => $order,
            'search' => $search,
        ]);
    }
    #[Route('/{id}/edit', name: 'app_user_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $form = $this->createForm(UserType::class, $user, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $plainPassword = $form->get('password')->getData();
            if ($plainPassword) {
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            }

            $user->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('success', '✏️ Utilisateur modifié avec succès !');
            return $this->redirectToRoute('app_user_index');
        }

        return $this->render('back/users/edit.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_user_delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $user->getId(), $request->request->get('_token'))) {
            $entityManager->remove($user);
            $entityManager->flush();
            $this->addFlash('success', "🗑️ Utilisateur supprimé !");
        }

        return $this->redirectToRoute('app_user_index');
    }
    #[Route('/export/pdf', name: 'app_user_export_pdf', methods: ['GET'])]
    public function exportPdf(Request $request, UserRepository $userRepository): Response
    {
    // ✅ récupérer mêmes filtres/tri que la liste
        $sort = $request->query->get('sort', 'createdAt');
        $order = $request->query->get('order', 'DESC');
        $search = trim((string) $request->query->get('q', ''));

        $users = $userRepository->findAllSortedAndFiltered($search, $sort, $order);

    // ✅ HTML depuis Twig
        $html = $this->renderView('back/users/pdf.html.twig', [
            'users' => $users,
            'generatedAt' => new \DateTimeImmutable(),
            'search' => $search,
            'sort' => $sort,
            'order' => $order,
        ]);

    // ✅ Dompdf config
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans'); // support accents
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'utilisateurs_' . (new \DateTimeImmutable())->format('Y-m-d_H-i') . '.pdf';

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                 'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]
        );
    }

    
}