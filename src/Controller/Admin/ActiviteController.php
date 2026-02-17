<?php

namespace App\Controller\Admin;

use App\Entity\Activite;
use App\Form\ActiviteType;
use App\Repository\ActiviteRepository;
use App\Repository\EtatRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/activite')]
class ActiviteController extends AbstractController
{
    #[Route('/', name: 'admin_activite_index', methods: ['GET'])]
    public function index(Request $request, ActiviteRepository $repo): Response
    {
        $q = $request->query->get('q');
        $sort = $request->query->get('sort', 'dateActivite');
        $dir = $request->query->get('dir', 'DESC');

        $activites = $repo->searchAndSort($q, $sort, $dir);
        $globalStats = $repo->getGlobalStats();
        $trendByType = $repo->getTrendByType();
        $userComparison = $repo->getUserComparison();
        $inactivityAlerts = $repo->getInactivityAnomalies(7);

        // Données pour la courbe (7 derniers jours)
        $byDay = $repo->getCountByDayLastDays(7);
        $dayMap = [];
        foreach ($byDay as $row) {
            $dayMap[$row['date']] = (int) $row['total'];
        }
        $now = new \DateTimeImmutable();
        $chartLabels = [];
        $chartData = [];
        $dayNames = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
        for ($i = 6; $i >= 0; $i--) {
            $d = $now->modify("-{$i} days");
            $key = $d->format('Y-m-d');
            $chartLabels[] = $dayNames[(int) $d->format('N') - 1];
            $chartData[] = $dayMap[$key] ?? 0;
        }

        return $this->render(
            'admin/activite/index.html.twig',
            compact('activites', 'q', 'sort', 'dir', 'globalStats', 'trendByType', 'userComparison', 'inactivityAlerts', 'chartLabels', 'chartData')
        );
    }

    #[Route('/new', name: 'admin_activite_new', methods: ['GET', 'POST'])]
    public function new(): Response
    {
        $this->addFlash('warning', 'Ajout d activite desactive dans l administration.');
        return $this->redirectToRoute('admin_activite_index');
    }

    #[Route('/{id}', name: 'admin_activite_show', methods: ['GET'])]
    public function show(Activite $activite): Response
    {
        return $this->render('admin/activite/show.html.twig', ['activite' => $activite]);
    }

    #[Route('/{id}/edit', name: 'admin_activite_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Activite $activite, EntityManagerInterface $em, EtatRepository $etatRepository): Response
    {
        $etats = $etatRepository->findBy([], ['dateReleve' => 'DESC']);
        $form = $this->createForm(ActiviteType::class, $activite, ['etats' => $etats]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            return $this->redirectToRoute('admin_activite_index');
        }

        return $this->render('admin/activite/edit.html.twig', [
            'activite' => $activite,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'admin_activite_delete', methods: ['POST'])]
    public function delete(Request $request, Activite $activite, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $activite->getId(), $request->request->get('_token'))) {
            $em->remove($activite);
            $em->flush();
        }

        return $this->redirectToRoute('admin_activite_index');
    }

    #[Route('/export/excel', name: 'admin_activite_export_excel', methods: ['GET'])]
    public function exportExcel(Request $request, ActiviteRepository $repo): Response
    {
        $q = $request->query->get('q');
        $sort = $request->query->get('sort', 'dateActivite');
        $dir = $request->query->get('dir', 'DESC');
        $activites = $repo->searchAndSort($q, $sort, $dir);

        $rows = ['ID;Type;Duree;Calories;Date activite;Utilisateur;Cree le'];
        foreach ($activites as $activite) {
            $rows[] = implode(';', [
                $activite->getId(),
                $this->csvValue($activite->getType()),
                $activite->getDuree(),
                $activite->getCalories(),
                $activite->getDateActivite()?->format('Y-m-d') ?? '',
                $activite->getUtilisateur()?->getId() ?? '',
                $activite->getCreatedAt()?->format('Y-m-d H:i') ?? '',
            ]);
        }

        $content = "\xEF\xBB\xBF" . implode("\n", $rows);
        $response = new Response($content);
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'activite-export-' . date('Ymd-His') . '.csv'));

        return $response;
    }

    #[Route('/export/pdf', name: 'admin_activite_export_pdf', methods: ['GET'])]
    public function exportPdf(Request $request, ActiviteRepository $repo): Response
    {
        $q = $request->query->get('q');
        $sort = $request->query->get('sort', 'dateActivite');
        $dir = $request->query->get('dir', 'DESC');
        $activites = $repo->searchAndSort($q, $sort, $dir);

        $widths = [5, 24, 10, 10, 14, 10, 17];
        $lines = ['Export Activites - ' . date('Y-m-d H:i')];
        $lines[] = $this->pdfTableLine($widths);
        $lines[] = $this->pdfTableRow(['ID', 'Type', 'Duree', 'Calories', 'Date', 'User', 'Cree'], $widths);
        $lines[] = $this->pdfTableLine($widths);

        foreach ($activites as $activite) {
            $lines[] = $this->pdfTableRow(
                [
                    (string) ($activite->getId() ?? ''),
                    (string) ($activite->getType() ?? ''),
                    (string) ($activite->getDuree() ?? ''),
                    (string) ($activite->getCalories() ?? ''),
                    $activite->getDateActivite()?->format('Y-m-d') ?? '',
                    (string) ($activite->getUtilisateur()?->getId() ?? ''),
                    $activite->getCreatedAt()?->format('Y-m-d H:i') ?? '',
                ],
                $widths
            );
        }

        $lines[] = $this->pdfTableLine($widths);
        $pdfContent = $this->generateSimplePdf($lines);
        $response = new Response($pdfContent);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'activite-export-' . date('Ymd-His') . '.pdf'));

        return $response;
    }

    private function csvValue(?string $value): string
    {
        return '"' . str_replace('"', '""', $value ?? '') . '"';
    }

    private function generateSimplePdf(array $lines): string
    {
        $contentLines = ['BT', '/F1 9 Tf'];
        $y = 560;
        foreach ($lines as $line) {
            if ($y < 40) break;
            $contentLines[] = sprintf('1 0 0 1 24 %d Tm (%s) Tj', $y, $this->escapePdfText($line));
            $y -= 12;
        }
        $contentLines[] = 'ET';
        $stream = implode("\n", $contentLines);
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            4 => '<< /Length ' . strlen($stream) . ' >>' . "\nstream\n" . $stream . "\nendstream",
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . ' /Root 1 0 R >>' . "\nstartxref\n" . $xrefOffset . "\n%%EOF";

        return $pdf;
    }

    private function escapePdfText(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\(', '\)'], $value);
    }

    private function pdfTableRow(array $values, array $widths): string
    {
        $cells = [];
        foreach ($widths as $i => $w) {
            $cells[] = str_pad(mb_substr(trim($values[$i] ?? ''), 0, max(0, $w - 1)), $w, ' ');
        }
        return '|' . implode('|', $cells) . '|';
    }

    private function pdfTableLine(array $widths): string
    {
        return '+' . implode('+', array_map(fn ($w) => str_repeat('-', $w), $widths)) . '+';
    }
}
