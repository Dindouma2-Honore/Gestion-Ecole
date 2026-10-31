<?php

declare(strict_types=1);

namespace App\Modules\RH\Filament\Resources;

use App\Filament\Support\AmbassadorsDesign;
use App\Modules\RH\Filament\Resources\CategoriePersonnelResource\Pages;
use App\Modules\RH\Filament\Support\RhActions;
use App\Modules\RH\Models\CategoriePersonnel;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CategoriePersonnelResource extends Resource
{
    protected static ?string $model = CategoriePersonnel::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'catégorie libre';

    protected static ?string $pluralModelLabel = 'Catégories libres';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informations')->columns(2)->schema([
                TextInput::make('nom')->label('Nom')->required()->maxLength(255)->unique(ignoreRecord: true),
                Toggle::make('actif')->label('Active')->default(true),
                Textarea::make('description')->label('Description')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        AmbassadorsDesign::configureTable($table, null);

        return $table->columns([
            TextColumn::make('nom')->label('Catégorie')->searchable()->sortable(),
            TextColumn::make('description')->label('Description')->limit(60),
            TextColumn::make('employes_count')->counts('employes')->label('Personnel associé')->sortable(),
            IconColumn::make('actif')->label('Active')->boolean(),
            TextColumn::make('created_at')->label('Créée le')->dateTime('d/m/Y')->sortable(),
        ])->defaultSort('created_at', 'desc')->actions([
            RhActions::edit()
                ->successNotification(fn (CategoriePersonnel $record): Notification => Notification::make()
                    ->color('gray')
                    ->title('Catégorie mise à jour')
                    ->body('La catégorie « '.$record->nom.' » a été mise à jour avec succès.')),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategoriesPersonnel::route('/'),
            'create' => Pages\CreateCategoriePersonnel::route('/create'),
            'edit' => Pages\EditCategoriePersonnel::route('/{record}/edit'),
        ];
    }
}
