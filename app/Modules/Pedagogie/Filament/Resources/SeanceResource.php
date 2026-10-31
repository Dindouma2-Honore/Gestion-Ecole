<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Resources;

use App\Models\User;
use App\Modules\Pedagogie\Contracts\PresenceServiceInterface;
use App\Modules\Pedagogie\Contracts\SeanceServiceInterface;
use App\Modules\Pedagogie\Filament\Resources\SeanceResource\Pages;
use App\Modules\Pedagogie\Models\DisciplineEleve;
use App\Modules\Pedagogie\Models\Seance;
use App\Modules\Pedagogie\Services\ProgrammeAccessService;
use App\Modules\Scolarite\Contracts\EleveServiceInterface;
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
                Action::make('faireAppel')
                    ->label('Faire l’appel')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('success')
                    ->visible(fn (Seance $record) => in_array($record->statut, ['programmee', 'commencee', 'dispensee'], true) && ! app(PresenceServiceInterface::class)->estAppelVerrouille($record->id))
                    ->form(function (Seance $record) {
                        $classeId = $record->emploiDuTemps?->classe_id;
                        $eleves = $classeId ? app(EleveServiceInterface::class)->getElevesParClasse((int) $classeId) : [];

                        return [
                            Forms\Components\CheckboxList::make('absents')
                                ->label('Élèves absents (les non-cochés sont réputés présents)')
                                ->options(collect($eleves)->mapWithKeys(fn ($e) => [$e['id'] => trim(($e['nom'] ?? '').' '.($e['prenom'] ?? ''))]))
                                ->columns(2),
                            Forms\Components\Repeater::make('retards')
                                ->label('Élèves en retard')
                                ->schema([
                                    Forms\Components\Select::make('eleve_id')
                                        ->label('Élève')
                                        ->options(collect($eleves)->mapWithKeys(fn ($e) => [$e['id'] => trim(($e['nom'] ?? '').' '.($e['prenom'] ?? ''))]))
                                        ->required(),
                                    Forms\Components\TimePicker::make('heure_arrivee')
                                        ->label('Heure d’arrivée')
                                        ->default(now()->format('H:i')),
                                ])
                                ->columns(2)
                                ->defaultItems(0),
                        ];
                    })
                    ->action(function (Seance $record, array $data): void {
                        $saisies = [];
                        foreach ($data['absents'] ?? [] as $eleveId) {
                            $saisies[(int) $eleveId] = 'absent';
                        }
                        foreach ($data['retards'] ?? [] as $retard) {
                            if (! empty($retard['eleve_id'])) {
                                $saisies[(int) $retard['eleve_id']] = [
                                    'statut' => 'retard',
                                    'heure_arrivee' => $retard['heure_arrivee'] ?? null,
                                ];
                            }
                        }

                        app(PresenceServiceInterface::class)->faireAppel($record->id, $saisies, true);

                        Notification::make()
                            ->title('Appel enregistré avec succès')
                            ->success()
                            ->send();
                    }),
                Action::make('progression')->label('Renseigner la progression')->icon('heroicon-o-pencil-square')
                    ->visible(fn (Seance $record) => $record->statut === 'dispensee' && (int) $record->enseignant_id === app(ProgrammeAccessService::class)->enseignantId())
                    ->url(fn (Seance $record) => ProgressionResource::getUrl('index', ['seance' => $record->id])),
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

                Action::make('renseignerProgression')
                    ->label('Renseigner')
                    ->icon('heroicon-o-book-open')
                    ->color('success')
                    ->visible(fn (Seance $record) => in_array($record->statut, ['dispensee', 'commencee'], true) && ! $record->progression_renseignee)
                    ->url(fn (Seance $record) => ProgressionResource::getUrl('index', ['seance' => $record->id])),

                Action::make('signalerIncident')
                    ->label('Signaler un incident')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('danger')
                    ->form([
                        Forms\Components\Select::make('eleve_id')->label('Élève')->searchable()->required()
                            ->options(fn (Seance $record) => collect(app(EleveServiceInterface::class)->getElevesParClasse((int) $record->emploiDuTemps->classe_id))
                                ->mapWithKeys(fn ($e) => [$e['id'] => trim(($e['nom'] ?? '').' '.($e['prenom'] ?? ''))])),
                        Forms\Components\TextInput::make('type_incident')->label('Type d’incident')->required(),
                        Forms\Components\Select::make('gravite')->label('Gravité')->options(['mineure' => 'Mineure', 'moyenne' => 'Moyenne', 'grave' => 'Grave', 'critique' => 'Critique'])->required(),
                        Forms\Components\Textarea::make('description')->label('Description')->required(),
                    ])
                    ->action(function (Seance $record, array $data): void {
                        DisciplineEleve::create($data + [
                            'classe_id' => $record->emploiDuTemps->classe_id,
                            'annee_scolaire_id' => $record->emploiDuTemps->annee_scolaire_id,
                            'date_incident' => $record->date_seance,
                            'confidentiel' => true,
                            'enregistre_par' => auth()->id(),
                        ]);
                        Notification::make()->title('Incident enregistré')->success()->send();
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
