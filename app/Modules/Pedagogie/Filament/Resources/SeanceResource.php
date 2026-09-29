<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Resources;

use App\Models\User;
use App\Modules\Pedagogie\Contracts\SeanceServiceInterface;
use App\Modules\Pedagogie\Filament\Resources\SeanceResource\Pages;
use App\Modules\Pedagogie\Models\Seance;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class SeanceResource extends Resource
{
    protected static ?string $model = Seance::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|UnitEnum|null $navigationGroup = 'Pédagogie';

    protected static ?string $navigationLabel = 'Séances';

    protected static ?string $modelLabel = 'séance';

    protected static ?string $pluralModelLabel = 'séances';

    // Pas de formulaire de création manuelle : les séances sont générées par
    // SeanceServiceInterface::genererSeancesPourSemaine() (Job planifié).
    // Toutes les actions ci-dessous passent par le Service, jamais par une
    // écriture directe sur le Model, pour respecter les transitions de
    // statut autorisées (Seance::TRANSITIONS_AUTORISEES).

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('emploiDuTemps.matiere.nom')->label('Matière')->searchable(),
                Tables\Columns\TextColumn::make('date_seance')->label('Date')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('enseignant_id')
                    ->label('Enseignant')
                    ->formatStateUsing(fn ($state) => User::find($state)?->name ?? "#{$state}"),
                Tables\Columns\TextColumn::make('statut')->label('Statut')->badge(),
                Tables\Columns\IconColumn::make('progression_renseignee')
                    ->label('Cahier de texte')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('statut')->options([
                    'programmee' => 'Programmée',
                    'commencee' => 'Commencée',
                    'dispensee' => 'Dispensée',
                    'annulee' => 'Annulée',
                    'reportee' => 'Reportée',
                    'non_dispensee' => 'Non dispensée',
                ]),
            ])
            ->actions([
                Action::make('commencer')
                    ->label('Commencer')
                    ->icon('heroicon-o-play')
                    ->visible(fn (Seance $record) => $record->statut === 'programmee')
                    ->action(function (Seance $record) {
                        app(SeanceServiceInterface::class)->marquerCommencee($record->id);
                    }),

                Action::make('annuler')
                    ->label('Annuler')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Seance $record) => in_array($record->statut, ['programmee', 'commencee'], true))
                    ->form([
                        Forms\Components\Textarea::make('motif')->label('Motif')->required(),
                    ])
                    ->action(function (Seance $record, array $data) {
                        app(SeanceServiceInterface::class)->annuler($record->id, $data['motif']);
                    }),

                Action::make('reporter')
                    ->label('Reporter')
                    ->icon('heroicon-o-calendar')
                    ->visible(fn (Seance $record) => in_array($record->statut, ['programmee', 'commencee'], true))
                    ->form([
                        Forms\Components\DatePicker::make('nouvelle_date')->label('Nouvelle date')->required(),
                    ])
                    ->action(function (Seance $record, array $data) {
                        app(SeanceServiceInterface::class)->reporter(
                            $record->id,
                            Carbon::parse($data['nouvelle_date'])
                        );

                        Notification::make()
                            ->title('Séance reportée, une nouvelle séance a été programmée.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSeances::route('/'),
        ];
    }
}
