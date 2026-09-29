<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources;

use App\Modules\Socle\Filament\Resources\WorkflowDefinitionResource\Pages;
use App\Modules\Socle\Models\WorkflowDefinition;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class WorkflowDefinitionResource extends Resource
{
    protected static ?string $model = WorkflowDefinition::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string|\UnitEnum|null $navigationGroup = 'Workflows & validations';

    protected static ?string $modelLabel = 'Workflow';

    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole('Fondateur') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->hasRole('Fondateur') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->required()->disabledOn('edit'),
            TextInput::make('nom')->required(),
            TextInput::make('module_proprietaire')->required(),
            Repeater::make('etapes')->relationship()->schema([
                TextInput::make('ordre')->numeric()->required(),
                TextInput::make('nom')->required(),
                Select::make('validateur_type')->options(['fondateur' => 'Fondateur', 'role' => 'Rôle', 'utilisateur' => 'Utilisateur'])->required(),
                TextInput::make('validateur_valeur'),
                Textarea::make('condition')->helperText('Condition JSON flexible, ex. {"seuil_defaut":100000}')->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state) : $state)->dehydrateStateUsing(fn ($state) => is_string($state) ? json_decode($state, true) : $state),
            ])->orderColumn('ordre')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('code')->searchable(), TextColumn::make('nom'), TextColumn::make('module_proprietaire')->label('Module'),
            TextColumn::make('version')->badge(), TextColumn::make('etapes_count')->counts('etapes')->label('Étapes'), IconColumn::make('actif')->boolean(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListWorkflowDefinitions::route('/'), 'create' => Pages\CreateWorkflowDefinition::route('/create'), 'edit' => Pages\EditWorkflowDefinition::route('/{record}/edit')];
    }
}
