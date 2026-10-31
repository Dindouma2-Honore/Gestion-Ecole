<?php

declare(strict_types=1);

namespace App\Modules\RH\Filament\Resources;

use App\Filament\Support\AmbassadorsDesign;
use App\Modules\RH\Filament\Resources\PosteAdministratifResource\Pages;
use App\Modules\RH\Filament\Support\RhActions;
use App\Modules\RH\Models\PosteAdministratif;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PosteAdministratifResource extends Resource
{
    protected static ?string $model = PosteAdministratif::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'fonction';

    protected static ?string $pluralModelLabel = 'fonctions';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nom')->label('Nom de la fonction')->required()->maxLength(255)->unique(ignoreRecord: true),
            Select::make('parent_id')
                ->label('Supérieur hiérarchique (Fonction N+1)')
                ->relationship('parent', 'nom')
                ->searchable()
                ->preload()
                ->placeholder('Aucun (Sommet / Gouvernance)')
                ->nullable(),
            TextInput::make('departement')
                ->label('Pôle / Département')
                ->placeholder('ex: Direction Générale, Pédagogie, Vie Scolaire, Administration...')
                ->maxLength(100),
            TextInput::make('niveau_hierarchique')
                ->label('Niveau hiérarchique (1 = Sommet)')
                ->numeric()
                ->default(1)
                ->minValue(1)
                ->maxValue(10),
            TextInput::make('ordre')
                ->label('Ordre d\'affichage')
                ->numeric()
                ->default(0),
            Textarea::make('description')->label('Description / Missions')->columnSpanFull(),
            Toggle::make('actif')->label('Fonction active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        AmbassadorsDesign::configureTable($table, null);

        return $table->columns([
            TextColumn::make('nom')->label('Fonction')->searchable()->sortable(),
            TextColumn::make('parent.nom')->label('Supérieur N+1')->placeholder('—')->sortable(),
            TextColumn::make('departement')->label('Pôle')->badge()->color('gray')->sortable(),
            TextColumn::make('niveau_hierarchique')->label('Niveau')->sortable(),
            TextColumn::make('employes_count')->counts('employes')->label('Personnel associé')->sortable(),
            IconColumn::make('actif')->label('Active')->boolean(),
            TextColumn::make('created_at')->label('Créée le')->dateTime('d/m/Y')->sortable(),
        ])->defaultSort('created_at', 'desc')->actions([
            RhActions::edit()
                ->successNotification(fn (PosteAdministratif $record): Notification => Notification::make()
                    ->color('gray')
                    ->title('Fonction mise à jour')
                    ->body('La fonction « '.$record->nom.' » a été mise à jour avec succès.')),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPostesAdministratifs::route('/'),
            'create' => Pages\CreatePosteAdministratif::route('/create'),
            'edit' => Pages\EditPosteAdministratif::route('/{record}/edit'),
        ];
    }
}
