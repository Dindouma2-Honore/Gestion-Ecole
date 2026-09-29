<?php

declare(strict_types=1);

namespace App\Modules\Finances\Filament\Pages;

use App\Modules\Finances\Models\CatalogueFraisDivers;
use App\Modules\Finances\Models\GrilleFrais;
use App\Modules\Finances\Models\GroupeFrais;
use App\Modules\Finances\Models\TypeFraisRecurrent;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use App\Modules\Socle\Contracts\AuditServiceContract;
use App\Modules\Socle\Contracts\ParametrageServiceContract;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreateFrais extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'finances::filament.pages.create-frais';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-plus-circle';

    protected static string|\UnitEnum|null $navigationGroup = 'Finances';

    protected static ?string $navigationLabel = 'Créer un frais';

    protected static ?string $title = 'Créer un frais';

    protected static ?string $slug = 'finances/frais/create';

    protected static bool $shouldRegisterNavigation = false;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'nature' => 'recurrent',
            'nature_recurrente' => 'autre',
            'ratio_tranche_1' => 50,
            'periodicite' => 'unique',
            'actif' => true,
        ]);
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->hasAnyRole(['Fondateur', 'Comptable']) ?? false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('nature')
                ->label('Nature du frais')
                ->options(['recurrent' => 'Récurrent', 'divers' => 'Ponctuel / divers'])
                ->required()
                ->live(),
            Select::make('groupe_frais_id')
                ->label('Groupe')
                ->options(fn (): array => GroupeFrais::query()->orderBy('id')->pluck('nom', 'id')->all())
                ->required()
                ->helperText('Référentiel fixe : Frais de scolarité ou Autres frais.'),
            TextInput::make('nom')->label('Nom du poste')->required()->maxLength(150),
            Select::make('nature_recurrente')
                ->label('Composante')
                ->options(['inscription' => "Frais d'inscription", 'scolarite' => 'Frais de scolarité', 'autre' => 'Autre frais récurrent'])
                ->default('autre')
                ->visible(fn (Get $get): bool => $get('nature') === 'recurrent')
                ->required(fn (Get $get): bool => $get('nature') === 'recurrent'),
            TextInput::make('montant')->label('Montant')->numeric()->minValue(0)->required()->suffix('FCFA'),
            TextInput::make('ratio_tranche_1')
                ->label('Part de la tranche 1')
                ->numeric()->minValue(0)->maxValue(100)->default(50)->suffix('%')
                ->visible(fn (Get $get): bool => $get('nature') === 'recurrent' && $get('nature_recurrente') === 'scolarite'),
            Select::make('niveau_id')
                ->label('Niveau concerné')
                ->options(fn (): array => collect(app(ParametrageServiceContract::class)->getTousLesNiveaux())->pluck('nom', 'id')->all())
                ->visible(fn (Get $get): bool => $get('nature') === 'recurrent')
                ->helperText('Laissez vide pour appliquer le frais à tous les niveaux.'),
            Select::make('annee_scolaire_id')
                ->label('Année scolaire')
                ->options(fn (): array => collect(app(AnneeScolaireServiceContract::class)->getToutesLesAnnees())->pluck('libelle', 'id')->all())
                ->visible(fn (Get $get): bool => $get('nature') === 'recurrent')
                ->required(fn (Get $get): bool => $get('nature') === 'recurrent'),
            Select::make('categorie')
                ->label('Sous-catégorie')
                ->options(['examen' => 'Examen', 'tenue' => 'Tenue', 'transport' => 'Transport', 'cantine' => 'Cantine', 'excursion' => 'Excursion', 'fourniture' => 'Fourniture', 'autre' => 'Autre'])
                ->visible(fn (Get $get): bool => $get('nature') === 'divers')
                ->required(fn (Get $get): bool => $get('nature') === 'divers'),
            Select::make('periodicite')
                ->label('Périodicité')
                ->options(['unique' => 'Une fois', 'mensuelle' => 'Mensuelle', 'trimestrielle' => 'Trimestrielle', 'annuelle' => 'Annuelle'])
                ->default('unique')
                ->visible(fn (Get $get): bool => $get('nature') === 'divers')
                ->required(fn (Get $get): bool => $get('nature') === 'divers'),
            Toggle::make('actif')->label('Actif')->default(true),
        ])->statePath('data');
    }

    public function create(): void
    {
        $data = $this->form->getState();

        $record = DB::transaction(function () use ($data): object {
            if ($data['nature'] === 'divers') {
                return CatalogueFraisDivers::query()->create([
                    'nom' => $data['nom'],
                    'categorie' => $data['categorie'],
                    'periodicite' => $data['periodicite'],
                    'groupe_frais_id' => $data['groupe_frais_id'],
                    'montant_defaut' => $data['montant'],
                    'actif' => $data['actif'],
                ]);
            }

            $type = TypeFraisRecurrent::query()->create([
                'nom' => $data['nom'],
                'nature' => $data['nature_recurrente'],
                'groupe_frais_id' => $data['groupe_frais_id'],
                'niveau_id' => $data['niveau_id'] ?? null,
                'annee_scolaire_id' => $data['annee_scolaire_id'],
                'montant' => $data['montant'],
                'ratio_tranche_1' => $data['ratio_tranche_1'] ?? 50,
                'actif' => $data['actif'],
            ]);

            if (($data['niveau_id'] ?? null) !== null) {
                GrilleFrais::query()->create([
                    'type_frais_recurrent_id' => $type->id,
                    'niveau_id' => $data['niveau_id'],
                    'annee_scolaire_id' => $data['annee_scolaire_id'],
                    'montant' => $data['montant'],
                ]);
            }

            return $type;
        });

        app(AuditServiceContract::class)->enregistrer($record, "Création du poste de frais {$data['nom']}");
        Notification::make()->success()->title('Frais créé avec succès')->send();
        $this->form->fill([
            'nature' => 'recurrent',
            'nature_recurrente' => 'autre',
            'ratio_tranche_1' => 50,
            'periodicite' => 'unique',
            'actif' => true,
        ]);
        $this->dispatch('platform-state-updated');
    }
}
