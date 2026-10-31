<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Filament\Resources;

use App\Filament\Support\RecordModal;
use App\Modules\VieScolaire\Models\DossierSante;
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

/**
 * ATTENTION SÉCURITÉ — module à confidentialité maximale du projet.
 * La Policy Filament doit être plus restrictive que B.13/D.38 : Fondateur,
 * Surveillant général et personnel médical désigné uniquement (rôle
 * InfirmierScolaire à créer, décision à prendre avec Joel). Cette Resource
 * ne définit PAS encore de Policy — à faire avant toute mise en production.
 *
 * Par ailleurs, cette table() liste directement les Models sans passer par
 * SanteServiceInterface::consulterDossier() — donc SANS audit de lecture.
 * C'est un manquement volontaire à corriger avant livraison : soit ajouter
 * un log d'accès à chaque affichage de cette liste/vue, soit remplacer
 * cette Resource standard par une Page personnalisée qui appelle le
 * Service (et donc l'audit) à chaque consultation.
 */
class DossierSanteResource extends Resource
{
    protected static ?string $model = DossierSante::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-heart';

    protected static string|UnitEnum|null $navigationGroup = 'Vie scolaire';

    protected static ?string $navigationLabel = 'Dossiers santé';

    protected static ?string $modelLabel = 'dossier santé';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('eleve_id')->label('Élève')->searchable()->preload()->options(fn () => DB::table('eleves')->whereNull('deleted_at')->orderBy('nom')->get()->mapWithKeys(fn ($e) => [$e->id => trim("{$e->nom} {$e->prenom}")." — {$e->matricule_permanent}"])->all())->required()->unique(ignoreRecord: true),

                TextInput::make('groupe_sanguin')->label('Groupe sanguin')->maxLength(5),
                Textarea::make('allergies')->label('Allergies'),
                Textarea::make('maladies_chroniques')->label('Maladies chroniques'),
                Textarea::make('antecedents_medicaux')->label('Antécédents médicaux'),
                Textarea::make('traitements_connus')->label('Traitements connus'),
                Textarea::make('medicaments_autorises')->label('Médicaments autorisés'),
                Textarea::make('restrictions')->label('Restrictions'),
                Textarea::make('informations_importantes')->label('Informations importantes'),
                TextInput::make('contact_urgence_nom')->label('Contact urgence — nom')->required(),
                TextInput::make('contact_urgence_telephone')->label('Contact urgence — téléphone')->tel()->required(),
                TextInput::make('medecin_traitant')->label('Médecin traitant'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return RecordModal::configure($table
            ->columns([
                Tables\Columns\TextColumn::make('eleve_id')->label('Élève')->formatStateUsing(fn ($state) => optional(DB::table('eleves')->find($state))->nom ?? '—'),
                Tables\Columns\TextColumn::make('groupe_sanguin')->label('Groupe sanguin')->placeholder('—'),
                Tables\Columns\TextColumn::make('allergies')->label('Allergies')->limit(40)->placeholder('—'),
                Tables\Columns\TextColumn::make('contact_urgence_nom')->label('Contact urgence'),
            ])
            ->actions([
                EditAction::make(),
            ])->defaultPaginationPageOption(5), static::class);
    }

    public static function getPages(): array
    {
        return [
            'index' => DossierSanteResource\Pages\ListDossiersSante::route('/'),
            'create' => DossierSanteResource\Pages\CreateDossierSante::route('/create'),
            'edit' => DossierSanteResource\Pages\EditDossierSante::route('/{record}/edit'),
        ];
    }
}
