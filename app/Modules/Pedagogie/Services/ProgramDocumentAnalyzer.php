<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Services;

use App\Modules\Pedagogie\Exceptions\DocumentAnalyseException;
use Smalot\PdfParser\Parser as PdfParser;
use ZipArchive;

class ProgramDocumentAnalyzer
{
    private const MAX_CHAPITRES = 30;
    private const MAX_LECONS_PAR_CHAPITRE = 50;

    /**
     * Analyse un fichier physique (PDF, DOCX, DOC) et retourne la structure pédagogique hiérarchique.
     *
     * @return array<int, array{
     *     titre: string,
     *     type_structure: string,
     *     ordre: int,
     *     objectif: ?string,
     *     contenu: ?string,
     *     volume_horaire_prevu: ?float,
     *     lecons: array<int, array{
     *         titre: string,
     *         type_element: string,
     *         ordre: int,
     *         objectifs: ?string,
     *         volume_horaire_prevu: ?float
     *     }>
     * }>
     */
    public function analyserFichier(string $cheminFichier, ?string $cycle = null): array
    {
        if (! file_exists($cheminFichier)) {
            throw new DocumentAnalyseException("Le fichier spécifié n'existe pas : {$cheminFichier}");
        }

        $extension = strtolower(pathinfo($cheminFichier, PATHINFO_EXTENSION));

        $texte = match ($extension) {
            'pdf' => $this->extrairePdf($cheminFichier),
            'docx' => $this->extraireDocx($cheminFichier),
            'doc' => $this->extraireDocBinaire($cheminFichier),
            default => throw DocumentAnalyseException::formatNonSupporte($extension),
        };

        if (mb_strlen(trim($texte)) < 20) {
            throw DocumentAnalyseException::documentVideOuIllisible();
        }

        $structure = $this->interpreterTexte($texte, $cycle);

        if (empty($structure)) {
            throw DocumentAnalyseException::aucunElementIdentifie();
        }

        return $structure;
    }

    /**
     * Analyse le texte brut extrait pour reconstituer la structure pédagogique.
     *
     * @return array<int, array{
     *     titre: string,
     *     type_structure: string,
     *     ordre: int,
     *     objectif: ?string,
     *     contenu: ?string,
     *     volume_horaire_prevu: ?float,
     *     lecons: array<int, array{
     *         titre: string,
     *         type_element: string,
     *         ordre: int,
     *         objectifs: ?string,
     *         volume_horaire_prevu: ?float
     *     }>
     * }>
     */
    public function interpreterTexte(string $texte, ?string $cycle = null): array
    {
        $texte = str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $texte);
        $lignes = array_values(array_filter(array_map(
            static fn (string $l): string => trim(preg_replace('/\s+/u', ' ', $l) ?? ''),
            explode("\n", $texte)
        )));

        $chapitres = [];
        $courantChapitre = null;
        $courantLecon = null;

        $typeStructureDefaut = match ($cycle) {
            'maternelle' => 'domaine',
            'primaire' => 'unite',
            default => 'chapitre',
        };

        $typeElementDefaut = match ($cycle) {
            'maternelle' => 'activite',
            'primaire' => 'competence',
            default => 'lecon',
        };

        // Regex de détection des chapitres / domaines / modules
        $regexChapitre = '/^(?:chapitre|chapter|module|th(?:e|è)me|domaine|axe|unit(?:e|é)|partie)\s*(?:n[°o.]?\s*)?([0-9]{1,2}|[ivxlcdm]+)?\s*[:.\-–]?\s*(.*)$/iu';

        // Regex de détection des sous-éléments adaptée au cycle
        $regexLecon = match ($cycle) {
            'maternelle' => '/^(?:activit(?:e|é)|objectif|atelier)\s*(?:n[°o.]?\s*)?([0-9ivxlcdm.a-z-]*)\s*[:.\-–]?\s*(.*)$/iu',
            'primaire' => '/^(?:comp(?:e|é)tence|le(?:c|ç)on|lesson)\s*(?:n[°o.]?\s*)?([0-9ivxlcdm.a-z-]*)\s*[:.\-–]?\s*(.*)$/iu',
            default => '/^(?:le(?:c|ç)on|lesson|tp|td|sous[- ]?chapitre|s(?:e|é)ance)\s*(?:n[°o.]?\s*)?([0-9ivxlcdm.a-z-]*)\s*[:.\-–]?\s*(.*)$/iu',
        };

