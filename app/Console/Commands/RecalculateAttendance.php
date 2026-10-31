<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Assiduite\Contracts\AttendanceEngineInterface;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class RecalculateAttendance extends Command
{
    protected $signature = 'attendance:recalculate
        {date? : Date à recalculer au format AAAA-MM-JJ}
        {--type= : eleve ou personnel}
        {--id= : Identifiant de la personne}';

    protected $description = 'Recalcule l’assiduité journalière des élèves et du personnel';

    public function handle(AttendanceEngineInterface $moteur): int
    {
        try {
            $date = Carbon::parse((string) ($this->argument('date') ?: today()->toDateString()))->startOfDay();
        } catch (Throwable) {
            $this->error('La date doit respecter le format AAAA-MM-JJ.');

            return self::FAILURE;
        }

        $type = $this->option('type');
        $id = $this->option('id');

        if ($type !== null && ! in_array($type, ['eleve', 'personnel'], true)) {
            $this->error('Le type doit être « eleve » ou « personnel ».');

            return self::FAILURE;
        }

        if ($id !== null && $type === null) {
            $this->error('L’option --type est obligatoire lorsque --id est utilisé.');

            return self::FAILURE;
        }

        $traites = 0;
        $erreurs = 0;

        if ($id !== null) {
            $this->recalculer($moteur, (string) $type, (int) $id, $date, $traites, $erreurs);
        } else {
            if ($type === null || $type === 'eleve') {
                $this->elevesActifs()->orderBy('eleves.id')->chunkById(200, function ($eleves) use ($moteur, $date, &$traites, &$erreurs): void {
                    foreach ($eleves as $eleve) {
                        $this->recalculer($moteur, 'eleve', (int) $eleve->id, $date, $traites, $erreurs);
                    }
                }, 'eleves.id', 'id');
            }

            if ($type === null || $type === 'personnel') {
                DB::table('employes')->where('statut', 'actif')->orderBy('id')->chunkById(200, function ($employes) use ($moteur, $date, &$traites, &$erreurs): void {
                    foreach ($employes as $employe) {
                        $this->recalculer($moteur, 'personnel', (int) $employe->id, $date, $traites, $erreurs);
                    }
                });
            }
        }

        $this->info("Recalcul terminé : {$traites} personne(s) traitée(s), {$erreurs} erreur(s).");

        return $erreurs === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function elevesActifs(): Builder
    {
        return DB::table('eleves')
            ->join('inscriptions', 'inscriptions.eleve_id', '=', 'eleves.id')
            ->whereNull('eleves.deleted_at')
            ->whereIn('inscriptions.statut', ['active', 'validee'])
            ->select('eleves.id')
            ->distinct();
    }

    private function recalculer(AttendanceEngineInterface $moteur, string $type, int $id, Carbon $date, int &$traites, int &$erreurs): void
    {
        try {
            $moteur->recalculer($type, $id, $date);
            $traites++;
        } catch (Throwable $exception) {
            report($exception);
            $erreurs++;
            $this->warn("Échec pour {$type} #{$id} : {$exception->getMessage()}");
        }
    }
}
