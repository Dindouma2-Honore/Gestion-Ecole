<?php

namespace App\Modules\RH\Filament\Resources\BulletinPaieResource\Pages;

use App\Filament\Support\AmbassadorsDesign;
use App\Modules\Finances\Contracts\CaisseServiceContract;
use App\Modules\RH\Contracts\PaieServiceContract;
use App\Modules\RH\Exceptions\BulletinDejaExistantException;
use App\Modules\RH\Filament\Resources\BulletinPaieResource;
use App\Modules\RH\Filament\Resources\BulletinPaieResource\Pages\Widgets\MassePaieWidget;
use App\Modules\RH\Models\BulletinPaie;
use App\Modules\RH\Models\Employe;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ListBulletinPaies extends ListRecords
{
    protected static string $resource = BulletinPaieResource::class;

    protected function getHeaderWidgets(): array
    {
        return [MassePaieWidget::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('valider_tous')
                ->label('Valider tous les bulletins')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (): bool => Auth::user()?->hasRole('Fondateur') ?? false)
                ->requiresConfirmation()->modalWidth('none')->extraModalWindowAttributes(AmbassadorsDesign::goldenModalAttributes('dialog'))
                ->modalHeading('Valider tous les bulletins calculés ?')
                ->modalDescription('Le solde disponible en caisse sera contrôlé avant validation. Si le solde est insuffisant, aucun bulletin ne sera validé.')
                ->modalSubmitActionLabel('Oui, valider tout')
                ->action(function (): void {
                    $bulletins = BulletinPaie::query()->where('statut', 'calcule')->get();
                    $montantGlobal = round((float) $bulletins->sum('net_a_payer'), 2);
                    if ($bulletins->isEmpty()) {
                        Notification::make()->warning()->title('Aucun bulletin calculé à valider')->send();

                        return;
                    }

                    try {
                        $soldeDisponible = app(CaisseServiceContract::class)->getSoldeTheoriqueActuel();
                    } catch (\Throwable $exception) {
                        report($exception);
                        Notification::make()
                            ->danger()
                            ->title('Validation refusée')
                            ->body('Impossible de consulter le solde disponible en caisse. Aucun bulletin n’a été validé.')
                            ->send();

                        return;
                    }

                    if ($soldeDisponible < $montantGlobal) {
                        Notification::make()
                            ->danger()
                            ->title('Validation refusée : solde insuffisant')
                            ->body(sprintf(
                                'Paie globale : %s FCFA. Solde disponible : %s FCFA. Il manque %s FCFA.',
                                number_format($montantGlobal, 0, ',', ' '),
                                number_format($soldeDisponible, 0, ',', ' '),
                                number_format($montantGlobal - $soldeDisponible, 0, ',', ' '),
                            ))
                            ->persistent()
                            ->send();

                        return;
                    }

                    $service = app(PaieServiceContract::class);
                    DB::transaction(function () use ($bulletins, $service): void {
                        foreach ($bulletins as $bulletin) {
                            $service->validerBulletin($bulletin->id, (int) Auth::id());
                        }
                    });
                    Notification::make()
                        ->success()
                        ->title('Bulletins validés')
                        ->body(sprintf('%d bulletin(s) validé(s) pour %s FCFA.', $bulletins->count(), number_format($montantGlobal, 0, ',', ' ')))
                        ->send();
                }),
            Actions\Action::make('supprimer_tous')
                ->label('Supprimer tous les bulletins')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->visible(fn (): bool => Auth::user()?->hasRole('Fondateur') ?? false)
                ->requiresConfirmation()->modalWidth('none')->extraModalWindowAttributes(AmbassadorsDesign::goldenModalAttributes('dialog'))
                ->modalHeading('Supprimer définitivement tous les bulletins ?')
                ->modalDescription('Cette opération supprime les bulletins calculés, validés et payés ainsi que leurs lignes. Les mouvements de caisse restent conservés pour l’audit. Les déductions d’avances seront remises à zéro.')
                ->modalSubmitActionLabel('Oui, tout supprimer')
                ->action(function (): void {
                    $nombre = BulletinPaie::query()->count();
                    DB::transaction(function (): void {
                        DB::table('bulletins_paie')->delete();
                        DB::table('avances_salaires')->update(['montant_deja_deduit' => 0]);
                    });
                    Notification::make()->success()->title("{$nombre} bulletin(s) supprimé(s)")->send();
                }),
            Actions\Action::make('imprimer_tous')
                ->label('Imprimer tous les bulletins')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->schema([
                    Select::make('mois')->options(array_combine(range(1, 12), range(1, 12)))->default((int) date('n'))->required(),
                    TextInput::make('annee')->numeric()->default((int) date('Y'))->required(),
                ])
                ->action(fn (array $data) => redirect()->route('rh.bulletins-paie.imprimer-tous', [
                    'mois' => (int) $data['mois'],
                    'annee' => (int) $data['annee'],
                ])),
            AmbassadorsDesign::goldenFormModal(Actions\Action::make('calculer_mois'))
                ->label('Calculer la paie du mois')
                ->icon('heroicon-o-calculator')
                ->schema([
                    DatePicker::make('periode_debut')->label('Début de période')->default(now()->startOfMonth())->required(),
                    DatePicker::make('periode_fin')->label('Fin de période')->default(now()->endOfMonth())->afterOrEqual('periode_debut')->required(),
                ])
                ->action(function (array $data): void {
                    $service = app(PaieServiceContract::class);
                    $periodeDebut = Carbon::parse($data['periode_debut']);
                    $periodeFin = Carbon::parse($data['periode_fin']);
                    $mois = $periodeDebut->month;
                    $annee = $periodeDebut->year;
                    $calcules = 0;
                    Employe::query()->where('statut', 'actif')->whereHas('contrats', fn ($query) => $query->where('statut', 'actif'))->each(function (Employe $employe) use ($service, $mois, $annee, $periodeDebut, $periodeFin, &$calcules): void {
                        try {
                            $service->calculerBulletin($employe->id, $mois, $annee, $periodeDebut, $periodeFin);
                            $calcules++;
                        } catch (BulletinDejaExistantException) {
                            $bulletin = BulletinPaie::query()
                                ->where('employe_id', $employe->id)
                                ->where('mois', $mois)
                                ->where('annee', $annee)
                                ->first();

                            if (
                                $bulletin?->statut === 'calcule'
                                && (float) $bulletin->salaire_base === 0.0
                                && (float) $bulletin->total_primes === 0.0
                                && (float) $bulletin->net_a_payer === 0.0
                            ) {
                                $bulletin->delete();
                                $service->calculerBulletin($employe->id, $mois, $annee, $periodeDebut, $periodeFin);
                                $calcules++;
                            }
                        }
                    });
                    Notification::make()->success()->title("{$calcules} bulletin(s) calculé(s)")->send();
                }),
            AmbassadorsDesign::goldenFormModal(Actions\Action::make('calculer_bulletin'))
                ->label('Calculer un bulletin')
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->schema([
                    Select::make('employe_id')
                        ->label('Employé')
                        ->options(fn (): array => Employe::query()
                            ->where('statut', 'actif')
                            ->whereHas('contrats', fn ($q) => $q->where('statut', 'actif'))
                            ->orderBy('nom')
                            ->get()
                            ->mapWithKeys(fn (Employe $e) => [$e->id => $e->nom_complet])
                            ->all())
                        ->searchable()
                        ->required(),
                    DatePicker::make('periode_debut')->label('Début de période')->default(now()->startOfMonth())->required(),
                    DatePicker::make('periode_fin')->label('Fin de période')->default(now()->endOfMonth())->afterOrEqual('periode_debut')->required(),
                ])
                ->action(function (array $data): void {
                    $service = app(PaieServiceContract::class);
                    $debut = Carbon::parse($data['periode_debut']);
                    $fin = Carbon::parse($data['periode_fin']);

                    try {
                        $bulletin = $service->calculerBulletin(
                            (int) $data['employe_id'],
                            $debut->month,
                            $debut->year,
                            $debut,
                            $fin
                        );

                        Notification::make()
                            ->title('Bulletin calculé avec succès')
                            ->body(sprintf('Net à payer : %s FCFA', number_format((float) $bulletin->net_a_payer, 0, ',', ' ')))
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Erreur lors du calcul du bulletin')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