        foreach ($lignes as $ligne) {
            // Détection du volume horaire dans la ligne
            $volumeTrouve = null;
            if (preg_match('/(\d+(?:[.,]\d+)?)\s*(?:h|heures?|hrs?)\b/iu', $ligne, $hMatch)) {
                $volumeTrouve = (float) str_replace(',', '.', $hMatch[1]);
            }

            // 1. Détection d'un grand ensemble (Chapitre / Domaine / Module)
            if (preg_match($regexChapitre, $ligne, $mChap)) {
                if ($courantChapitre !== null) {
                    $this->finaliserLeconCourante($courantChapitre, $courantLecon);
                    $chapitres[] = $this->finaliserChapitre($courantChapitre, count($chapitres) + 1);
                }

                if (count($chapitres) >= self::MAX_CHAPITRES) {
                    break;
                }

                $numero = trim((string) ($mChap[1] ?? ''));
                $intitule = trim((string) ($mChap[2] ?? ''));
                $intitule = preg_replace('/\s*\(\s*\d+(?:[.,]\d+)?\s*(?:h|heures?|hrs?)\s*\)\s*$/iu', '', $intitule);
                $titrePrefix = ucfirst($typeStructureDefaut).' '.($numero !== '' ? $numero : count($chapitres) + 1);

                $courantChapitre = [
                    'titre' => $intitule !== '' ? "{$titrePrefix} : {$intitule}" : $titrePrefix,
                    'type_structure' => $typeStructureDefaut,
                    'ordre' => count($chapitres) + 1,
                    'objectif' => null,
                    'contenu_lignes' => [],
                    'volume_horaire_prevu' => $volumeTrouve,
                    'lecons' => [],
                ];
                $courantLecon = null;

                continue;
            }

            // Si aucun grand ensemble n'a encore été initié, en initialiser un par défaut
            if ($courantChapitre === null) {
                // Si la ligne ressemble à une leçon sans chapitre, on crée un conteneur initial
                if (preg_match($regexLecon, $ligne)) {
                    $courantChapitre = [
                        'titre' => ucfirst($typeStructureDefaut).' 1',
                        'type_structure' => $typeStructureDefaut,
                        'ordre' => 1,
                        'objectif' => null,
                        'contenu_lignes' => [],
                        'volume_horaire_prevu' => null,
                        'lecons' => [],
                    ];
                } else {
                    continue;
                }
            }

            // 2. Détection d'un sous-élément (Leçon / Compétence / Activité)
            if (preg_match($regexLecon, $ligne, $mLecon)) {
                $this->finaliserLeconCourante($courantChapitre, $courantLecon);

                if (count($courantChapitre['lecons']) < self::MAX_LECONS_PAR_CHAPITRE) {
                    $numLecon = trim((string) ($mLecon[1] ?? ''));
                    $nomLecon = trim((string) ($mLecon[2] ?? ''));
                    $nomLecon = preg_replace('/\s*\(\s*\d+(?:[.,]\d+)?\s*(?:h|heures?|hrs?)\s*\)\s*$/iu', '', $nomLecon);
                    $prefixLecon = ucfirst($typeElementDefaut).' '.($numLecon !== '' ? $numLecon : count($courantChapitre['lecons']) + 1);

                    $courantLecon = [
                        'titre' => $nomLecon !== '' ? "{$prefixLecon} : {$nomLecon}" : $prefixLecon,
                        'type_element' => $typeElementDefaut,
                        'ordre' => count($courantChapitre['lecons']) + 1,
                        'objectifs' => null,
                        'volume_horaire_prevu' => $volumeTrouve ?? 1.0,
                        'lignes_detail' => [],
                    ];
                }

                continue;
            }

            // 3. Détection de tableaux avec séparateurs '|' ou ';' ou '\t'
            if (str_contains($ligne, '|') || str_contains($ligne, "\t")) {
                $colonnes = array_values(array_filter(array_map('trim', preg_split('/[|\t]/', $ligne) ?: [])));
                if (count($colonnes) >= 2) {
                    $colTitre = $colonnes[0];
                    $colObj = $colonnes[1] ?? null;
                    if (mb_strlen($colTitre) > 3 && ! preg_match('/^(titre|chapitre|n°|ordre)/i', $colTitre)) {
                        $this->finaliserLeconCourante($courantChapitre, $courantLecon);
                        $courantChapitre['lecons'][] = [
                            'titre' => $colTitre,
                            'type_element' => $typeElementDefaut,
                            'ordre' => count($courantChapitre['lecons']) + 1,
                            'objectifs' => $colObj,
                            'volume_horaire_prevu' => $volumeTrouve ?? 1.0,
                        ];

                        continue;
                    }
                }
            }

            // 4. Ligne de détail / objectifs / contenu
            if ($courantLecon !== null) {
                $courantLecon['lignes_detail'][] = $ligne;
            } else {
                $courantChapitre['contenu_lignes'][] = $ligne;
            }
        }

        if ($courantChapitre !== null && count($chapitres) < self::MAX_CHAPITRES) {
            $this->finaliserLeconCourante($courantChapitre, $courantLecon);
            $chapitres[] = $this->finaliserChapitre($courantChapitre, count($chapitres) + 1);
        }

