<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Filament\Resources;

use App\Filament\Support\RecordModal;
use App\Models\User;
use App\Modules\VieScolaire\Models\Visiteur;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class VisiteurResource extends Resource
{
    protected static ?string $model = Visiteur::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static string|UnitEnum|null $navigationGroup = 'Vie scolaire';

    protected static ?string $navigationLabel = 'Visiteurs';

    protected static ?string $modelLabel = 'visiteur';

    protected static bool $shouldRegisterNavigation = false;

    /**
     * Interface d'enregistrement rapide (type kiosque) — la sélection de
     * personne_visitee (User/Eleve) reste en champs simples en attendant
     * un composant polymorphique dédié.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nom')->label('Nom')->required()->maxLength(255),
                TextInput::make('prenom')->label('Prénom')->maxLength(255),
                TextInput::make('telephone')->label('Téléphone')->tel()->maxLength(30),
                TextInput::make('motif')->label('Motif de la visite')->required()->maxLength(255),

                Select::make('personne_visitee_id')->label('Personne visitée')->searchable()->preload()->options(fn () => DB::table('users')->orderBy('name')->pluck('name', 'id')->all()),
                Hidden::make('personne_visitee_type')->default(User::class),
                TextInput::make('service_concerne')->label('Service concerné'),
                TextInput::make('type_piece')->label('Type de pièce'),
                TextInput::make('numero_piece')->label('Numéro de pièce'),

                DateTimePicker::make('heure_entree')
                    ->label('Heure d\'entrée')
                    ->default(now())
                    ->required(),

                TextInput::make('badge_numero')
                    ->label('Numéro de badge')
                    ->maxLength(20),

                Toggle::make('autorisation_prealable')
                    ->label('Autorisation préalable')
                    ->default(false),
                Textarea::make('observations')->label('Observations')->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return RecordModal::configure($table
            ->columns([
                Tables\Columns\TextColumn::make('nom')->label('Nom')->searchable(),
                Tables\Columns\TextColumn::make('motif')->label('Motif'),
                Tables\Columns\TextColumn::make('heure_entree')->label('Entrée')->dateTime(),
                Tables\Columns\TextColumn::make('heure_sortie')->label('Sortie')->dateTime()->placeholder('Présent'),
                Tables\Columns\IconColumn::make('incident_signale')->label('Incident')->boolean(),
            ])
            ->defaultSort('heure_entree', 'desc')
            ->actions([
                Action::make('enregistrerSortie')
                    ->label('Enregistrer sortie')
                    ->icon('heroicon-o-arrow-right-start-on-rectangle')
                    ->visible(fn (Visiteur $record) => $record->heure_sortie === null)
                    ->action(fn (Visiteur $record) => $record->update(['heure_sortie' => now(), 'badge_rendu' => true])),
                EditAction::make(),
            ])->defaultPaginationPageOption(5), static::class);
    }

    public static function getPages(): array
    {
        return [
            'index' => VisiteurResource\Pages\ListVisiteurs::route('/'),
            'create' => VisiteurResource\Pages\CreateVisiteur::route('/create'),
            'edit' => VisiteurResource\Pages\EditVisiteur::route('/{record}/edit'),
        ];
    }
}
