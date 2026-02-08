<?php

namespace App\Controller\Front;

use App\Entity\Etat;
use App\Entity\User;
use App\Form\EtatFrontType;
use App\Repository\EtatRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/etat')]
class EtatFrontController extends AbstractController
{
    #[Route('/', name: 'front_etat_index', methods: ['GET'])]
    public function index(Request $request, EtatRepository $repo): Response
    {
        $q = $request->query->get('q');
        $sort = $request->query->get('sort', 'dateReleve');
        $dir = $request->query->get('dir', 'DESC');

        $etats = $repo->searchAndSort($q, $sort, $dir);

        return $this->render('front/etat/index.html.twig', compact('etats', 'q', 'sort', 'dir'));
    }

    #[Route('/new', name: 'front_etat_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepository
    ): Response {
        $etat = new Etat();
        $form = $this->createForm(EtatFrontType::class, $etat);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $currentUser = $this->getUser();
            if ($currentUser instanceof User) {
                $etat->setUtilisateur($currentUser);
            } else {
                $fallbackUser = $userRepository->findOneBy([], ['id' => 'ASC']);
                if (!$fallbackUser instanceof User) {
                    $fallbackUser = $this->createTemporaryPatient($em);
                }
                $etat->setUtilisateur($fallbackUser);
            }

            $em->persist($etat);
            $em->flush();
            $this->addFlash('success', 'Etat ajoute avec succes.');

            return $this->redirectToRoute('front_etat_index');
        }
        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('danger', 'Enregistrement impossible: verifiez les champs.');
        }

        return $this->render('front/etat/new.html.twig', [
            'etat' => $etat,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'front_etat_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Etat $etat): Response
    {
        return $this->render('front/etat/show.html.twig', compact('etat'));
    }

    #[Route('/{id}/edit', name: 'front_etat_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Etat $etat, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(EtatFrontType::class, $etat);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Etat modifie avec succes.');
            return $this->redirectToRoute('front_etat_index');
        }
        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('danger', 'Modification impossible: verifiez les champs.');
        }

        return $this->render('front/etat/edit.html.twig', [
            'etat' => $etat,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'front_etat_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Etat $etat, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$etat->getId(), $request->request->get('_token'))) {
            $em->remove($etat);
            $em->flush();
            $this->addFlash('success', 'Etat supprime avec succes.');
        } else {
            $this->addFlash('danger', 'Token CSRF invalide: suppression refusee.');
        }

        return $this->redirectToRoute('front_etat_index');
    }

    #[Route('/export/excel', name: 'front_etat_export_excel', methods: ['GET'])]
    public function exportExcel(Request $request, EtatRepository $repo): Response
    {
        $q = $request->query->get('q');
        $sort = $request->query->get('sort', 'dateReleve');
        $dir = $request->query->get('dir', 'DESC');
        $etats = $repo->searchAndSort($q, $sort, $dir);

        $rows = ["ID;Type;Valeur;Unite;Date Releve;Utilisateur"];
        foreach ($etats as $etat) {
            $rows[] = implode(';', [
                $etat->getId(),
                $this->csvValue($etat->getType()),
                $etat->getValeur(),
                $this->csvValue($etat->getUnite()),
                $etat->getDateReleve()?->format('Y-m-d') ?? '',
                $etat->getUtilisateur()?->getId() ?? '',
            ]);
        }

        $content = "\xEF\xBB\xBF".implode("\n", $rows);
        $response = new Response($content);
        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'etat-export-'.date('Ymd-His').'.csv'
        );
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }

    #[Route('/export/pdf', name: 'front_etat_export_pdf', methods: ['GET'])]
    public function exportPdf(Request $request, EtatRepository $repo): Response
    {
        $q = $request->query->get('q');
        $sort = $request->query->get('sort', 'dateReleve');
        $dir = $request->query->get('dir', 'DESC');
        $etats = $repo->searchAndSort($q, $sort, $dir);

        $widths = [6, 34, 14, 16, 18];
        $lines = ['Export Etats - '.date('Y-m-d H:i')];
        $lines[] = $this->pdfTableLine($widths);
        $lines[] = $this->pdfTableRow(['ID', 'Type', 'Valeur', 'Unite', 'Date releve'], $widths);
        $lines[] = $this->pdfTableLine($widths);
        foreach ($etats as $etat) {
            $lines[] = $this->pdfTableRow(
                [
                    (string) ($etat->getId() ?? ''),
                    (string) ($etat->getType() ?? ''),
                    (string) ($etat->getValeur() ?? ''),
                    (string) ($etat->getUnite() ?? ''),
                    $etat->getDateReleve()?->format('Y-m-d') ?? '',
                ],
                $widths
            );
        }
        $lines[] = $this->pdfTableLine($widths);

        $pdfContent = $this->generateSimplePdf($lines);
        $response = new Response($pdfContent);
        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'etat-export-'.date('Ymd-His').'.pdf'
        );
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }

    private function createTemporaryPatient(EntityManagerInterface $em): User
    {
        $stamp = date('YmdHis');
        $user = new User();
        $user->setNom('Patient');
        $user->setPrenom('Temporaire');
        $user->setEmail('patient-temp-'.$stamp.'@local.chroniccare');
        $user->setPassword('temporary-password');
        $user->setRoles(['ROLE_PATIENT']);
        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function csvValue(?string $value): string
    {
        $value = $value ?? '';
        $escaped = str_replace('"', '""', $value);

        return '"'.$escaped.'"';
    }

    private function generateSimplePdf(array $lines): string
    {
        $contentLines = ["BT", "/F1 10 Tf"];
        $y = 800;
        foreach ($lines as $line) {
            if ($y < 40) {
                break;
            }
            $safeLine = $this->escapePdfText($line);
            $contentLines[] = sprintf("1 0 0 1 40 %d Tm (%s) Tj", $y, $safeLine);
            $y -= 14;
        }
        $contentLines[] = "ET";
        $stream = implode("\n", $contentLines);

        $objects = [
            1 => "<< /Type /Catalog /Pages 2 0 R >>",
            2 => "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
            3 => "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>",
            4 => "<< /Length ".strlen($stream)." >>\nstream\n".$stream."\nendstream",
            5 => "<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id." 0 obj\n".$body."\nendobj\n";
        }
        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n".$xrefOffset."\n%%EOF";

        return $pdf;
    }

    private function escapePdfText(string $value): string
    {
        return str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\(', '\)'],
            $value
        );
    }

    private function pdfTableRow(array $values, array $widths): string
    {
        $cells = [];
        foreach ($widths as $index => $width) {
            $raw = isset($values[$index]) ? (string) $values[$index] : '';
            $text = mb_substr(trim($raw), 0, max(0, $width - 1));
            $cells[] = str_pad($text, $width, ' ');
        }

        return '|'.implode('|', $cells).'|';
    }

    private function pdfTableLine(array $widths): string
    {
        $segments = array_map(
            static fn (int $width): string => str_repeat('-', $width),
            $widths
        );

        return '+'.implode('+', $segments).'+';
    }
}
