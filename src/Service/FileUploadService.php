<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class FileUploadService
{
    private string $targetDirectory;
    private SluggerInterface $slugger;

    public function __construct(string $forumUploadDir, SluggerInterface $slugger)
    {
        $this->targetDirectory = $forumUploadDir;
        $this->slugger = $slugger;
    }

    /**
     * Upload file and return filename
     * 
     * @param UploadedFile $file
     * @return string|null
     */
    public function uploadFile(UploadedFile $file): ?string
    {
        if (!$file) {
            return null;
        }

        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        try {
            $file->move($this->targetDirectory, $newFilename);
            return $newFilename;
        } catch (\Exception $e) {
            throw new \RuntimeException('Erreur lors du téléchargement du fichier: ' . $e->getMessage());
        }
    }

    /**
     * Delete file if exists
     * 
     * @param string|null $filename
     * @return bool
     */
    public function deleteFile(?string $filename): bool
    {
        if (!$filename) {
            return true;
        }

        $filePath = $this->targetDirectory . '/' . $filename;
        
        if (file_exists($filePath)) {
            return unlink($filePath);
        }

        return true;
    }
}
