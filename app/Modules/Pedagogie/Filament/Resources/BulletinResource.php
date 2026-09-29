<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Resources;

use App\Modules\Pedagogie\Contracts\BulletinServiceInterface;
use App\Modules\Pedagogie\Filament\Resources\BulletinResource\Pages;
use App\Modules\Pedagogie\Models\Bulletin;
use App\Modules\Scolarite\Contracts\ClasseServiceInterface;
use App\Modules\Scolarite\Contracts\EleveServiceInterface;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class BulletinResource extends Resource
{
    protected static ?string $model = Bulletin::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static string|UnitEnum|null $navigationGroup = 'Pédagogie';

    protected static ?string $navigationLabel = 'Bulletins';

    public static function form(Schema $schema): Schema
    {
        // Les bulletins sont uniquement générés via les actions dédiées
        // ("Générer pour un élève" / "Générer pour la classe") — pas de
        // création manuelle, pour garantir que les chiffres restent
        // recalculés à partir des notes réelles.
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('eleve_id')
                    ->label('Élève')
                    ->formatStateUsing(function ($state) {
                        $eleve = app(EleveServiceInterface::class)->getEleve($state);

                        return trim(($eleve['nom'] ?? '').' '.($eleve['prenom'] ?? '')) ?: "#{$state}";
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('classe_id')->label('Classe #'),
                Tables\Columns\TextColumn::make('annee_scolaire_id')->label('Année scolaire #'),
                Tables\Columns\TextColumn::make('periode_id')->label('Période #')->placeholder('Année complète'),

                Tables\Columns\TextColumn::make('moyenne_generale')
                    ->label('Moyenne générale')
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format((float) $state, 2).' / 20' : '—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('rang')
                    ->label('Rang')
                    ->formatStateUsing(fn ($state, Bulletin $record) => $state ? "{$state} / {$record->effectif_classe}" : '—'),

                Tables\Columns\TextColumn::make('mention')->label('Mention')->badge(),
                Tables\Columns\TextColumn::make('statut')->label('Statut')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'publie' => 'success', 'valide' => 'info', 'soumis' => 'warning', 'archive' => 'gray', default => 'primary'
                    }),

                Tables\Columns\IconColumn::make('document_id')->label('Document généré')->boolean()->getStateUsing(fn (Bulletin $record) => $record->document_id !== null),

                Tables\Columns\TextColumn::make('genere_le')->label('Généré le')->dateTime(),
            ])
            ->actions([
                Action::make('soumettre')->label('Soumettre')->icon('heroicon-o-paper-airplane')->visible(fn (Bulletin $record): bool => $record->statut === 'calcule')->requiresConfirmation()->action(fn (Bulletin $record) => self::transition($record, 'soumis')),
                Action::make('valider')->label('Valider')->icon('heroicon-o-check-circle')->color('success')->visible(fn (Bulletin $record): bool => $record->statut === 'soumis')->requiresConfirmation()->action(fn (Bulletin $record) => self::transition($record, 'valide')),
                Action::make('publier')->label('Publier')->icon('heroicon-o-eye')->color('success')->visible(fn (Bulletin $record): bool => $record->statut === 'valide')->requiresConfirmation()->action(fn (Bulletin $record) => self::transition($record, 'publie')),
                Action::make('archiver')->label('Archiver')->icon('heroicon-o-archive-box')->color('gray')->visible(fn (Bulletin $record): bool => $record->statut === 'publie')->requiresConfirmation()->action(fn (Bulletin $record) => self::transition($record, 'archive')),
            ])
            ->headerActions([
                Action::make('genererPourEleve')
                    ->label("Générer le bulletin d'un élève")
                    ->icon('heroicon-o-document-plus')
                    ->color('primary')
                    ->form(self::formulaireGeneration(avecEleve: true))
                    ->action(function (array $data): void {
                        try {
                            $bulletin = app(BulletinServiceInterface::class)->genererPourEleve(
                                (int) $data['eleve_id'],
                                (int) $data['classe_id'],
                                (int) $data['annee_scolaire_id'],
                                $data['periode_id'] ? (int) $data['periode_id'] : null,
                            );

                            Notification::make()
                                ->title('Bulletin généré')
                                ->body("Moyenne générale : {$bulletin->moyenne_generale}/20 — Rang {$bulletin->rang}/{$bulletin->effectif_classe} — {$bulletin->mention}.")
                                ->success()
                                ->duration(6000)
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Échec de la génération')
                                ->body('Erreur : '.$e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),

                Action::make('genererPourClasse')
                    ->label('Générer les bulletins de la classe')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->form(self::formulaireGeneration(avecEleve: false))
                    ->action(function (array $data): void {
                        try {
                            $bulletins = app(BulletinServiceInterface::class)->genererPourClasse(
                                (int) $data['classe_id'],
                                (int) $data['annee_scolaire_id'],
                                $data['periode_id'] ? (int) $data['periode_id'] : null,
                            );

                            Notification::make()
                                ->title('Bulletins générés')
                                ->body("{$bulletins->count()} bulletin(s) généré(s) pour la classe.")
                                ->success()
                                ->duration(6000)
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Échec de la génération')
                                ->body('Erreur : '.$e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),
            ]);
    }

    private static function transition(Bulletin $bulletin, string $statut): void
    {
        try {
            app(BulletinServiceInterface::class)->changerStatut($bulletin->id, $statut);
            Notification::make()->title('Bulletin mis à jour')->body('Nouveau statut : '.$statut)->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Transition impossible')->body($e->getMessage())->danger()->send();
        }
    }

    private static function formulaireGeneration(bool $avecEleve): array
    {
        $champs = [
            Select::make('annee_scolaire_id')
                ->label('Année scolaire')
                ->options(fn () => collect(app(AnneeScolaireServiceContract::class)->getToutesLesAnnees())
                    ->mapWithKeys(fn ($a) => [data_get($a, 'id') => data_get($a, 'libelle', data_get($a, 'nom', '#'.data_get($a, 'id')))]))
                ->live()
                ->default(fn () => app(AnneeScolaireServiceContract::class)->getAnneeCouranteId())
                ->required(),

            Select::make('classe_id')
                ->label('Classe')
                ->options(function (Get $get) {
                    $anneeScolaireId = (int) ($get('annee_scolaire_id') ?: app(AnneeScolaireServiceContract::class)->getAnneeCouranteId());

                    return collect(app(ClasseServiceInterface::class)->getToutesLesClasses($anneeScolaireId))->pluck('nom', 'id');
                })
                ->searchable()
                ->live()
                ->required(),

            Select::make('periode_id')
                ->label('Période (optionnel — laisser vide pour l’année complète)')
                ->options(function (Get $get) {
                    $anneeScolaireId = (int) ($get('annee_scolaire_id') ?: app(AnneeScolaireServiceContract::class)->getAnneeCouranteId());

                    return collect(app(AnneeScolaireServiceContract::class)->getPeriodes($anneeScolaireId))
                        ->mapWithKeys(fn ($p) => [data_get($p, 'id') => data_get($p, 'nom', data_get($p, 'libelle', '#'.data_get($p, 'id')))]);
                }),
        ];

        if ($avecEleve) {
            $champs[] = Select::make('eleve_id')
                ->label('Élève')
                ->options(function (Get $get) {
                    $classeId = (int) $get('classe_id');
                    if (! $classeId) {
                        return [];
                    }

                    return collect(app(EleveServiceInterface::class)->getElevesParClasse($classeId))
                        ->mapWithKeys(fn ($e) => [$e['id'] => "{$e['nom']} {$e['prenom']}"]);
                })
                ->searchable()
                ->required();
        }

        return $champs;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBulletins::route('/'),
        ];
    }
}
