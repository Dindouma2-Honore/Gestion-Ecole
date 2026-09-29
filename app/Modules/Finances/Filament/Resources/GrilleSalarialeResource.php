<?php

declare(strict_types=1);

namespace App\Modules\Finances\Filament\Resources;

use App\Modules\Finances\Filament\Resources\GrilleSalarialeResource\Pages;
use App\Modules\Finances\Models\GrilleSalariale;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class GrilleSalarialeResource extends Resource
{
    protected static ?string $model = GrilleSalariale::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-table-cells';

    protected static string|\UnitEnum|null $navigationGroup = 'Finances';

    protected static ?string $modelLabel = 'ligne salariale';

    protected static ?string $pluralModelLabel = 'Grille salariale';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Positionnement dans la grille')
                ->description('Définissez le palier d’ancienneté et le personnel concerné par cette ligne.')
                ->icon('heroicon-o-identification')
                ->columns(2)
                ->schema([
                    Select::make('categorie_personnel_id')->label('Catégorie d’ancienneté')->options(fn () => DB::table('categories_personnel')->where('progression_automatique', true)->orderBy('anciennete_min_mois')->pluck('nom', 'id')->all())->required()->searchable(),
                    Select::make('base_calcul')->label('Champ d’application')->options(self::basesCalcul())->required()->live(),
                    Select::make('matiere_id')->label('Matière enseignée')->options(fn () => DB::table('matieres')->where('actif', true)->orderBy('nom')->pluck('nom', 'id')->all())->searchable()->visible(fn (Get $get) => $get('base_calcul') === 'matiere')->required(fn (Get $get) => $get('base_calcul') === 'matiere'),
                    Select::make('poste_administratif_id')->label('Fonction administrative')->options(fn () => DB::table('poste_administratifs')->orderBy('nom')->pluck('nom', 'id')->all())->searchable()->visible(fn (Get $get) => $get('base_calcul') === 'fonction')->required(fn (Get $get) => $get('base_calcul') === 'fonction'),
                    TextInput::make('tache')->label('Tâche administrative')->placeholder('Ex. permanence, surveillance, saisie…')->visible(fn (Get $get) => $get('base_calcul') === 'tache')->required(fn (Get $get) => $get('base_calcul') === 'tache'),
                ]),
            Section::make('Rémunération')
                ->description('Les deux montants permettent de couvrir les contrats fixes, horaires et mixtes.')
                ->icon('heroicon-o-banknotes')
                ->columns(2)
                ->schema([
                    TextInput::make('salaire_base')->label('Salaire mensuel de base')->numeric()->minValue(0)->prefix('FCFA')->default(0)->required(),
                    TextInput::make('taux_horaire')->label('Taux par heure')->numeric()->minValue(0)->prefix('FCFA')->default(0)->required(),
                ]),
            Section::make('Validité et traçabilité')
                ->icon('heroicon-o-shield-check')
                ->columns(2)
                ->schema([
                    DatePicker::make('date_effet')->label('Applicable à partir du')->default(now())->required(),
                    Toggle::make('actif')->label('Ligne active')->default(true)->inline(false),
                    TextInput::make('source')->label('Source ou justification')->placeholder('Ex. contrats FACILG 2020–2026, décision de direction…')->maxLength(255)->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('categorie_nom')->label('Palier')->badge()->color(fn (?string $state): string => match ($state) {
                'Junior' => 'info', 'Confirmé' => 'success', 'Senior' => 'warning', 'Expert' => 'danger', default => 'gray'
            })->sortable(),
            TextColumn::make('base_calcul')->label('Application')->badge()->formatStateUsing(fn (string $state): string => self::basesCalcul()[$state] ?? $state)->color('gray'),
            TextColumn::make('contexte')->label('Matière, fonction ou tâche')->weight('medium')->searchable(['tache']),
            TextColumn::make('salaire_base')->label('Base mensuelle')->money('XAF')->weight('bold')->sortable(),
            TextColumn::make('taux_horaire')->label('Taux horaire')->money('XAF')->weight('bold')->sortable(),
            TextColumn::make('date_effet')->label('Depuis le')->date('d/m/Y')->sortable(),
            IconColumn::make('actif')->label('Active')->boolean(),
            TextColumn::make('source')->label('Référence')->wrap()->limit(45)->tooltip(fn (GrilleSalariale $record): ?string => $record->source)->toggleable(),
        ])
            ->filters([
                SelectFilter::make('categorie_personnel_id')->label('Catégorie')->options(fn () => DB::table('categories_personnel')->orderBy('anciennete_min_mois')->pluck('nom', 'id')->all()),
                SelectFilter::make('base_calcul')->label('Champ d’application')->options(self::basesCalcul()),
                TernaryFilter::make('actif')->label('État')->trueLabel('Actives')->falseLabel('Inactives')->placeholder('Toutes'),
            ])
            ->defaultSort('date_effet', 'desc')
            ->emptyStateHeading('Aucune ligne salariale')
            ->emptyStateDescription('Ajoutez une ligne pour définir le salaire de base ou le taux horaire d’un palier.')
            ->emptyStateIcon('heroicon-o-table-cells')
            ->actions([EditAction::make()->label('Modifier'), DeleteAction::make()->label('Supprimer')]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListGrillesSalariales::route('/'), 'create' => Pages\CreateGrilleSalariale::route('/create'), 'edit' => Pages\EditGrilleSalariale::route('/{record}/edit')];
    }

    public static function basesCalcul(): array
    {
        return ['generale' => 'Tout le personnel', 'matiere' => 'Matière enseignée', 'fonction' => 'Fonction administrative', 'tache' => 'Tâche administrative'];
    }
}
