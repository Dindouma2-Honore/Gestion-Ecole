<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Services;

use App\Modules\Pedagogie\Exceptions\DocumentAnalyseException;
use Smalot\PdfParser\Parser as PdfParser;
use ZipArchive;

class DocumentExtractorService
{
    public function __construct(
        protected ?OcrService $ocrService = null
    ) {
        $this->ocrService ??= new OcrService();
    }

    /**
     * Extrait le texte d'un document selon son extension et type.
     */
    public function extraireTexte(string $cheminFichier): string
    {
        if (! file_exists($cheminFichier)) {
            throw new DocumentAnalyseException("Le fichier spécifié n'existe pas : {$cheminFichier}");
        }

        if (filesize($cheminFichier) === 0) {
            throw DocumentAnalyseException::documentVideOuIllisible();
        }

        $extension = strtolower(pathinfo($cheminFichier, PATHINFO_EXTENSION));

        $texte = match ($extension) {
            'pdf' => $this->extrairePdf($cheminFichier),
            'docx' => $this->extraireDocx($cheminFichier),
            'doc' => $this->extraireDocBinaire($cheminFichier),
            'xlsx' => $this->extraireXlsx($cheminFichier),
            'csv' => $this->extraireCsv($cheminFichier),
            'txt', 'text', 'md' => (string) file_get_contents($cheminFichier),
            'png', 'jpg', 'jpeg', 'webp' => $this->ocrService->extraireImage($cheminFichier),
            default => throw DocumentAnalyseException::formatNonSupporte($extension),
        };

        $texteNormalise = $this->normaliserTexte($texte);

        // Si le texte extrait d'un PDF est quasi vide, tenter l'OCR
        if (mb_strlen($texteNormalise) < 30 && in_array($extension, ['pdf', 'png', 'jpg', 'jpeg'], true)) {
            $texteOcr = $this->ocrService->extraireOcr($cheminFichier);
            if (mb_strlen($texteOcr) > mb_strlen($texteNormalise)) {
                $texteNormalise = $this->normaliserTexte($texteOcr);
            }
        }

        if (mb_strlen($texteNormalise) < 20) {
            throw DocumentAnalyseException::documentVideOuIllisible();
        }

        return $texteNormalise;
    }

    protected function extrairePdf(string $chemin): string
    {
        try {
            $parser = new PdfParser();
            $pdf = $parser->parseFile($chemin);

            return $pdf->getText();
        } catch (\Throwable $e) {
            return '';
        }
    }

    protected function extraireDocx(string $chemin): string
    {
        $zip = new ZipArchive();
        if ($zip->open($chemin) !== true) {
            return '';
        }

        $xmlIndex = $zip->locateName('word/document.xml');
        if ($xmlIndex === false) {
            $zip->close();

            return '';
        }

        $content = $zip->getFromIndex($xmlIndex);
        $zip->close();

        if (! $content) {
            return '';
        }

        $content = preg_replace('/<\/w:p>/u', "\n", $content) ?? $content;
        $content = preg_replace('/<\/w:tr>/u', "\n", $content) ?? $content;
        $content = preg_replace('/<\/w:tc>/u', "\t", $content) ?? $content;

        return trim(strip_tags($content));
    }

    protected function extraireDocBinaire(string $chemin): string
    {
        $data = (string) file_get_contents($chemin);
        $matches = [];
        preg_match_all('/[\x20-\x7E\t\r\n]{4,}/', $data, $matches);

        return implode(' ', $matches[0] ?? []);
    }

    protected function extraireXlsx(string $chemin): string
    {
        $zip = new ZipArchive();
        if ($zip->open($chemin) !== true) {
            return '';
        }

        // Récupérer les chaînes partagées
        $sharedStrings = [];
        $ssIndex = $zip->locateName('xl/sharedStrings.xml');
        if ($ssIndex !== false) {
            $ssXml = simplexml_load_string((string) $zip->getFromIndex($ssIndex));
            if ($ssXml) {
                foreach ($ssXml->si as $si) {
                    $sharedStrings[] = (string) ($si->t ?? '');
                }
            }
        }

        // Lire la première feuille de calcul
        $texte = '';
        $sheetIndex = $zip->locateName('xl/worksheets/sheet1.xml');
        if ($sheetIndex !== false) {
            $sheetXml = simplexml_load_string((string) $zip->getFromIndex($sheetIndex));
            if ($sheetXml && isset($sheetXml->sheetData->row)) {
                foreach ($sheetXml->sheetData->row as $row) {
                    $rowTexts = [];
                    foreach ($row->c as $cell) {
                        $type = (string) ($cell['t'] ?? '');
                        $val = (string) ($cell->v ?? '');
                        if ($type === 's' && isset($sharedStrings[(int) $val])) {
                            $rowTexts[] = $sharedStrings[(int) $val];
                        } else {
                            $rowTexts[] = $val;
                        }
                    }
                    $texte .= implode("\t", array_filter($rowTexts))."\n";
                }
            }
        }

        $zip->close();

        return $texte;
    }

    protected function extraireCsv(string $chemin): string
    {
        $texte = '';
        if (($handle = fopen($chemin, 'r')) !== false) {
            while (($row = fgetcsv($handle, 4096)) !== false) {
                $texte .= implode("\t", array_map('trim', $row))."\n";
            }
            fclose($handle);
        }

        return $texte;
    }

    public function normaliserTexte(string $texte): string
    {
        $texte = str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $texte);

        // Nettoyer les caractères de contrôle non imprimables
        $texte = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $texte) ?? $texte;

        // Réduire les espaces multiples sur chaque ligne
        $lignes = explode("\n", $texte);
        $lignesPropres = array_map(function ($ligne) {
            return trim(preg_replace('/[ \t]+/u', ' ', $ligne) ?? '');
        }, $lignes);

        // Supprimer les lignes vides consécutives excédentaires
        return trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $lignesPropres)) ?? '');
    }
}
