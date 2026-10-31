<?php

declare(strict_types=1);

namespace Tests\Unit\Pedagogie;

use App\Modules\Pedagogie\Services\ProgrammeDocumentParser;
use PHPUnit\Framework\TestCase;

class ProgrammeDocumentParserTest extends TestCase
{
    public function test_interprete_les_chapitres_et_leurs_parties(): void
    {
        $chapitres = (new ProgrammeDocumentParser)->interpreterTexte(<<<'TEXT'
Chapitre 1 : Les nombres entiers
Partie A : Lecture
Lire et écrire les nombres.
Partie B : Comparaison
Comparer et ordonner les nombres.
Chapitre 2 - Les fractions
Section 1 : Définition
Comprendre le numérateur et le dénominateur.
TEXT);

        $this->assertCount(2, $chapitres);
        $this->assertSame('Chapitre 1 : Les nombres entiers', $chapitres[0]['titre']);
        $this->assertCount(2, $chapitres[0]['parties']);
        $this->assertSame('Partie A : Lecture', $chapitres[0]['parties'][0]['titre']);
        $this->assertStringContainsString('Comparer et ordonner', $chapitres[0]['contenu']);
    }

    public function test_accepte_un_programme_de_seize_chapitres(): void
    {
        $texte = implode("\n", array_map(
            fn (int $numero): string => "Chapitre {$numero} : Titre {$numero}\nPartie A : Notions\nContenu {$numero}",
            range(1, 16),
        ));

        $chapitres = (new ProgrammeDocumentParser)->interpreterTexte($texte);

        $this->assertCount(16, $chapitres);
        $this->assertSame('Chapitre 16 : Titre 16', $chapitres[15]['titre']);
    }
}
