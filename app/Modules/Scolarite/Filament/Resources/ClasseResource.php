<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources;

use App\Modules\Finances\Contracts\BilanFinancierClasseServiceContract;
use App\Modules\Scolarite\Filament\Resources\ClasseResource\Pages;
use App\Modules\Scolarite\Filament\Support\ScolariteActions;
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

    /** @var array<int, array<string, int|float>> */
    private static array $resumes = [];

    protected static string|UnitEnum|null $navigationGroup = 'Organisation scolaire';

    protected static ?string $navigationLabel = 'Classes';

    protected static ?int $navigationSort = 5;

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

        ScolariteActions::configureTable($table, 'edit');

        return $table
            ->columns($cards ? self::cardColumns() : self::tableColumns())
            ->contentGrid($cards ? [
                'default' => 1,
                'sm' => 2,
                'md' => 3,
                'lg' => 4,
            ] : null)
            ->actions([
                ScolariteActions::edit(),
                ScolariteActions::view(),
                ScolariteActions::delete(),
            ]);
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
            Tables\Columns\TextColumn::make('effectif')
                ->label('Inscrits (F/G)')
                ->getStateUsing(fn (Classe $record): string => sprintf(
                    '%d (%d/%d)', self::resume($record)['total'], self::resume($record)['filles'], self::resume($record)['garcons']
                )),
            Tables\Columns\TextColumn::make('attendu')
                ->label('Attendu')
                ->getStateUsing(fn (Classe $record): float => (float) self::resume($record)['attendu'])
                ->money('XAF'),
            Tables\Columns\TextColumn::make('encaisse')
                ->label('Encaissé')
                ->getStateUsing(fn (Classe $record): float => (float) self::resume($record)['encaisse'])
                ->money('XAF')
                ->color('success'),
            Tables\Columns\TextColumn::make('reste')
                ->label('Reste')
                ->getStateUsing(fn (Classe $record): float => (float) self::resume($record)['reste'])
                ->money('XAF')
                ->color(fn ($state): string => (float) $state > 0 ? 'warning' : 'success'),
            Tables\Columns\TextColumn::make('statut')->badge(),

            Tables\Columns\TextColumn::make('professeur_principal_id')
                ->label('Professeur principal')
                ->formatStateUsing(fn ($state) => $state ? ($enseignants[$state] ?? '—') : '—'),

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

    /** @return array{total:int,filles:int,garcons:int,attendu:float,encaisse:float,reste:float} */
    public static function resume(Classe $classe): array
    {
        if (isset(self::$resumes[$classe->id])) {
            return self::$resumes[$classe->id];
        }

        $eleves = $classe->inscriptions()
            ->where('annee_scolaire_id', $classe->annee_scolaire_id)
            ->whereIn('statut', ['active', 'validee'])
            ->with('eleve:id,sexe')
            ->get()->pluck('eleve')->filter();
        $finances = app(BilanFinancierClasseServiceContract::class)->calculer(
            $classe->id,
            (int) $classe->annee_scolaire_id,
            $eleves->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
        );

        return self::$resumes[$classe->id] = [
            'total' => $eleves->count(),
            'filles' => $eleves->where('sexe', 'F')->count(),
            'garcons' => $eleves->where('sexe', 'M')->count(),
            ...$finances,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClasses::route('/'),
            'create' => Pages\CreateClasse::route('/create'),
            'view' => Pages\ViewClasse::route('/{record}'),
            'edit' => Pages\EditClasse::route('/{record}/edit'),
        ];
    }
}
