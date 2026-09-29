<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources;

use App\Models\User;
use App\Modules\Scolarite\Contracts\CourrierDestinataireServiceContract;
use App\Modules\Socle\Contracts\CourrierServiceContract;
use App\Modules\Socle\Models\Courrier;
use App\Modules\Socle\Models\CourrierModele;
use App\Modules\Socle\Models\Groupe;
use App\Modules\Socle\Models\Niveau;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class CourrierResource extends Resource
{
    protected static ?string $model = Courrier::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static string|\UnitEnum|null $navigationGroup = 'Module Complémentaire';

    protected static ?string $modelLabel = 'Courrier';

    protected static ?string $pluralModelLabel = 'Courriers';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->options([
                        'entrant' => 'Entrant',
                        'sortant' => 'Sortant',
                    ])
                    ->required()
                    ->live(),
                TextInput::make('numero')
                    ->disabled()
                    ->placeholder('Généré automatiquement à la création'),
                TextInput::make('objet')
                    ->required(),
                Select::make('modele_id')
                    ->label('Modèle réutilisable (facultatif)')
                    ->options(fn (): array => CourrierModele::where('actif', true)->pluck('nom', 'id')->all())
                    ->searchable()
                    ->live()
                    ->visible(fn ($get): bool => $get('type') === 'sortant')
                    ->afterStateUpdated(function ($state, callable $set): void {
                        if (! $state) { return; }
                        $contenu = app(CourrierServiceContract::class)->appliquerModele((int) $state);
                        $set('objet', $contenu['objet']);
                        $set('contenu', $contenu['contenu']);
                    }),
                TextInput::make('expediteur')
                    ->required(),
                TextInput::make('destinataire')
                    ->required(fn ($get): bool => $get('type') === 'entrant')
                    ->visible(fn ($get): bool => $get('type') === 'entrant'),
                Textarea::make('contenu')
                    ->rows(10)
                    ->required(fn ($get): bool => $get('type') === 'sortant')
                    ->visible(fn ($get): bool => $get('type') === 'sortant')
                    ->columnSpanFull(),
                Select::make('cible_type')
                    ->label('Catégorie de destinataires')
                    ->options([
                        'utilisateurs' => 'Sélection manuelle du personnel',
                        'groupe' => 'Groupe d’accès permanent',
                        'tous_enseignants' => 'Tous les enseignants',
                        'tout_personnel' => 'Tout le personnel',
                        'parents_selectionnes' => 'Parents sélectionnés',
                        'tous_parents' => 'Tous les parents',
                        'niveau' => 'Parents d’un ou plusieurs niveaux',
                        'classe' => 'Parents d’une classe',
                        'classes' => 'Parents de plusieurs classes',
                        'sous_niveaux' => 'Parents de sous-niveaux/classes',
                    ])
                    ->live()->required(fn ($get): bool => $get('type') === 'sortant')
                    ->visible(fn ($get): bool => $get('type') === 'sortant'),
                Select::make('user_ids')->label('Membres du personnel')->multiple()->searchable()
                    ->options(fn (): array => User::where('statut', 'actif')->orderBy('name')->pluck('name', 'id')->all())
                    ->visible(fn ($get): bool => $get('type') === 'sortant' && $get('cible_type') === 'utilisateurs'),
                Select::make('groupe_id')->label('Groupe permanent')->searchable()
                    ->options(fn (): array => Groupe::orderBy('nom')->pluck('nom', 'id')->all())
                    ->visible(fn ($get): bool => $get('type') === 'sortant' && $get('cible_type') === 'groupe'),
                Select::make('parent_ids')->label('Parents')->multiple()->searchable()
                    ->options(fn (): array => app(CourrierDestinataireServiceContract::class)->optionsParents())
                    ->visible(fn ($get): bool => $get('type') === 'sortant' && $get('cible_type') === 'parents_selectionnes'),
                Select::make('niveau_ids')->label('Niveaux')->multiple()->searchable()
                    ->options(fn (): array => Niveau::orderBy('ordre')->pluck('nom', 'id')->all())
                    ->visible(fn ($get): bool => $get('type') === 'sortant' && $get('cible_type') === 'niveau'),
                Select::make('classe_ids')->label('Classes / sous-niveaux')->multiple()->searchable()
                    ->options(fn (): array => app(CourrierDestinataireServiceContract::class)->optionsClasses())
                    ->visible(fn ($get): bool => $get('type') === 'sortant' && in_array($get('cible_type'), ['classe', 'classes', 'sous_niveaux'], true)),
                Select::make('canal')->options(['email' => 'E-mail', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp', 'in_app' => 'Dans l’application'])
                    ->default('email')->required(fn ($get): bool => $get('type') === 'sortant')
                    ->visible(fn ($get): bool => $get('type') === 'sortant'),
                DatePicker::make('date_limite_reponse'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('numero')->searchable()->sortable(),
                TextColumn::make('type')->badge()->color(fn (string $state): string => $state === 'entrant' ? 'info' : 'success'),
                TextColumn::make('objet')->searchable(),
                TextColumn::make('expediteur')->searchable(),
                TextColumn::make('destinataire')->searchable(),
                TextColumn::make('destinataires_count')->counts('destinataires')->label('Contacts'),
                TextColumn::make('statut')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'recu' => 'warning',
                        'affecte' => 'info',
                        'en_traitement' => 'primary',
                        'repondu' => 'success',
                        'archive' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('date_limite_reponse')->date(),
            ])
            ->recordActions([
                Action::make('envoyer')
                    ->label('Envoyer')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Courrier $record): bool => $record->type === 'sortant' && $record->envoye_le === null)
                    ->action(function (Courrier $record): void {
                        app(CourrierServiceContract::class)->envoyerCourrier($record->id);
                        Notification::make()->title('Courrier placé dans la file d’envoi.')->success()->send();
                    }),
                Action::make('affecter')
                    ->label('Affecter')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('info')
                    ->schema([
                        TextInput::make('service_affecte_id')
                            ->label('Service')
                            ->numeric()
                            ->required(),
                    ])
                    ->visible(fn (Courrier $record): bool => $record->estAuStatut('recu'))
                    ->action(fn (Courrier $record, array $data) => app(CourrierServiceContract::class)
                        ->affecterAService($record->id, (int) $data['service_affecte_id'])),
                Action::make('marquer_en_traitement')
                    ->label('Marquer en traitement')
                    ->icon('heroicon-o-cog')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->visible(fn (Courrier $record): bool => $record->estAuStatut('affecte'))
                    ->action(fn (Courrier $record) => $record->changerStatut('en_traitement', Auth::user())),
                Action::make('repondre')
                    ->label('Répondre')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Courrier $record): bool => $record->estAuStatut('en_traitement'))
                    ->action(fn (Courrier $record) => $record->changerStatut('repondu', Auth::user())),
                Action::make('archiver')
                    ->label('Archiver')
                    ->icon('heroicon-o-archive-box')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (Courrier $record): bool => $record->estAuStatut('repondu'))
                    ->action(fn (Courrier $record) => $record->changerStatut('archive', Auth::user())),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => CourrierResource\Pages\ListCourriers::route('/'),
            'create' => CourrierResource\Pages\CreateCourrier::route('/create'),
            'edit' => CourrierResource\Pages\EditCourrier::route('/{record}/edit'),
        ];
    }
}
