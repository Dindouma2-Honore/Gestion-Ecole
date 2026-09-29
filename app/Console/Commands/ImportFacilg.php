<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Facilg\FacilgImportReport;
use App\Support\Facilg\FacilgSqlDump;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

class ImportFacilg extends Command
{
    protected $signature = 'facilg:import {dump : Chemin du dump SQL FACILG} {--dry-run : Analyse sans écriture}';

    protected $description = 'Importe les données historiques FACILG avec traçabilité et rapport détaillé';

    private string $dumpPath;

    private int $systemUserId;

    public function handle(FacilgSqlDump $dump, FacilgImportReport $report): int
    {
        $path = (string) $this->argument('dump');
        $realPath = realpath($path);
        if ($realPath === false || ! is_readable($realPath)) {
            $this->error("Dump introuvable ou illisible : {$path}");

            return self::FAILURE;
        }
        $this->dumpPath = $realPath;

        try {
            DB::beginTransaction();
            $this->systemUserId = $this->ensureSystemUser();
            $years = $this->importYears($dump, $report);
            $employees = $this->importEmployees($dump, $report, $years);
            $classes = $this->importClasses($dump, $report, $years, $employees);
            [$students, $registrations] = $this->importStudents($dump, $report, $years, $classes);
            $this->importFees($dump, $report, $years, $classes, $students);
            $this->importPayments($dump, $report, $years, $students, $registrations);
            $this->importExpenses($dump, $report, $years);
            $this->importClosures($dump, $report, $years);
            $this->importSubjects($dump, $report, $years, $classes, $employees);
            $this->importUsers($dump, $report);
            $this->importSettingsOnce($dump, $report);

            $this->option('dry-run') ? DB::rollBack() : DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $rows = [];
        foreach ($report->stats() as $table => $stats) {
            $rows[] = [$table, $stats['importees'], $stats['ignorees'], $stats['erreurs']];
        }
        $this->table(['Table FACILG', 'Importées', 'Ignorées', 'Erreurs'], $rows);
        foreach (array_slice($report->messages(), 0, 30) as $message) {
            $this->warn($message);
        }
        $this->info($this->option('dry-run') ? 'Analyse terminée : aucune écriture conservée.' : 'Import FACILG terminé. Rapport : storage/logs/facilg-import.log');

        return self::SUCCESS;
    }

    /** @return array<string, int> */
    private function importYears(FacilgSqlDump $dump, FacilgImportReport $report): array
    {
        $labels = [];
        foreach (['employes', 'classes', 'eleves', 'fraisscole', 'recettes'] as $table) {
            foreach ($dump->rows($this->dumpPath, $table) as $row) {
                if ($label = $this->text($row['AnneeScol'] ?? null)) {
                    $labels[$label] = true;
                }
            }
        }
        $result = [];
        foreach (array_keys($labels) as $label) {
            if (! preg_match('/^(\d{4})\D+(\d{4})$/', $label, $parts)) {
                $report->warning('annees_scolaires', "Année scolaire non reconnue : {$label}");

                continue;
            }
            $legacy = "annee:{$label}";
            $targetLabel = "{$parts[1]}-{$parts[2]}";
            $existingId = DB::table('annees_scolaires')->where('libelle', $targetLabel)->value('id');
            if ($existingId) {
                DB::table('annees_scolaires')->where('id', $existingId)->whereNull('facilg_legacy_id')->update(['facilg_legacy_id' => $legacy]);
                $report->count('annees_scolaires', 'ignorees');
                $result[$label] = (int) $existingId;

                continue;
            }
            $result[$label] = $this->upsert('annees_scolaires', $legacy, [
                'libelle' => $targetLabel,
                'date_debut' => "{$parts[1]}-09-01",
                'date_fin' => "{$parts[2]}-08-31",
                'statut' => 'archivee',
            ], $report, 'annees_scolaires');
        }

        return $result;
    }

    /** @param array<string, int> $years @return array<string, int> */
    private function importEmployees(FacilgSqlDump $dump, FacilgImportReport $report, array $years): array
    {
        $functions = [];
        foreach ($dump->rows($this->dumpPath, 'fonctions') as $row) {
            $functions[$this->key($row['ID_F'], $row['AnneeScol'])] = $row;
        }
        $hourly = [];
        foreach ($dump->rows($this->dumpPath, 'horairemensuel') as $row) {
            if ((float) ($row['NbreHeure_HM'] ?? 0) > 0) {
                $hourly[$this->key($row['ID_Em'], $row['AnneeScol'])] = true;
            }
        }
        $employees = [];
        foreach ($dump->rows($this->dumpPath, 'employes') as $row) {
            $sourceId = $this->text($row['ID_Em'] ?? null);
            $year = $this->text($row['AnneeScol'] ?? null);
            if (! $sourceId || ! isset($years[$year])) {
                $report->warning('employes', 'Employé sans identifiant ou année résolue.');

                continue;
            }
            $legacy = "employe:{$sourceId}";
            $function = $functions[$this->key($row['ID_F1'] ?? '', $year)] ?? null;
            $functionName = $this->text($function['Nom_F'] ?? null) ?: 'Poste FACILG à reclasser';
            $roleId = null;
            $posteId = null;
            if (preg_match('/ENSEIGN|TEACHER/i', $functionName)) {
                $roleId = DB::table('roles')->where('name', 'Enseignant')->value('id');
            } else {
                $posteId = DB::table('postes_administratifs')->where('nom', $functionName)->value('id')
                    ?: DB::table('postes_administratifs')->insertGetId(['nom' => $functionName, 'description' => $this->text($function['Detail_F'] ?? null), 'actif' => true, 'facilg_legacy_id' => 'fonction:'.($row['ID_F1'] ?? 'inconnue'), 'created_at' => now(), 'updated_at' => now()]);
                $report->warning('fonctions', "Fonction administrative/ambiguë à confirmer : {$functionName}");
            }
            $employeeId = $this->upsert('employes', $legacy, [
                'matricule' => $this->text($row['Matricule_Em'] ?? null) ?: $sourceId,
                'nom' => $this->text($row['Nom_Em'] ?? null) ?: 'INCONNU',
                'prenom' => $this->text($row['Prenom_Em'] ?? null) ?: '',
                'date_naissance' => $this->date($row['DateNaiss_Em'] ?? null),
                'sexe' => $this->sex($row['Sexe_Em'] ?? null),
                'telephone' => $this->text($row['Tel_Em'] ?? null),
                'email' => filter_var($row['EMail_Em'] ?? null, FILTER_VALIDATE_EMAIL) ?: null,
                'photo' => $this->text($row['Photo_Em'] ?? null),
                'role_id' => $roleId,
                'poste_administratif_id' => $posteId,
                'poste' => $functionName,
                'date_embauche' => $this->yearStart($year),
                'statut' => 'actif',
            ], $report, 'employes');
            $employees[$this->key($sourceId, $year)] = $employeeId;
            $employees[$sourceId] = $employeeId;

            $contractId = $this->upsert('contrats', "contrat:{$sourceId}:{$year}", [
                'employe_id' => $employeeId,
                'type' => str_contains(strtoupper((string) ($row['Nature_Em'] ?? '')), 'VACAT') ? 'vacation' : 'CDI',
                'categorie_paie' => isset($hourly[$this->key($sourceId, $year)]) ? 'horaire' : 'fixe',
                'date_debut' => $this->yearStart($year),
                'salaire_base' => max(0, (float) ($row['SalaireBase_Em'] ?? 0)),
                'statut' => 'actif',
            ], $report, 'contrats');
            DB::table('employes')->where('id', $employeeId)->update(['contrat_id' => $contractId]);
            $this->importEmployeeDiplomas($dump, $report, $row, $employeeId, $year);
            $this->importEmployeePremium($report, $row, $employeeId, $year);
        }

        return $employees;
    }

    /** @param array<string, int> $years @param array<string, int> $employees @return array<string, int> */
    private function importClasses(FacilgSqlDump $dump, FacilgImportReport $report, array $years, array $employees): array
    {
        $classes = [];
        foreach ($dump->rows($this->dumpPath, 'classes') as $row) {
            $name = $this->text($row['Nom_Cl'] ?? null);
            $year = $this->text($row['AnneeScol'] ?? null);
            $levelName = $this->classLevel($name);
            if (! $levelName) {
                $report->warning('classes', "Classe non reconnue, laissée en attente : {$name} ({$year})");

                continue;
            }
            if (! isset($years[$year])) {
                $report->warning('classes', "Année introuvable pour {$name} : {$year}");

                continue;
            }
            $levelId = DB::table('niveaux')->where('nom', $levelName)->value('id');
            if (! $levelId) {
                $levelId = $this->upsert('niveaux', 'niveau-facilg:'.Str::slug($levelName), [
                    'nom' => $levelName,
                    'code' => 'FAC-'.strtoupper(Str::slug($levelName)),
                    'ordre' => match ($levelName) {
                        'Maternelle' => 10, 'Primaire' => 20, default => 30
                    },
                    'description' => 'Niveau de regroupement explicite pour la reprise FACILG',
                ], $report, 'classes');
            }
            $sourceId = $this->text($row['ID_Cl']);
            $legacy = $this->key($sourceId, $year);
            $sectionId = DB::table('sections_scolaires')->where('nom', $this->text($row['Section_Cl'] ?? null))->value('id');
            $existingClassId = DB::table('classes')->where('nom', $name)->where('annee_scolaire_id', $years[$year])->value('id');
            if ($existingClassId) {
                DB::table('classes')->where('id', $existingClassId)->whereNull('facilg_legacy_id')->update(['facilg_legacy_id' => "classe:{$legacy}"]);
                $classes[$legacy] = (int) $existingClassId;
                $report->count('classes', 'ignorees');

                continue;
            }
            $classes[$legacy] = $this->upsert('classes', "classe:{$legacy}", [
                'nom' => $name,
                'code' => 'FAC-'.substr(sha1($legacy), 0, 12),
                'niveau_id' => $levelId,
                'section_id' => $sectionId,
                'annee_scolaire_id' => $years[$year],
                'capacite_max' => max(1, (int) ($row['Eff_Cl'] ?? 1)),
                'professeur_principal_id' => $employees[$this->key($row['ID_Em'] ?? '', $year)] ?? null,
                'professeur_adjoint_id' => $employees[$this->key($row['ID_Em1'] ?? '', $year)] ?? null,
            ], $report, 'classes');
        }

        return $classes;
    }

    /** @param array<string, int> $years @param array<string, int> $classes @return array{array<string,int>,array<string,int>} */
    private function importStudents(FacilgSqlDump $dump, FacilgImportReport $report, array $years, array $classes): array
    {
        $students = [];
        $registrations = [];
        foreach ($dump->rows($this->dumpPath, 'eleves') as $row) {
            $year = $this->text($row['AnneeScol'] ?? null);
            $sourceId = $this->text($row['ID_El'] ?? null);
            $matricule = $this->text($row['Matricule_El'] ?? null) ?: $sourceId;
            if (! $sourceId || ! isset($years[$year])) {
                $report->warning('eleves', "Élève sans identifiant/année : {$sourceId}");

                continue;
            }
            $notes = array_filter([
                $this->text($row['Observation_El'] ?? null),
                $this->text($row['NomPere_El'] ?? null) ? 'Père : '.$this->text($row['NomPere_El']) : null,
                $this->text($row['NomMere_El'] ?? null) ? 'Mère : '.$this->text($row['NomMere_El']) : null,
            ]);
            $studentId = $this->upsert('eleves', "eleve:{$matricule}", [
                'matricule_permanent' => $matricule,
                'nom' => $this->text($row['Nom_El'] ?? null) ?: 'INCONNU',
                'prenom' => $this->text($row['Prenom_El'] ?? null) ?: '',
                'date_naissance' => $this->date($row['DateNaiss_El'] ?? null),
                'lieu_naissance' => $this->text($row['LieuNaiss_El'] ?? null),
                'sexe' => $this->sex($row['Sexe_El'] ?? null),
                'nationalite' => $this->text($row['Nationalite_El'] ?? null),
                'observation' => implode("\n", $notes) ?: null,
                'photo' => $this->text($row['RefPhoto_El'] ?? null),
                'statut' => 'inscrit',
            ], $report, 'eleves');
            $students[$this->key($sourceId, $year)] = $studentId;
            $students[$sourceId] = $studentId;

            $guardian = $this->text($row['NomTuteur_El'] ?? null) ?: $this->text($row['NomPere_El'] ?? null) ?: $this->text($row['NomMere_El'] ?? null);
            if (! $guardian) {
                $guardian = 'Tuteur à compléter';
                $report->warning('parents_tuteurs', "Aucun tuteur/père/mère pour l’élève {$matricule}");
            }
            $parentId = $this->upsert('parents_tuteurs', "parent-eleve:{$matricule}", [
                'nom' => $guardian,
                'prenom' => '',
                'telephone' => $this->text($row['TelTuteur_El'] ?? null),
                'portail_actif' => false,
            ], $report, 'parents_tuteurs');
            DB::table('eleve_parent')->updateOrInsert(['eleve_id' => $studentId, 'parent_id' => $parentId], [
                'lien' => 'tuteur', 'responsable_legal' => true, 'responsable_paiement' => true,
                'autorise_recuperation' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);

            $classKey = $this->key($row['ID_Cl'] ?? '', $year);
            if (! isset($classes[$classKey])) {
                $report->warning('inscriptions', "Classe non résolue pour {$matricule}, année {$year}");

                continue;
            }
            $status = $this->registrationStatus($row);
            $registrationKey = $this->key($sourceId, $year);
            $existingRegistrationId = DB::table('inscriptions')->where('eleve_id', $studentId)->where('annee_scolaire_id', $years[$year])->value('id');
            if ($existingRegistrationId) {
                $registrations[$registrationKey] = (int) $existingRegistrationId;
                $report->warning('inscriptions', "Doublon FACILG pour le matricule {$matricule} et l’année {$year}; inscription existante conservée.");

                continue;
            }
            $registrations[$registrationKey] = $this->upsert('inscriptions', "inscription:{$registrationKey}", [
                'eleve_id' => $studentId, 'classe_id' => $classes[$classKey], 'annee_scolaire_id' => $years[$year],
                'type' => count(array_filter($students, fn (int $id): bool => $id === $studentId)) > 2 ? 'reinscription' : 'inscription',
                'date_inscription' => $this->yearStart($year), 'statut' => $status,
            ], $report, 'inscriptions');
        }

        return [$students, $registrations];
    }

    /** @param array<string, int> $years @param array<string, int> $classes @param array<string, int> $students */
    private function importFees(FacilgSqlDump $dump, FacilgImportReport $report, array $years, array $classes, array $students): void
    {
        $schoolGroup = DB::table('groupes_frais')->where('code', 'scolarite')->value('id');
        $otherGroup = DB::table('groupes_frais')->where('code', 'autres')->value('id');
        foreach ($dump->rows($this->dumpPath, 'fraisscole') as $row) {
            $year = $this->text($row['AnneeScol']);
            $sourceKey = $this->key($row['ID_Cl'], $year);
            if (! isset($classes[$sourceKey], $years[$year])) {
                $report->warning('fraisscole', "Classe/année introuvable : {$sourceKey}");

                continue;
            }
            $tranches = [];
            foreach (range(1, 5) as $order) {
                $amount = (float) ($row["Tranche{$order}"] ?? 0);
                if ($amount <= 0) {
                    continue;
                }
                $tranches[] = ['ordre' => $order, 'montant' => $amount, 'date' => $this->date($row["DateLimTr{$order}"] ?? null) ?: $this->yearEnd($year)];
            }
            $registrationFee = (float) ($row['Insc'] ?? 0);
            $total = $registrationFee + array_sum(array_column($tranches, 'montant'));
            $configData = [
                'classe_id' => $classes[$sourceKey], 'annee_scolaire_id' => $years[$year], 'montant_total' => $total,
                'politique_validation_inscription' => 'frais_inscription', 'montant_minimum_inscription' => $registrationFee ?: null, 'actif' => true,
            ];
            $existingConfigId = DB::table('configurations_frais_classe')->where('classe_id', $classes[$sourceKey])->where('annee_scolaire_id', $years[$year])->value('id');
            if ($existingConfigId) {
                DB::table('configurations_frais_classe')->where('id', $existingConfigId)->update([...$configData, 'facilg_legacy_id' => "frais-classe:{$sourceKey}", 'updated_at' => now()]);
                $configId = (int) $existingConfigId;
                $report->count('fraisscole', 'ignorees');
            } else {
                $configId = $this->upsert('configurations_frais_classe', "frais-classe:{$sourceKey}", $configData, $report, 'fraisscole');
            }
            foreach ($tranches as $tranche) {
                $trancheData = [
                    'configuration_frais_classe_id' => $configId, 'ordre' => $tranche['ordre'], 'libelle' => 'Tranche '.$tranche['ordre'],
                    'montant' => $tranche['montant'], 'date_echeance' => $tranche['date'], 'actif' => true,
                ];
                $existingTrancheId = DB::table('tranches_frais_classe')->where('configuration_frais_classe_id', $configId)->where('ordre', $tranche['ordre'])->value('id');
                if ($existingTrancheId) {
                    DB::table('tranches_frais_classe')->where('id', $existingTrancheId)->update([...$trancheData, 'facilg_legacy_id' => "tranche:{$sourceKey}:{$tranche['ordre']}", 'updated_at' => now()]);
                    $report->count('fraisscole', 'ignorees');
                } else {
                    $this->upsert('tranches_frais_classe', "tranche:{$sourceKey}:{$tranche['ordre']}", $trancheData, $report, 'fraisscole');
                }
            }
            foreach (['APE' => 'APE', 'Exam' => 'Frais d’examen'] as $column => $name) {
                $amount = (float) ($row[$column] ?? 0);
                if ($amount <= 0) {
                    continue;
                }
                $catalogId = $this->upsert('catalogue_frais_divers', "catalogue:{$column}:{$year}", [
                    'nom' => $name, 'categorie' => $column === 'Exam' ? 'examen' : 'autre', 'groupe_frais_id' => $otherGroup,
                    'montant_defaut' => $amount, 'actif' => true,
                ], $report, 'fraisscole');
                foreach ($students as $sourceStudent => $studentId) {
                    if (! str_contains($sourceStudent, '|'.$year)) {
                        continue;
                    }
                    $this->upsert('frais_divers_eleves', "frais-divers:{$column}:{$sourceStudent}", [
                        'catalogue_frais_divers_id' => $catalogId, 'eleve_id' => $studentId, 'annee_scolaire_id' => $years[$year],
                        'montant' => $amount, 'statut' => 'actif',
                    ], $report, 'fraisscole');
                }
            }
            if ($registrationFee > 0) {
                $this->upsert('types_frais_recurrents', "inscription:{$sourceKey}", [
                    'nom' => 'Frais d’inscription '.$sourceKey, 'nature' => 'inscription', 'groupe_frais_id' => $schoolGroup, 'niveau_id' => null,
                    'annee_scolaire_id' => $years[$year], 'montant' => $registrationFee, 'ratio_tranche_1' => 50, 'actif' => true,
                ], $report, 'fraisscole');
            }
        }
    }

    /** @param array<string, int> $years @param array<string, int> $students @param array<string, int> $registrations */
    private function importPayments(FacilgSqlDump $dump, FacilgImportReport $report, array $years, array $students, array $registrations): void
    {
        $rows = iterator_to_array($dump->rows($this->dumpPath, 'recettes'));
        usort($rows, fn (array $a, array $b): int => strcmp(($a['Date_R'] ?? '').' '.($a['Heure_R'] ?? ''), ($b['Date_R'] ?? '').' '.($b['Heure_R'] ?? '')));
        foreach ($rows as $row) {
            $year = $this->text($row['AnneeScol']);
            $studentKey = $this->key($row['ID_El'], $year);
            if (! isset($students[$studentKey], $years[$year])) {
                $report->warning('recettes', "Élève introuvable pour recette {$row['ID_R']}");

                continue;
            }
            $amount = (float) ($row['Montant'] ?? 0);
            if ($amount <= 0) {
                $report->count('recettes', 'ignorees');

                continue;
            }
            $registrationId = $registrations[$studentKey] ?? null;
            $remaining = $registrationId ? $this->remainingForRegistration($registrationId) : 0.0;
            if ($registrationId && $amount > $remaining + 0.001) {
                $report->warning('recettes', "Versement {$row['ID_R']} de {$amount} supérieur au reste dû {$remaining}");

                continue;
            }
            $legacy = 'recette:'.$this->key($row['ID_R'], $row['ID_El'], $year, $row['Period_R']);
            $paymentId = $this->upsert('paiements', $legacy, [
                'eleve_id' => $students[$studentKey], 'annee_scolaire_id' => $years[$year], 'inscription_id' => $registrationId,
                'versement_reference' => $this->uuid($legacy), 'rubrique' => 'tranches_classe', 'montant' => $amount,
                'mode' => $this->paymentMode($row['ModePays'] ?? null), 'numero_recu' => 'FACILG-'.$this->key($row['ID_R'], $row['ID_El'], $year),
                'statut' => preg_match('/ANNUL|REJET/i', (string) ($row['Statut_R'] ?? '')) ? 'annule' : 'valide',
                'encaisse_par' => $this->systemUserId, 'created_at' => $this->dateTime($row['Date_R'] ?? null, $row['Heure_R'] ?? null),
            ], $report, 'recettes');
            if ($registrationId) {
                $this->allocatePayment($paymentId, $registrationId, $amount);
            }
        }
    }

    /** @param array<string, int> $years */
    private function importExpenses(FacilgSqlDump $dump, FacilgImportReport $report, array $years): void
    {
        $rubricId = $this->upsert('rubriques_depenses', 'rubrique:facilg-divers', ['nom' => 'Divers FACILG', 'active' => true, 'cree_par' => $this->systemUserId], $report, 'bonsortie');
        foreach ($dump->rows($this->dumpPath, 'bonsortie') as $row) {
            $year = $this->text($row['AnneeScol']);
            if (! isset($years[$year])) {
                $report->warning('bonsortie', "Année inconnue pour {$row['Id_B']}");

                continue;
            }
            $this->upsert('depenses', 'bonsortie:'.$this->key($row['Id_B'], $year), [
                'annee_scolaire_id' => $years[$year], 'rubrique_depense_id' => $rubricId,
                'libelle' => $this->text($row['Nature_B'] ?? null) ?: 'Décaissement FACILG', 'montant' => max(0, (float) ($row['Montant_B'] ?? 0)),
                'date_depense' => $this->date($row['Date_B'] ?? null) ?: $this->yearStart($year), 'statut' => 'payee',
                'motif' => $this->text($row['Detail'] ?? null), 'cree_par' => $this->systemUserId,
                'valide_par' => $this->systemUserId, 'validee_le' => $this->dateTime($row['Date_B'] ?? null, $row['Heure_B'] ?? null),
                'payee_le' => $this->dateTime($row['Date_B'] ?? null, $row['Heure_B'] ?? null),
            ], $report, 'bonsortie');
        }
        foreach ($dump->rows($this->dumpPath, 'updatebonsortie') as $row) {
            $legacy = 'updatebonsortie:'.$this->key($row['Id_B'], $row['AnneeScol'], $row['Date_Upd']);
            $this->upsert('facilg_import_audits', $legacy, [
                'source_table' => 'depenses', 'source_type' => 'bonsortie',
                'source_id' => DB::table('depenses')->where('facilg_legacy_id', 'bonsortie:'.$this->key($row['Id_B'], $row['AnneeScol']))->value('id'),
                'action' => $this->text($row['StatutUpd'] ?? null) ?: 'modification', 'motif' => $this->text($row['Motif_Upd'] ?? null),
                'created_at' => $this->date($row['Date_Upd'] ?? null) ?: now(),
            ], $report, 'updatebonsortie');
        }
    }

    /** @param array<string, int> $years @param array<string, int> $classes @param array<string, int> $employees */
    private function importSubjects(FacilgSqlDump $dump, FacilgImportReport $report, array $years, array $classes, array $employees): void
    {
        $categoryId = DB::table('categories_matieres')->where('code', 'NON_CLASSEE')->value('id');
        $subjects = [];
        foreach ($dump->rows($this->dumpPath, 'matieres') as $row) {
            $legacy = 'matiere:'.$this->key($row['ID_Mat'], $row['AnneeScol']);
            $subjects[$this->key($row['ID_Mat'], $row['AnneeScol'])] = $this->upsert('matieres', $legacy, [
                'categorie_matiere_id' => $categoryId, 'nom' => $this->text($row['Nom_Mat'] ?? null) ?: $row['ID_Mat'],
                'code' => 'FAC-'.substr(sha1($legacy), 0, 12), 'coefficient' => 1, 'description' => $this->text($row['Detail_Mat'] ?? null), 'actif' => true,
            ], $report, 'matieres');
        }

        foreach ($dump->rows($this->dumpPath, 'matiereclasses') as $row) {
            $year = $this->text($row['AnneeScol']);
            $classId = $classes[$this->key($row['ID_Cl'], $year)] ?? null;
            $subjectId = $subjects[$this->key($row['ID_Mat'], $year)] ?? null;
            $teacherId = $employees[$this->key($row['ID_Em'], $year)] ?? null;
            if (! $classId || ! $subjectId || ! $teacherId || ! isset($years[$year])) {
                $report->warning('matiereclasses', 'Affectation incomplète : '.$this->key($row['ID_Cl'], $row['ID_Mat'], $row['ID_Em'], $year));

                continue;
            }
            $this->upsert('affectations_pedagogiques', 'matiereclasse:'.$this->key($row['ID_Cl'], $row['ID_Mat'], $year), [
                'enseignant_id' => $teacherId, 'matiere_id' => $subjectId, 'classe_id' => $classId,
                'annee_scolaire_id' => $years[$year], 'actif' => true,
            ], $report, 'matiereclasses');
        }

        $noteCount = iterator_count($dump->rows($this->dumpPath, 'notes'));
        for ($index = 0; $index < $noteCount; $index++) {
            $report->count('notes', 'ignorees');
        }
        $report->warning('notes', 'Import différé : le dump ne contient aucune date de séquence et aucune période historique cible avec date de fin. Aucune date d’évaluation n’a été inventée.');
    }

    /** @param array<string, int> $years */
    private function importClosures(FacilgSqlDump $dump, FacilgImportReport $report, array $years): void
    {
        foreach ($dump->rows($this->dumpPath, 'decomptage') as $row) {
            $year = $this->text($row['AnneeScol']);
            $detail = [
                '10000' => (int) ($row['Billet10'] ?? 0), '5000' => (int) ($row['Billet5'] ?? 0),
                '2000' => (int) ($row['Billet2'] ?? 0), '1000' => (int) ($row['Billet01'] ?? 0),
                '500' => (int) ($row['Billet05'] ?? 0), '50' => (int) ($row['Pieces50'] ?? 0),
                '25' => (int) ($row['Pieces25'] ?? 0), '10' => (int) ($row['Pieces10'] ?? 0),
                '5' => (int) (($row['Pieces05'] ?? 0) + ($row['Pieces5'] ?? 0)),
                '1' => (int) (($row['Pieces01'] ?? 0) + ($row['Pieces1'] ?? 0)),
            ];
            $total = array_sum(array_map(fn (int $count, string $value): int => $count * (int) $value, $detail, array_keys($detail)));
            $dateTime = $this->dateTime($row['Date_D'] ?? null, $row['Heure_D'] ?? null);
            $calculated = (float) DB::table('paiements')->where('statut', 'valide')->whereDate('created_at', substr($dateTime, 0, 10))->sum('montant')
                - (float) DB::table('depenses')->where('statut', 'payee')->whereDate('payee_le', substr($dateTime, 0, 10))->sum('montant');
            $this->upsert('facilg_clotures_caisse', 'decomptage:'.$this->key($row['Id_E'], $row['Date_D'], $row['Heure_D'], $year), [
                'annee_scolaire_id' => $years[$year] ?? null, 'cloturee_le' => $dateTime, 'nature' => 'Décompte FACILG',
                'total_decompte' => $total, 'detail_coupures' => json_encode($detail),
                'total_recalcule' => $calculated, 'ecart' => $total - $calculated,
            ], $report, 'decomptage');
        }
        foreach ($dump->rows($this->dumpPath, 'cloturations') as $row) {
            $year = $this->text($row['AnneeScol']);
            $this->upsert('facilg_clotures_caisse', 'cloturation:'.$this->key($row['Date_Cl'], $year, $row['Nature_Cl']), [
                'annee_scolaire_id' => $years[$year] ?? null, 'cloturee_le' => $this->dateTime($row['Date_Cl'] ?? null, $row['Heure_Cl'] ?? null),
                'nature' => $this->text($row['Nature_Cl'] ?? null) ?: 'Clôture FACILG',
            ], $report, 'cloturations');
        }
    }

    private function importUsers(FacilgSqlDump $dump, FacilgImportReport $report): void
    {
        $groups = [];
        foreach ($dump->rows($this->dumpPath, 'groupesuser') as $row) {
            $groups[$this->key($row['Id_G'], $row['AnneeScol'])] = strtoupper($this->text($row['Nom_G'] ?? null));
        }
        foreach ($dump->rows($this->dumpPath, 'utilisateurs') as $row) {
            $sourceId = $this->text($row['ID_E']);
            $year = $this->text($row['AnneeScol']);
            $userId = $this->upsert('users', 'utilisateur:'.$sourceId, [
                'name' => "Utilisateur FACILG {$sourceId}", 'email' => strtolower($sourceId).'@facilg.import.local',
                'password' => Hash::make(Str::random(48)), 'statut' => 'actif', 'must_change_password' => true,
            ], $report, 'utilisateurs');
            $code = $groups[$this->key($row['ID_G'] ?? '', $year)] ?? strtoupper($this->text($row['ID_G'] ?? null));
            $roleName = config("facilg.roles_utilisateurs.{$code}");
            if ($roleName && $roleId = DB::table('roles')->where('name', $roleName)->value('id')) {
                DB::table('model_has_roles')->updateOrInsert(['role_id' => $roleId, 'model_type' => 'App\\Models\\User', 'model_id' => $userId]);
            } else {
                $report->warning('utilisateurs', "Groupe {$code} non confirmé pour {$sourceId}; poste administratif à reclasser.");
            }
        }
    }

    private function importSettingsOnce(FacilgSqlDump $dump, FacilgImportReport $report): void
    {
        if (DB::table('config_etablissement')->whereNotNull('nom')->where('nom', '!=', '')->exists()) {
            $report->count('parametres', 'ignorees');

            return;
        }
        foreach ($dump->rows($this->dumpPath, 'parametres') as $row) {
            DB::table('config_etablissement')->insert([
                'nom' => $this->text($row['Nom_Etab'] ?? null) ?: 'Établissement FACILG', 'adresse' => $this->text($row['Localite'] ?? null),
                'telephone' => $this->text($row['Tel_Etab'] ?? null), 'email' => filter_var($row['Email_Etab'] ?? null, FILTER_VALIDATE_EMAIL) ?: null,
                'devise' => $this->text($row['DevisePays'] ?? null) ?: 'XAF', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $report->count('parametres', 'importees');
            break;
        }
    }

    private function importEmployeeDiplomas(FacilgSqlDump $dump, FacilgImportReport $report, array $employee, int $employeeId, string $year): void
    {
        foreach ([['diplomes', 'ID_Dip', 'Nom_Dip', 'academique'], ['diplomespro', 'ID_DipPro', 'Nom_DipPro', 'professionnel']] as [$table, $idColumn, $nameColumn, $type]) {
            $wanted = $this->text($employee[$idColumn] ?? null);
            if (! $wanted || $wanted === '0000') {
                continue;
            }
            foreach ($dump->rows($this->dumpPath, $table) as $row) {
                if ($this->text($row[$idColumn] ?? null) !== $wanted || $this->text($row['AnneeScol'] ?? null) !== $year) {
                    continue;
                }
                $this->upsert('diplomes_personnel', "{$table}:".$this->key($wanted, $year, $employee['ID_Em']), [
                    'personnel_id' => $employeeId, 'nom' => $this->text($row[$nameColumn] ?? null) ?: $wanted, 'type' => $type,
                ], $report, $table);
                break;
            }
        }
    }

    private function importEmployeePremium(FacilgImportReport $report, array $employee, int $employeeId, string $year): void
    {
        $amount = (float) ($employee['PrimeFonction_Em'] ?? 0) + (float) ($employee['AutrePrime_Em'] ?? 0);
        if ($amount <= 0 || ! DB::table('types_primes')->exists()) {
            return;
        }
        $typeId = DB::table('types_primes')->where('code', 'facilg_import')->value('id')
            ?: DB::table('types_primes')->insertGetId(['code' => 'facilg_import', 'libelle' => 'Prime FACILG (import)', 'mode_calcul' => 'montant_fixe', 'valeur_defaut' => 0, 'actif' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->upsert('personnel_primes', 'prime:'.$this->key($employee['ID_Em'], $year), [
            'employe_id' => $employeeId, 'type_prime_id' => $typeId, 'valeur_override' => $amount,
            'date_attribution' => $this->yearStart($year), 'actif' => true, 'motif' => 'Prime FACILG importée — à reclasser', 'created_by' => $this->systemUserId,
        ], $report, 'primesemployes');
    }

    private function upsert(string $table, string $legacy, array $data, FacilgImportReport $report, string $sourceTable): int
    {
        $existing = DB::table($table)->where('facilg_legacy_id', $legacy)->first();
        $data['updated_at'] = $data['updated_at'] ?? now();
        if ($existing) {
            DB::table($table)->where('id', $existing->id)->update($data);
            $report->count($sourceTable, 'ignorees');

            return (int) $existing->id;
        }
        $data['facilg_legacy_id'] = $legacy;
        $data['created_at'] = $data['created_at'] ?? now();
        $report->count($sourceTable, 'importees');

        return (int) DB::table($table)->insertGetId($data);
    }

    private function ensureSystemUser(): int
    {
        return (int) (DB::table('users')->where('email', 'migration.facilg@ambassadors.local')->value('id')
            ?: DB::table('users')->insertGetId(['name' => 'Migration FACILG', 'email' => 'migration.facilg@ambassadors.local', 'password' => Hash::make(Str::random(48)), 'statut' => 'actif', 'must_change_password' => true, 'created_at' => now(), 'updated_at' => now()]));
    }

    private function classLevel(string $name): ?string
    {
        $normalized = Str::of($name)->ascii()->upper()->toString();
        foreach (config('facilg.niveaux_classes', []) as $level => $patterns) {
            foreach ($patterns as $pattern) {
                if (str_contains($normalized, Str::ascii(strtoupper($pattern)))) {
                    return $level;
                }
            }
        }

        return null;
    }

    private function remainingForRegistration(int $registrationId): float
    {
        $registration = DB::table('inscriptions')->find($registrationId);
        $config = DB::table('configurations_frais_classe')->where('classe_id', $registration->classe_id)->where('annee_scolaire_id', $registration->annee_scolaire_id)->first();
        if (! $config) {
            return PHP_FLOAT_MAX;
        }
        $paid = (float) DB::table('paiements')->where('inscription_id', $registrationId)->where('statut', 'valide')->sum('montant');

        return max(0, (float) $config->montant_total - $paid);
    }

    private function allocatePayment(int $paymentId, int $registrationId, float $amount): void
    {
        if (DB::table('paiement_tranche_allocations')->where('paiement_id', $paymentId)->exists()) {
            return;
        }
        $registration = DB::table('inscriptions')->find($registrationId);
        $configId = DB::table('configurations_frais_classe')->where('classe_id', $registration->classe_id)->where('annee_scolaire_id', $registration->annee_scolaire_id)->value('id');
        $remaining = $amount;
        foreach (DB::table('tranches_frais_classe')->where('configuration_frais_classe_id', $configId)->orderBy('ordre')->get() as $tranche) {
            $already = (float) DB::table('paiement_tranche_allocations')->where('tranche_frais_classe_id', $tranche->id)->sum('montant');
            $allocated = min($remaining, max(0, (float) $tranche->montant - $already));
            if ($allocated > 0) {
                DB::table('paiement_tranche_allocations')->insert(['paiement_id' => $paymentId, 'tranche_frais_classe_id' => $tranche->id, 'montant' => $allocated, 'created_at' => now(), 'updated_at' => now()]);
                $remaining -= $allocated;
            }
        }
    }

    private function registrationStatus(array $row): string
    {
        return $this->yes($row['Demission_El'] ?? null) || $this->yes($row['ExcluScol_El'] ?? null) ? 'annulee' : ($this->yes($row['Transfere_El'] ?? null) ? 'validee' : 'validee');
    }

    private function paymentMode(mixed $mode): string
    {
        $mode = Str::ascii(strtoupper($this->text($mode)));
        if (preg_match('/MOBILE|MOMO|ORANGE|MTN/', $mode)) {
            return 'mobile_money';
        }
        if (preg_match('/BANQ|VIRE|CHEQ/', $mode)) {
            return 'bancaire';
        }

        return 'especes';
    }

    private function sex(mixed $value): ?string
    {
        $value = strtoupper($this->text($value));

        return str_starts_with($value, 'M') ? 'M' : (str_starts_with($value, 'F') ? 'F' : null);
    }

    private function yes(mixed $value): bool
    {
        return in_array(strtoupper($this->text($value)), ['OUI', 'YES', '1'], true);
    }

    private function text(mixed $value): string
    {
        return trim((string) $value);
    }

    private function key(mixed ...$parts): string
    {
        return implode('|', array_map(fn ($part): string => $this->text($part), $parts));
    }

    private function uuid(string $value): string
    {
        $hash = md5($value);

        return substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-5'.substr($hash, 13, 3).'-a'.substr($hash, 17, 3).'-'.substr($hash, 20, 12);
    }

    private function date(mixed $value): ?string
    {
        $value = $this->text($value);
        if ($value === '' || str_starts_with($value, '0000-00-00')) {
            return null;
        }
        try {
            return CarbonImmutable::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function dateTime(mixed $date, mixed $time): string
    {
        return ($this->date($date) ?? now()->toDateString()).' '.($this->text($time) ?: '00:00:00');
    }

    private function yearStart(string $year): string
    {
        return substr($year, 0, 4).'-09-01';
    }

    private function yearEnd(string $year): string
    {
        return substr($year, -4).'-08-31';
    }
}
