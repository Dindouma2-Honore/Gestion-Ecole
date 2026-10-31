<?php

namespace App\Modules\Pedagogie\Services;

use App\Modules\Pedagogie\Contracts\ProgramDocumentAnalyzer;

final class ProgrammeStructureAnalyzer implements ProgramDocumentAnalyzer
{
    public function analyser(string $texte): array
    {
        $result = [];
        $parent = null;
        foreach (preg_split('/\R/u', $texte) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^(\d+(?:\.\d+)*)(?:[.)-])?\s+(.+)$/u', $line, $outline)) {
                $line = (str_contains($outline[1], '.') ? 'Leçon ' : 'Unité ').$line;
            }
            if (preg_match('/^(chapitre|chapter|unité|unite|séquence|sequence|domaine|module|leçon|lecon|lesson|objectif|compétence|competence|activité|activite)\b[\s:.-]*(.*)$/iu', $line, $m)) {
                $type = match (mb_strtolower($m[1])) {
                    'chapter' => 'chapitre','unité','unite','domaine','module' => 'unite','séquence','sequence' => 'sequence','leçon','lecon','lesson' => 'lecon','compétence','competence' => 'competence','activité','activite' => 'activite',default => mb_strtolower($m[1])
                };
                $isParent = in_array($type, ['chapitre', 'unite', 'sequence']);
                $index = count($result);
                $result[] = ['titre' => mb_substr($line, 0, 200), 'type' => $type, 'parent_index' => $isParent ? null : $parent, 'contenu' => '', 'volume_horaire_prevu' => null];
                if ($isParent) {
                    $parent = $index;
                }
            } elseif ($result !== []) {
                $result[array_key_last($result)]['contenu'] .= ($result[array_key_last($result)]['contenu'] !== '' ? "\n" : '').$line;
            }
            if (count($result) > 500) {
                throw new \RuntimeException('Structure trop volumineuse, veuillez répartir le document.');
            }
        }
        if ($result === []) {
            throw new \RuntimeException('La structure n’a pas pu être identifiée ; corrigez les titres du document puis importez une nouvelle version.');
        }

        return $result;
    }
}
