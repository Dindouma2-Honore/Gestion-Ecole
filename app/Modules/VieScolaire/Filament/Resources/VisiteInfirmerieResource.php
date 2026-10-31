<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Filament\Resources;

use App\Filament\Support\RecordModal;
use App\Modules\VieScolaire\Filament\Resources\VisiteInfirmerieResource\Pages;
use App\Modules\VieScolaire\Models\VisiteInfirmerie;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class VisiteInfirmerieResource extends Resource
{
    protected static ?string $model = VisiteInfirmerie::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-plus-circle';

    protected static string|UnitEnum|null $navigationGroup = 'Vie scolaire';

    protected static ?string $navigationLabel = 'Visites infirmerie';

    protected static ?string $modelLabel = 'visite';

    protected static ?string $pluralModelLabel = 'visites infirmerie';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('eleve_id')->label('Élève')->searchable()->preload()->options(fn () => DB::table('eleves')->whereNull('deleted_at')->orderBy('nom')->get()->mapWithKeys(fn ($e) => [$e->id => trim("{$e->nom} {$e->prenom}")." — {$e->matricule_permanent}"])->all())->required(),
            Textarea::make('motif')->label('Motif')->required(),
            Textarea::make('symptomes')->label('Symptômes'),
            Textarea::make('soins_prodigues')->label('Soins prodigués'),
            TextInput::make('medicament_administre')->label('Médicament administré'),
            TextInput::make('dose_administree')->label('Dose administrée'),
            Select::make('gravite')->label('Gravité')->options([
                'mineure' => 'Mineure',
                'moderee' => 'Modérée',
                'grave' => 'Grave',
            ])->required(),
            Select::make('decision')->label('Décision')->options(['retour_classe' => 'Retour en classe', 'repos' => 'Repos à l’infirmerie', 'parent_appele' => 'Parent appelé', 'sortie' => 'Sortie de l’établissement', 'evacuation' => 'Évacuation médicale', 'autre' => 'Autre']),
            Textarea::make('observations')->label('Observations'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return RecordModal::configure($table->columns([
            Tables\Columns\TextColumn::make('eleve_id')->label('Élève')->formatStateUsing(fn ($state) => optional(DB::table('eleves')->find($state))->nom ?? '—'),
            Tables\Columns\TextColumn::make('date_heure')->label('Date')->dateTime('d/m/Y H:i')->sortable(),
            Tables\Columns\TextColumn::make('motif')->label('Motif')->limit(50),
            Tables\Columns\TextColumn::make('gravite')->label('Gravité')->badge(),
            Tables\Columns\IconColumn::make('evacuation_necessaire')->label('Évacuation')->boolean(),
            Tables\Columns\IconColumn::make('parent_notifie')->label('Parent notifié')->boolean(),
        ])->actions([
            EditAction::make(),
        ])->filters([
            Tables\Filters\SelectFilter::make('gravite')->options([
                'mineure' => 'Mineure',
                'moderee' => 'Modérée',
                'grave' => 'Grave',
            ]),
        ])->defaultPaginationPageOption(5), static::class);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVisitesInfirmerie::route('/'),
            'create' => Pages\CreateVisiteInfirmerie::route('/create'),
            'edit' => Pages\EditVisiteInfirmerie::route('/{record}/edit'),
        ];
    }
}
