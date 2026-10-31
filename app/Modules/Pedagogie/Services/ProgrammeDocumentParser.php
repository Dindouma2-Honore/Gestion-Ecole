<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Services;

use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use Smalot\PdfParser\Parser as PdfParser;
use ZipArchive;

final class ProgrammeDocumentParser
{
    private const MAX_CHAPITRES = 16;

    /** @return array<int, array{titre:string, ordre:int, contenu:string, parties:array<int, array{titre:string, contenu:string}>}> */
    public function extraire(UploadedFile $fichier): array
    {
        $extension = strtolower($fichier->getClientOriginalExtension());
        $texte = match ($extension) {
            'pdf' => (new PdfParser)->parseFile($fichier->getRealPath())->getText(),
            'docx' => $this->extraireDocx($fichier->getRealPath()),
            'doc' => $this->extraireDocBinaire($fichier->getRealPath()),
            default => throw new InvalidArgumentException('Le programme doit être un fichier PDF, DOC ou DOCX.'),
        };

        $chapitres = $this->interpreterTexte($texte);
        if ($chapitres === []) {
            throw new InvalidArgumentException("Aucun chapitre n'a pu être identifié. Utilisez des titres comme « Chapitre 1 : ... » dans le document.");
        }

        return $chapitres;
    }

    /** @return array<int, array{titre:string, ordre:int, contenu:string, parties:array<int, array{titre:string, contenu:string}>}> */
    public function interpreterTexte(string $texte): array
    {
        $texte = str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $texte);
        $lignes = array_values(array_filter(array_map(static fn (string $ligne): string => trim(preg_replace('/\s+/u', ' ', $ligne) ?? ''), explode("\n", $texte))));
        $chapitres = [];
        $courant = null;

        foreach ($lignes as $ligne) {
            if (preg_match('/^(?:chapitre|chapter)\s*(?:n[°o.]?\s*)?([0-9]{1,2}|[ivxlcdm]+)?\s*[:.\-–]?\s*(.*)$/iu', $ligne, $matches)) {
                if ($courant !== null) {
                    $chapitres[] = $this->finaliserChapitre($courant, count($chapitres) + 1);
                }
                if (count($chapitres) >= self::MAX_CHAPITRES) {
                    break;
                }
                $numero = trim((string) ($matches[1] ?? ''));
                $intitule = trim((string) ($matches[2] ?? ''));
                $courant = [
                    'titre' => trim('Chapitre '.($numero !== '' ? $numero : count($chapitres) + 1).($intitule !== '' ? ' : '.$intitule : '')),
                    'contenu_general' => [],
                    'parties' => [],
                    'partie_courante' => null,
                ];

                continue;
            }

            if ($courant === null) {
                continue;
            }

            if (preg_match('/^(?:partie|section|le(?:c|ç)on|lesson|sous[- ]?chapitre)\s*([0-9ivxlcdm.a-z-]*)\s*[:.\-–]?\s*(.*)$/iu', $ligne, $matches)) {
                $this->terminerPartie($courant);
                $suffixe = trim((string) ($matches[1] ?? ''));
                $intitule = trim((string) ($matches[2] ?? ''));
                $courant['partie_courante'] = [
                    'titre' => trim('Partie'.($suffixe !== '' ? ' '.$suffixe : '').($intitule !== '' ? ' : '.$intitule : '')),
                    'contenu' => [],
                ];

                continue;
            }

            if ($courant['partie_courante'] !== null) {
                $courant['partie_courante']['contenu'][] = $ligne;
            } else {
                $courant['contenu_general'][] = $ligne;
            }
        }

        if ($courant !== null && count($chapitres) < self::MAX_CHAPITRES) {
            $chapitres[] = $this->finaliserChapitre($courant, count($chapitres) + 1);
        }

        return array_slice($chapitres, 0, self::MAX_CHAPITRES);
    }

    private function extraireDocx(string $chemin): string
    {
        $archive = new ZipArchive;
        if ($archive->open($chemin) !== true) {
            throw new InvalidArgumentException('Le fichier Word ne peut pas être ouvert.');
        }
        $xml = $archive->getFromName('word/document.xml');
        $archive->close();
        if ($xml === false) {
            throw new InvalidArgumentException('Le document Word ne contient aucun texte exploitable.');
        }

        $xml = preg_replace('/<\/(w:p|w:tr)>/u', "\n", $xml) ?? $xml;
        $xml = preg_replace('/<w:tab\s*\/>/u', "\t", $xml) ?? $xml;

        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function extraireDocBinaire(string $chemin): string
    {
        $contenu = file_get_contents($chemin);
        if ($contenu === false) {
            throw new InvalidArgumentException('Le fichier Word ne peut pas être lu.');
        }

        preg_match_all('/[\x20-\x7E\xC0-\xFF]{4,}/', $contenu, $segments);

        return implode("\n", $segments[0] ?? []);
    }

    private function terminerPartie(array &$chapitre): void
    {
        if ($chapitre['partie_courante'] === null) {
            return;
        }
        $chapitre['partie_courante']['contenu'] = implode("\n", $chapitre['partie_courante']['contenu']);
        $chapitre['parties'][] = $chapitre['partie_courante'];
        $chapitre['partie_courante'] = null;
    }

    private function finaliserChapitre(array $chapitre, int $ordre): array
    {
        $this->terminerPartie($chapitre);
        $contenu = implode("\n", $chapitre['contenu_general']);
        foreach ($chapitre['parties'] as $partie) {
            $contenu .= ($contenu !== '' ? "\n\n" : '').$partie['titre'].($partie['contenu'] !== '' ? "\n".$partie['contenu'] : '');
        }

        return ['titre' => $chapitre['titre'], 'ordre' => $ordre, 'contenu' => trim($contenu), 'parties' => $chapitre['parties']];
    }
}
