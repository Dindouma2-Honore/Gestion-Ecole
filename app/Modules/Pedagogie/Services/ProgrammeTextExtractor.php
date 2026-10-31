<?php

namespace App\Modules\Pedagogie\Services;

use App\Modules\Pedagogie\Contracts\DocumentExtractor;
use Smalot\PdfParser\Parser;
use Symfony\Component\Process\Process;
use ZipArchive;

final class ProgrammeTextExtractor implements DocumentExtractor
{
    public function extraire(string $chemin, string $extension): string
    {
        $texte = match ($extension) {
            'pdf' => (new Parser)->parseFile($chemin)->getText(),
            'docx' => $this->docx($chemin),
            'doc' => $this->doc($chemin),
            default => throw new \RuntimeException('Format documentaire non pris en charge.'),
        };
        if (mb_strlen(trim($texte)) < 10) {
            throw new \RuntimeException('Aucun texte exploitable : un PDF scanné nécessite une reconnaissance OCR.');
        }
        if (strlen($texte) > 2000000) {
            throw new \RuntimeException('Document trop volumineux pour cette analyse.');
        }

        return $texte;
    }

    private function docx(string $path): string
    {
        $z = new ZipArchive;
        if ($z->open($path) !== true) {
            throw new \RuntimeException('Archive Word invalide.');
        }
        try {
            $meta = $z->statName('word/document.xml');
            if (! $meta || $meta['size'] > 2000000) {
                throw new \RuntimeException('Contenu Word invalide ou trop volumineux.');
            }
            $xml = $z->getFromName('word/document.xml');
            if ($xml === false) {
                throw new \RuntimeException('Contenu Word absent.');
            }
            $xml = preg_replace_callback('~<w:p(?:\s[^>]*)?>.*?</w:p>~s', static function ($m) {
                $paragraph = $m[0];
                if (preg_match('~<w:pStyle[^>]*w:val="(?:Heading|Titre)([1-6])"~i', $paragraph, $heading)) {
                    return ($heading[1] === '1' ? 'Unité : ' : 'Leçon : ').$paragraph;
                }

                return $paragraph;
            }, $xml);

            return html_entity_decode(strip_tags(preg_replace('~</w:(p|tr)>~', "\n", $xml)), ENT_QUOTES | ENT_XML1, 'UTF-8');
        } finally {
            $z->close();
        }
    }

    private function doc(string $path): string
    {
        $p = new Process(['antiword', $path]);
        $p->setTimeout(40);
        $p->mustRun();

        return $p->getOutput();
    }
}
