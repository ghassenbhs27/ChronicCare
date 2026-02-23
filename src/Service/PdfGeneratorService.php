<?php

namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * Service pour générer des fichiers PDF
 */
class PdfGeneratorService
{
    private Dompdf $dompdf;

    public function __construct(
        private readonly Environment $twig,
        private readonly LoggerInterface $logger,
        private readonly string $projectDir
    ) {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isFontSubsettingEnabled', true);
        $options->set('chroot', $projectDir . '/public');
        
        $this->dompdf = new Dompdf($options);
    }

    /**
     * Génère un PDF à partir d'un template Twig
     * 
     * @param string $template Chemin du template Twig
     * @param array $data Données à passer au template
     * @param string $filename Nom du fichier PDF
     * @param bool $download true = téléchargement, false = affichage inline
     */
    public function generateFromTemplate(
        string $template,
        array $data,
        string $filename,
        bool $download = true
    ): Response {
        try {
            // Rendre le template Twig
            $html = $this->twig->render($template, $data);
            
            // Générer le PDF
            $this->dompdf->loadHtml($html);
            $this->dompdf->setPaper('A4', 'portrait');
            $this->dompdf->render();
            
            // Créer la réponse
            $response = new Response($this->dompdf->output());
            $response->headers->set('Content-Type', 'application/pdf');
            
            $disposition = $download ? 'attachment' : 'inline';
            $response->headers->set(
                'Content-Disposition',
                "{$disposition}; filename=\"{$filename}\""
            );
            
            return $response;
            
        } catch (\Exception $e) {
            $this->logger->error('Erreur génération PDF', [
                'template' => $template,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Génère une facture PDF pour une commande
     */
    public function generateInvoice(object $commande, bool $download = true): Response
    {
        $filename = sprintf('facture_%s.pdf', $commande->getNumeroCommande());
        
        return $this->generateFromTemplate(
            'pdf/invoice.html.twig',
            [
                'commande' => $commande,
                'generatedAt' => new \DateTime()
            ],
            $filename,
            $download
        );
    }

    /**
     * Génère une fiche produit PDF
     */
    public function generateProductSheet(object $produit, bool $download = true): Response
    {
        $filename = sprintf('fiche_produit_%d.pdf', $produit->getId());
        
        return $this->generateFromTemplate(
            'pdf/product_sheet.html.twig',
            [
                'produit' => $produit,
                'generatedAt' => new \DateTime()
            ],
            $filename,
            $download
        );
    }

    /**
     * Génère un catalogue PDF de produits
     */
    public function generateCatalog(array $produits, bool $download = true): Response
    {
        $filename = sprintf('catalogue_produits_%s.pdf', date('Y-m-d'));
        
        return $this->generateFromTemplate(
            'pdf/catalog.html.twig',
            [
                'produits' => $produits,
                'generatedAt' => new \DateTime()
            ],
            $filename,
            $download
        );
    }

    /**
     * Génère une confirmation de RDV PDF
     */
    public function generateRdvConfirmation(object $rdv, bool $download = true): Response
    {
        $filename = sprintf('confirmation_rdv_%d.pdf', $rdv->getId());
        
        return $this->generateFromTemplate(
            'pdf/rdv_confirmation.html.twig',
            [
                'rdv' => $rdv,
                'generatedAt' => new \DateTime()
            ],
            $filename,
            $download
        );
    }

    /**
     * Génère un ticket d'événement PDF
     */
    public function generateEventTicket(object $inscription, bool $download = true): Response
    {
        $filename = sprintf('ticket_evenement_%d.pdf', $inscription->getId());
        
        return $this->generateFromTemplate(
            'pdf/event_ticket.html.twig',
            [
                'inscription' => $inscription,
                'generatedAt' => new \DateTime()
            ],
            $filename,
            $download
        );
    }

    /**
     * Génère un PDF brut à partir de HTML
     */
    public function generateFromHtml(
        string $html,
        string $filename,
        bool $download = true,
        string $orientation = 'portrait'
    ): Response {
        $this->dompdf->loadHtml($html);
        $this->dompdf->setPaper('A4', $orientation);
        $this->dompdf->render();
        
        $response = new Response($this->dompdf->output());
        $response->headers->set('Content-Type', 'application/pdf');
        
        $disposition = $download ? 'attachment' : 'inline';
        $response->headers->set(
            'Content-Disposition',
            "{$disposition}; filename=\"{$filename}\""
        );
        
        return $response;
    }

    /**
     * Sauvegarde un PDF dans un fichier
     */
    public function saveToFile(string $template, array $data, string $filepath): bool
    {
        try {
            $html = $this->twig->render($template, $data);
            
            $this->dompdf->loadHtml($html);
            $this->dompdf->setPaper('A4', 'portrait');
            $this->dompdf->render();
            
            file_put_contents($filepath, $this->dompdf->output());
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Erreur sauvegarde PDF', [
                'filepath' => $filepath,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
