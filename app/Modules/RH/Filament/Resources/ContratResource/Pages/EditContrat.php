<?php

namespace App\Modules\RH\Filament\Resources\ContratResource\Pages;

use App\Modules\RH\Filament\Resources\ContratResource;
use App\Modules\RH\Models\Contrat;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditContrat extends EditRecord
{
    protected static string $resource = ContratResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            $record->update($data);

            return $record;
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Erreur lors de la mise à jour du contrat')
                ->body($e->getMessage())
                ->danger()
                ->send();

            $this->halt();
        }
    }

    protected function afterSave(): void
    {
        self::saveFonction($this->record, $this->data);
    }

    public static function saveFonction(Contrat $record, array $data): void
    {
        $fonctionId = $data['fonction_id'] ?? null;
        $mode = $data['fonction_mode_remuneration'] ?? null;
        $montant = $data['fonction_montant'] ?? null;

        if (! $fonctionId || ! $mode || ! is_numeric($montant) || (float) $montant <= 0) {
            return;
        }

        DB::transaction(function () use ($record, $fonctionId, $mode, $montant): void {
            /** @var Contrat $contrat */
            $contrat = $record;
            $dateEffet = $contrat->date_debut ?? today();

            $contrat->fonctionsRemunerees()->create([
                'poste_administratif_id' => (int) $fonctionId,
                'mode_remuneration' => $mode,
                'montant' => (float) $montant,
                'date_effet' => $dateEffet,
                'created_by' => auth()->id(),
            ]);

            $contrat->employe?->historiquePostes()->create([
                'poste_administratif_id' => (int) $fonctionId,
                'date_debut' => $dateEffet,
                'motif' => 'Fonction ajoutée lors du renouvellement du contrat',
            ]);
        });
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
