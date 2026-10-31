<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Services;

use Illuminate\Support\Facades\Log;

class OcrService
{
    /**
     * Tente une extraction OCR via tesseract CLI si installé.
     */
    public function extraireOcr(string $cheminFichier): string
    {
        if (! file_exists($cheminFichier)) {
            return '';
        }

        // Vérifier si tesseract est installé sur le serveur
        $tesseract = trim((string) shell_exec('which tesseract 2>/dev/null'));
        if (empty($tesseract)) {
            Log::info("OcrService : tesseract n'est pas installé sur l'hôte, OCR local indisponible.");

            return '';
        }

        $fichierSortie = tempnam(sys_get_temp_dir(), 'ocr_out_');
        $cmd = escapeshellcmd($tesseract).' '.escapeshellarg($cheminFichier).' '.escapeshellarg($fichierSortie).' -l fra+eng 2>&1';
        shell_exec($cmd);

        $resultat = '';
        if (file_exists($fichierSortie.'.txt')) {
            $resultat = (string) file_get_contents($fichierSortie.'.txt');
            @unlink($fichierSortie.'.txt');
        }
        @unlink($fichierSortie);

        return trim($resultat);
    }

    public function extraireImage(string $cheminImage): string
    {
        return $this->extraireOcr($cheminImage);
    }
}