        // Si aucun chapitre formel n'a été reconnu mais qu'il y a du texte, découpage heuristique
        if (empty($chapitres) && ! empty($lignes)) {
            $chapitres = $this->decoupageHeuristique($lignes, $typeStructureDefaut, $typeElementDefaut);
        }

        return $chapitres;
    }

    private function finaliserLeconCourante(array &$chapitre, ?array &$leconCourante): void
    {
        if ($leconCourante === null) {
            return;
        }

        $detail = implode("\n", $leconCourante['lignes_detail'] ?? []);
        $chapitre['lecons'][] = [
            'titre' => $leconCourante['titre'],
            'type_element' => $leconCourante['type_element'],
            'ordre' => $leconCourante['ordre'],
            'objectifs' => $detail !== '' ? $detail : ($leconCourante['objectifs'] ?? null),
            'volume_horaire_prevu' => $leconCourante['volume_horaire_prevu'] ?? 1.0,
        ];

        $leconCourante = null;
    }

    private function finaliserChapitre(array $chapitre, int $ordre): array
    {
        $contenu = implode("\n", $chapitre['contenu_lignes'] ?? []);

        // Si aucune leçon n'a été extraite pour ce chapitre, en créer une par défaut
        $lecons = $chapitre['lecons'];
        if (empty($lecons)) {
            $lecons[] = [
                'titre' => $chapitre['titre'].' - Notions fondamentales',
                'type_element' => $chapitre['type_structure'] === 'maternelle' ? 'activite' : 'lecon',
                'ordre' => 1,
                'objectifs' => $contenu !== '' ? $contenu : null,
                'volume_horaire_prevu' => $chapitre['volume_horaire_prevu'] ?? 2.0,
            ];
        }

        return [
            'titre' => $chapitre['titre'],
            'type_structure' => $chapitre['type_structure'],
            'ordre' => $ordre,
            'objectif' => $contenu !== '' ? $contenu : null,
            'contenu' => $contenu !== '' ? $contenu : null,
            'volume_horaire_prevu' => $chapitre['volume_horaire_prevu'] ?? array_sum(array_column($lecons, 'volume_horaire_prevu')),
            'lecons' => $lecons,
        ];
    }

    /**
     * Découpage de repli si le document ne contient aucun en-tête standard reconnu.
     */
    private function decoupageHeuristique(array $lignes, string $typeStructure, string $typeElement): array
    {
        $blocs = array_chunk($lignes, 10);
        $chapitres = [];

        foreach ($blocs as $index => $lignesBloc) {
            if (count($chapitres) >= self::MAX_CHAPITRES) {
                break;
            }

            $titre = trim($lignesBloc[0] ?? ('Partie '.($index + 1)));
            if (mb_strlen($titre) > 80) {
                $titre = mb_substr($titre, 0, 77).'...';
            }

            $chapitres[] = [
                'titre' => ucfirst($typeStructure).' '.($index + 1).' : '.$titre,
                'type_structure' => $typeStructure,
                'ordre' => $index + 1,
                'objectif' => implode(' ', array_slice($lignesBloc, 1, 3)),
                'contenu' => implode("\n", $lignesBloc),
                'volume_horaire_prevu' => 4.0,
                'lecons' => [
                    [
                        'titre' => ucfirst($typeElement).' 1 : '.$titre,
                        'type_element' => $typeElement,
                        'ordre' => 1,
                        'objectifs' => null,
                        'volume_horaire_prevu' => 4.0,
                    ],
                ],
            ];
        }

        return $chapitres;
    }

    private function extrairePdf(string $chemin): string
    {
        try {
            $parser = new PdfParser;
            $pdf = $parser->parseFile($chemin);

            return $pdf->getText();
        } catch (\Throwable $e) {
            throw new DocumentAnalyseException("Impossible de lire le fichier PDF : {$e->getMessage()}", 0, $e);
        }
    }

    private function extraireDocx(string $chemin): string
    {
        $zip = new ZipArchive;
        if ($zip->open($chemin) !== true) {
            throw new DocumentAnalyseException('Le fichier Word (.docx) est corrompu ou illisible.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            throw new DocumentAnalyseException('Le fichier Word (.docx) ne contient aucune partie textuelle.');
        }

        $xml = preg_replace('/<\/(w:p|w:tr)>/u', "\n", $xml) ?? $xml;
        $xml = preg_replace('/<w:tab\s*\/>/u', "\t", $xml) ?? $xml;

        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function extraireDocBinaire(string $chemin): string
    {
        $contenu = file_get_contents($chemin);
        if ($contenu === false) {
            throw new DocumentAnalyseException('Impossible de lire le fichier Word (.doc).');
        }

        preg_match_all('/[\x20-\x7E\xC0-\xFF]{4,}/', $contenu, $segments);

        return implode("\n", $segments[0] ?? []);
    }
}
