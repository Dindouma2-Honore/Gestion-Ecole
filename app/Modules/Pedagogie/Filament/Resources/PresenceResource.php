<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Resources;

use App\Modules\Pedagogie\Contracts\PresenceServiceInterface;
use App\Modules\Pedagogie\Filament\Resources\PresenceResource\Pages;
use App\Modules\Pedagogie\Models\Presence;
use App\Modules\Scolarite\Contracts\EleveServiceInterface;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class PresenceResource extends Resource
{
    protected static ?string $model = Presence::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'Pédagogie';

    protected static ?string $navigationLabel = 'Présences (appel)';

    protected static ?string $modelLabel = 'présence';

    protected static ?string $pluralModelLabel = 'présences';

    // Pas de création manuelle ici : l'appel se fait en un clic groupé via
    // PresenceServiceInterface::faireAppel(), pas ligne par ligne. Cette
    // Resource sert à consulter/justifier, pas à saisir l'appel lui-même.

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('seance.emploiDuTemps.matiere.nom')->label('Matière'),
                Tables\Columns\TextColumn::make('seance.date_seance')->label('Date')->date('d/m/Y'),
                Tables\Columns\TextColumn::make('eleve_id')->label('Élève')
                    ->formatStateUsing(function ($state) {
                        $eleve = app(EleveServiceInterface::class)->getEleve($state);

                        return "{$eleve['nom']} {$eleve['prenom']}";
                    }),
                Tables\Columns\TextColumn::make('statut')->label('Statut')->badge(),
                Tables\Columns\TextColumn::make('heure_arrivee')->label('Heure d\'arrivée'),
                Tables\Columns\IconColumn::make('justifie')->label('Justifiée')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('statut')->options([
                    'present' => 'Présent',
                    'absent' => 'Absent',
                    'retard' => 'Retard',
                    'depart_anticipe' => 'Départ anticipé',
                ]),
                Tables\Filters\TernaryFilter::make('justifie')->label('Justifiée'),
            ])
            ->actions([
                Action::make('justifier')
                    ->label('Justifier')
                    ->icon('heroicon-o-document-check')
                    ->visible(fn (Presence $record) => $record->statut === 'absent' && ! $record->justifie)
                    ->form([
                        Forms\Components\FileUpload::make('justificatif')
                            ->label('Justificatif')
                            ->required(),
                    ])
                    ->action(function (Presence $record, array $data) {
                        // Le FileUpload de Filament renvoie un chemin de stockage ;
                        // DocumentServiceContract (Socle) attend un UploadedFile —
                        // conversion à finaliser une fois DocumentServiceContract
                        // confirmé côté Socle (TODO, cf. PresenceService).
                        app(PresenceServiceInterface::class);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPresences::route('/'),
        ];
    }
}
