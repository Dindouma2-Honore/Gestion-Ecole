<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources;

use App\Modules\Socle\Filament\Resources\DocumentTemplateResource\Pages;
use App\Modules\Socle\Models\DocumentTemplate;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class DocumentTemplateResource extends Resource
{
    protected static ?string $model = DocumentTemplate::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-duplicate';

    protected static string|\UnitEnum|null $navigationGroup = 'Modèles de documents';

    protected static ?string $modelLabel = 'Version de modèle';

    protected static ?string $pluralModelLabel = 'Bibliothèque des modèles';

    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole('Fondateur') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->required()->maxLength(50)->helperText('Identifiant stable, sans rapport avec la numérotation.'),
            TextInput::make('nom')->required(),
            TextInput::make('type_document')->required(),
            Select::make('module_proprietaire')->options(['Pédagogie' => 'Pédagogie', 'Finances' => 'Finances', 'Scolarité' => 'Scolarité', 'RH' => 'RH', 'Administration' => 'Administration'])->required(),
            Select::make('cycle')->options(['maternelle' => 'Maternelle', 'primaire' => 'Primaire', 'secondaire' => 'Secondaire']),
            Textarea::make('contenu')->label('Gabarit HTML / Blade')->rows(24)->required()->columnSpanFull(),
            Select::make('orientation')->options(['portrait' => 'Portrait', 'paysage' => 'Paysage'])->default('portrait')->required(),
            TextInput::make('format_papier')->default('A4')->required(),
            DatePicker::make('date_effet')->default(today())->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('code')->searchable()->sortable()->description(fn (DocumentTemplate $record): string => $record->nom),
            TextColumn::make('version')->label('Version')->badge()->sortable(),
            TextColumn::make('type_document')->label('Type')->badge(),
            TextColumn::make('module_proprietaire')->label('Module'),
            TextColumn::make('date_effet')->date('d/m/Y')->sortable(),
            IconColumn::make('actif')->boolean(),
        ])->recordActions([
            Action::make('apercu')
                ->label('Aperçu')
                ->icon('heroicon-o-eye')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fermer')
                ->modalWidth('7xl')
                ->modalContent(fn (DocumentTemplate $record) => view('socle::filament.document-template-preview', ['template' => $record])),
        ])->defaultSort('version', 'desc')->groups(['code']);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListDocumentTemplates::route('/'), 'create' => Pages\CreateDocumentTemplate::route('/create')];
    }
}
