<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources;

use App\Modules\Socle\Models\TemplateDocument;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TemplateDocumentResource extends Resource
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $model = TemplateDocument::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-duplicate';

    protected static string|\UnitEnum|null $navigationGroup = 'Modèles de documents';

    protected static ?string $modelLabel = 'Modèle de document';

    protected static ?string $pluralModelLabel = 'Modèles de documents';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->required()->unique(ignoreRecord: true),
            TextInput::make('nom')->required(),
            Select::make('type')->options(['pdf' => 'PDF', 'docx' => 'Word', 'xlsx' => 'Excel'])->required(),
            FileUpload::make('fichier_template')->label('Fichier modèle / Vue')->directory('templates/documents')->nullable(),
            Textarea::make('contenu_html')->label('Contenu HTML / Code du modèle')->rows(8)->nullable(),
            TagsInput::make('variables_disponibles')->label('Variables disponibles')->placeholder('Ajouter une variable...'),
            TextInput::make('version')->numeric()->minValue(1)->default(1)->required(),
            Toggle::make('actif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('code')->searchable()->sortable(),
            TextColumn::make('nom')->searchable(),
            TextColumn::make('type')->badge(),
            TextColumn::make('version')->numeric(),
            IconColumn::make('actif')->boolean(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => TemplateDocumentResource\Pages\ListTemplatesDocuments::route('/'),
            'create' => TemplateDocumentResource\Pages\CreateTemplateDocument::route('/create'),
            'edit' => TemplateDocumentResource\Pages\EditTemplateDocument::route('/{record}/edit'),
        ];
    }
}
