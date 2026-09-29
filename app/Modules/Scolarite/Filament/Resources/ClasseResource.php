<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources;

use App\Modules\Scolarite\Filament\Resources\ClasseResource\Pages;
use App\Modules\Scolarite\Models\Classe;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class ClasseResource extends Resource
{
    protected static ?string $model = Classe::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static string|UnitEnum|null $navigationGroup = 'Scolarité';

    protected static ?string $navigationLabel = 'Classes';

    protected static ?string $modelLabel = 'classe';

    protected static ?string $pluralModelLabel = 'classes';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nom')
                    ->label('Nom')
                    ->required()
                    ->maxLength(50)
                    ->helperText('Ex: CP1 A, Terminale C...'),

                TextInput::make('code')->label('Code automatique')->disabled()->dehydrated(false)->visibleOn('edit'),

                Select::make('niveau_id')
                    ->label('Niveau')
                    ->options(fn () => self::optionsNiveaux())
                    ->searchable()
                    ->required(),

                Select::make('annee_scolaire_id')
                    ->label('Année scolaire')
                    ->options(fn () => self::optionsAnneesScolaires())
                    ->searchable()
                    ->required(),

                TextInput::make('capacite_max')
                    ->label('Capacité maximale')
                    ->numeric()
                    ->required(),

                Select::make('filiere_id')->label('Série / filière')->options(fn () => DB::table('filieres')->where('actif', true)->pluck('nom', 'id'))->searchable(),
                Select::make('section_id')->label('Section')->options(fn () => DB::table('sections_scolaires')->where('actif', true)->pluck('nom', 'id'))->searchable(),
                Select::make('salle_principale_id')
                    ->label('Salle principale')
                    ->options(fn () => DB::table('salles')->where('type', 'classe')->where('etat', 'bon')->orderBy('nom')->pluck('nom', 'id'))
                    ->searchable(),
                Select::make('statut')->options(['active' => 'Active', 'inactive' => 'Inactive', 'archivee' => 'Archivée'])->default('active')->required(),

                Select::make('professeur_principal_id')
                    ->label('Enseignant principal')
                    ->options(fn () => self::optionsEnseignants())
                    ->searchable()
                    ->required(),

                Select::make('assistant_ids')
                    ->label('Personnels assistants')
                    ->options(fn () => self::optionsPersonnel())
                    ->multiple()->preload()->searchable()->dehydrated(false),

                TextInput::make('frais_inscription')
                    ->label("Frais d'inscription")
                    ->numeric()
                    ->default(fn (): int => (int) config('scolarite.frais_inscription'))
                    ->suffix('FCFA')
                    ->helperText('Montant unique fixé pour tous les niveaux.')
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('tranche_1')->label('Scolarité — tranche 1')->numeric()->minValue(0)->suffix('FCFA')->required()->dehydrated(false),
                TextInput::make('tranche_2')->label('Scolarité — tranche 2')->numeric()->minValue(0)->suffix('FCFA')->required()->dehydrated(false),
                TextInput::make('tranche_3')->label('Scolarité — tranche 3')->numeric()->minValue(0)->suffix('FCFA')->required()->dehydrated(false),
                TextInput::make('tranche_4')->label('Scolarité — tranche 4')->numeric()->minValue(0)->suffix('FCFA')->required()->dehydrated(false),
                TextInput::make('tranche_5')->label('Scolarité — tranche 5')->numeric()->minValue(0)->suffix('FCFA')->required()->dehydrated(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        $livewire = $table->getLivewire();
        $cards = $livewire instanceof Pages\ListClasses
            && method_exists($livewire, 'usesCardLayout')
            && $livewire->usesCardLayout();

        return $table
            ->columns($cards ? self::cardColumns() : self::tableColumns())
            ->contentGrid($cards ? [
                'default' => 1,
                'sm' => 2,
                'md' => 3,
                'lg' => 4,
            ] : null);
    }

    /** @return array<int, mixed> */
    private static function tableColumns(): array
    {
        $niveaux = self::optionsNiveaux();
        $annees = self::optionsAnneesScolaires();
        $enseignants = self::optionsEnseignants();

        return [
            Tables\Columns\TextColumn::make('nom')->label('Nom')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('code')->label('Code')->searchable(),

            Tables\Columns\TextColumn::make('niveau_id')
                ->label('Niveau')
                ->formatStateUsing(fn ($state) => $niveaux[$state] ?? '—'),

            Tables\Columns\TextColumn::make('annee_scolaire_id')
                ->label('Année scolaire')
                ->formatStateUsing(fn ($state) => $annees[$state] ?? '—'),

            Tables\Columns\TextColumn::make('capacite_max')->label('Capacité max'),
            Tables\Columns\TextColumn::make('statut')->badge(),

            Tables\Columns\TextColumn::make('professeur_principal_id')
                ->label('Professeur principal')
                ->formatStateUsing(fn ($state) => $state ? ($enseignants[$state] ?? '—') : '—'),

            Tables\Columns\TextColumn::make('inscriptions_count')
                ->label('Effectif')
                ->counts(['inscriptions' => fn ($q) => $q->where('statut', 'validee')]),
        ];
    }

    /** @return array<int, mixed> */
    private static function cardColumns(): array
    {
        return [
            Stack::make([
                Tables\Columns\ViewColumn::make('carte_classe')
                    ->view('scolarite::filament.tables.carte-classe'),
            ]),
        ];
    }

    /**
     * Niveau (Socle) : pas de relation Eloquent inter-module, simple
     * lecture de la table pour peupler le Select.
     *
     * @return Collection<int, string>
     */
    private static function optionsNiveaux(): Collection
    {
        try {
            return DB::table('niveaux')->orderBy('nom')->pluck('nom', 'id');
        } catch (\Throwable $e) {
            return collect();
        }
    }

    /**
     * Année scolaire (Socle) : idem, pas de relation Eloquent inter-module.
     *
     * @return Collection<int, string>
     */
    private static function optionsAnneesScolaires(): Collection
    {
        try {
            return DB::table('annees_scolaires')->orderByDesc('id')->pluck('libelle', 'id');
        } catch (\Throwable $e) {
            return collect();
        }
    }

    /**
     * Professeur principal = un Enseignant (RH), dont le nom vient de son
     * Employe lié. Pas de relation Eloquent inter-module non plus : simple
     * jointure en lecture seule pour le libellé du Select.
     *
     * @return Collection<int, string>
     */
    private static function optionsEnseignants(): Collection
    {
        try {
            return DB::table('enseignants')
                ->join('employes', 'employes.id', '=', 'enseignants.employe_id')
                ->orderBy('employes.nom')
                ->pluck('employes.nom', 'enseignants.id');
        } catch (\Throwable $e) {
            return collect();
        }
    }

    private static function optionsPersonnel(): Collection
    {
        try {
            return DB::table('employes')->where('statut', 'actif')->orderBy('nom')
                ->get()->mapWithKeys(fn (object $employe): array => [$employe->id => trim($employe->nom.' '.$employe->prenom)]);
        } catch (\Throwable) {
            return collect();
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClasses::route('/'),
            'create' => Pages\CreateClasse::route('/create'),
            'edit' => Pages\EditClasse::route('/{record}/edit'),
        ];
    }
}
