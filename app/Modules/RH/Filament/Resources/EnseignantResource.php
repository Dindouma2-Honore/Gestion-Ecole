<?php

declare(strict_types=1);

namespace App\Modules\RH\Filament\Resources;

use App\Modules\RH\Models\Enseignant;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EnseignantResource extends Resource
{
    protected static ?string $model = Enseignant::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static string|\UnitEnum|null $navigationGroup = 'Ressources humaines';

    protected static ?string $modelLabel = 'Enseignant';

    protected static ?string $pluralModelLabel = 'Corps Enseignant';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('employe_id')
                ->relationship('employe', 'nom')
                ->required(),
            TextInput::make('specialite')
                ->required()
                ->maxLength(100),
            Select::make('statut_contractuel')
                ->options([
                    'permanent' => 'Permanent',
                    'vacataire' => 'Vacataire',
                    'contractuel' => 'Contractuel',
                ])
                ->required(),
            TextInput::make('charge_horaire_hebdo')
                ->numeric()
                ->default(18.0)
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        $livewire = $table->getLivewire();
        $cards = $livewire instanceof EnseignantResource\Pages\ListEnseignants
            && method_exists($livewire, 'usesCardLayout')
            && $livewire->usesCardLayout();

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('employe'))
            ->columns($cards ? [
                ViewColumn::make('carte_enseignant')
                    ->label('Enseignant')
                    ->view('rh::filament.tables.enseignant-card'),
            ] : [
                TextColumn::make('employe.nom_complet')->label('Enseignant')->sortable()->searchable(),
                TextColumn::make('specialite')->sortable()->searchable(),
                TextColumn::make('statut_contractuel')->badge(),
                TextColumn::make('charge_horaire_hebdo')->label('Charge (h/sem)')->sortable(),
            ])
            ->contentGrid($cards ? ['md' => 2, 'xl' => 3] : null);
    }

    public static function getPages(): array
    {
        return [
            'index' => EnseignantResource\Pages\ListEnseignants::route('/'),
            'create' => EnseignantResource\Pages\CreateEnseignant::route('/create'),
            'edit' => EnseignantResource\Pages\EditEnseignant::route('/{record}/edit'),
        ];
    }
}
