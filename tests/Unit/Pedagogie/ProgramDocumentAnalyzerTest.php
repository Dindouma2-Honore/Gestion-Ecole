<?php

declare(strict_types=1);

namespace Tests\Unit\Pedagogie;

use App\Modules\Pedagogie\Exceptions\DocumentAnalyseException;
use App\Modules\Pedagogie\Services\ProgramDocumentAnalyzer;
use PHPUnit\Framework\TestCase;

class ProgramDocumentAnalyzerTest extends TestCase
{
    private ProgramDocumentAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new ProgramDocumentAnalyzer;
    }

    public function test_interprete_texte_secondaire_avec_chapitres_et_lecons(): void
    {
        $texte = <<<'TXT'
        Chapitre 1 : Les nombres réels et leurs propriétés
        Objectif : Maîtriser le calcul dans R.
        Leçon 1 : Les ensembles de nombres (2h)
        Définition des ensembles N, Z, D, Q et R.
        Leçon 2 : Intervalles et valeur absolue (3h)
        Propriétés des intervalles et distance.

        Chapitre 2 : Les fonctions numériques
        Leçon 1 : Domaine de définition et parité (2h)
        Leçon 2 : Variations et extrema (2h)
        TXT;

        $structure = $this->analyzer->interpreterTexte($texte, 'secondaire');

        $this->assertCount(2, $structure);
        $this->assertEquals('Chapitre 1 : Les nombres réels et leurs propriétés', $structure[0]['titre']);
        $this->assertEquals('chapitre', $structure[0]['type_structure']);
        $this->assertCount(2, $structure[0]['lecons']);
        $this->assertEquals('Lecon 1 : Les ensembles de nombres', $structure[0]['lecons'][0]['titre']);
        $this->assertEquals(2.0, $structure[0]['lecons'][0]['volume_horaire_prevu']);
        $this->assertEquals('Lecon 2 : Intervalles et valeur absolue', $structure[0]['lecons'][1]['titre']);
        $this->assertEquals(3.0, $structure[0]['lecons'][1]['volume_horaire_prevu']);

        $this->assertEquals('Chapitre 2 : Les fonctions numériques', $structure[1]['titre']);
        $this->assertCount(2, $structure[1]['lecons']);
    }

    public function test_interprete_texte_primaire_avec_domaines_et_competences(): void
    {
        $texte = <<<'TXT'
        Unité 1 : La communication orale et écrite
        Compétence 1 : Écouter pour comprendre un message oral (1.5h)
        Activité d'écoute active et restitution.
        Compétence 2 : Identifier les types de phrases (2h)
        Phrases déclaratives, interrogatives et exclamatives.

        Unité 2 : Les opérations fondamentales
        Compétence 1 : L'addition avec retenue (2h)
        Compétence 2 : La soustraction simple (1h)
        TXT;

        $structure = $this->analyzer->interpreterTexte($texte, 'primaire');

        $this->assertCount(2, $structure);
        $this->assertEquals('Unite 1 : La communication orale et écrite', $structure[0]['titre']);
        $this->assertEquals('unite', $structure[0]['type_structure']);
        $this->assertCount(2, $structure[0]['lecons']);
        $this->assertEquals('competence', $structure[0]['lecons'][0]['type_element']);
    }

    public function test_interprete_texte_maternelle_domaines_et_activites(): void
    {
        $texte = <<<'TXT'
        Domaine 1 : Mobiliser le langage dans toutes ses dimensions
        Activité 1 : Découvrir les sons de la langue (1h)
        Activité 2 : Écouter une histoire et raconter (1h)

        Domaine 2 : Construire les premiers outils pour structurer sa pensée
        Activité 1 : Comparer des grandeurs et formes (1h)
        TXT;

        $structure = $this->analyzer->interpreterTexte($texte, 'maternelle');

        $this->assertCount(2, $structure);
        $this->assertEquals('Domaine 1 : Mobiliser le langage dans toutes ses dimensions', $structure[0]['titre']);
        $this->assertEquals('domaine', $structure[0]['type_structure']);
        $this->assertEquals('activite', $structure[0]['lecons'][0]['type_element']);
    }

    public function test_interprete_tableau_de_progression(): void
    {
        $texte = <<<'TXT'
        Module 1 : Algorithmique
        Titre de la leçon | Objectif pédagogique | Volume horaire
        Variables et types de base | Comprendre les entiers et flottants | 2h
        Structures conditionnelles | Maîtriser le Si Alors Sinon | 3h
        TXT;

        $structure = $this->analyzer->interpreterTexte($texte, 'secondaire');

        $this->assertCount(1, $structure);
        $this->assertCount(2, $structure[0]['lecons']);
        $this->assertEquals('Variables et types de base', $structure[0]['lecons'][0]['titre']);
        $this->assertEquals(2.0, $structure[0]['lecons'][0]['volume_horaire_prevu']);
        $this->assertEquals('Structures conditionnelles', $structure[0]['lecons'][1]['titre']);
        $this->assertEquals(3.0, $structure[0]['lecons'][1]['volume_horaire_prevu']);
    }

    public function test_lance_exception_si_document_trop_court_ou_illisible(): void
    {
        $this->expectException(DocumentAnalyseException::class);
        $tempFile = tempnam(sys_get_temp_dir(), 'test_doc_');
        file_put_contents($tempFile, 'Vide');

        try {
            $this->analyzer->analyserFichier($tempFile, 'secondaire');
        } finally {
            unlink($tempFile);
        }
    }
}
