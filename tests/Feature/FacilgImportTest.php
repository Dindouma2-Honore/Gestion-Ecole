<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Socle\Models\Niveau;
use App\Support\Facilg\FacilgSqlDump;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class FacilgImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_sql_reader_handles_commas_quotes_and_null_values(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'facilg-');
        file_put_contents($path, "INSERT INTO `eleves` (`ID_El`, `Nom_El`, `Observation_El`) VALUES\n('E1', 'O\\'NEIL, Joel', NULL);\n");

        $rows = iterator_to_array(app(FacilgSqlDump::class)->rows($path, 'eleves'));

        $this->assertSame('E1', $rows[0]['ID_El']);
        $this->assertSame("O'NEIL, Joel", $rows[0]['Nom_El']);
        $this->assertNull($rows[0]['Observation_El']);
        unlink($path);
    }

    public function test_import_is_idempotent_and_unrecognized_classes_are_not_assigned(): void
    {
        foreach (['Maternelle', 'Primaire', 'Secondaire'] as $index => $name) {
            Niveau::create(['nom' => $name, 'code' => 'FAC'.$index, 'ordre' => $index]);
        }
        $path = tempnam(sys_get_temp_dir(), 'facilg-');
        file_put_contents($path, <<<'SQL'
INSERT INTO `classes` (`ID_Cl`, `AnneeScol`, `Nom_Cl`, `Abr_Cl`, `ID_Em`, `ID_Em1`, `NoteAdm_Cl`, `Cycle_Cl`, `Eff_Cl`, `Section_Cl`, `NumOrdre_Cl`) VALUES
('C1', '2025/2026', 'NURSERY 1', 'N1', NULL, '', 10, 'PRIMARY', 30, 'Anglophone', 1),
('C2', '2025/2026', 'CLASSE INCONNUE', 'XX', NULL, '', 10, 'PRIMARY', 30, 'Francophone', 2);
INSERT INTO `eleves` (`ID_El`, `AnneeScol`, `Nom_El`, `Prenom_El`, `DateNaiss_El`, `LieuNaiss_El`, `Matricule_El`, `ID_Cl`, `Redouble_El`, `AncienEtab_El`, `Sexe_El`, `NomTuteur_El`, `TelTuteur_El`, `NomPere_El`, `NomMere_El`, `Observation_El`, `Nationalite_El`, `Transfere_El`, `NumDecision_El`, `ExcFraisExam`, `Demission_El`, `ExcluScol_El`, `Statut_El`, `InfoSMS`, `EMail_El`, `RefPhoto_El`, `MoyEl`, `ExclusInsc`, `BonusScol`) VALUES
('E1', '2025/2026', 'DOE', 'Jane', '2020-01-02', 'Douala', 'MAT-1', 'C1', 'Non', '', 'F', 'DOE John', 677000000, '', '', '', 'Camerounaise', 'Non', '', 'Non', 'Non', 'Non', '', 'Oui', '', '', 0, 'Non', 0);
SQL);

        $this->assertSame(0, Artisan::call('facilg:import', ['dump' => $path]));
        $this->assertSame(0, Artisan::call('facilg:import', ['dump' => $path]));

        $this->assertDatabaseCount('eleves', 1);
        $this->assertDatabaseCount('parents_tuteurs', 1);
        $this->assertDatabaseCount('inscriptions', 1);
        $this->assertDatabaseHas('classes', ['nom' => 'NURSERY 1']);
        $this->assertDatabaseMissing('classes', ['nom' => 'CLASSE INCONNUE']);
        unlink($path);
    }
}
