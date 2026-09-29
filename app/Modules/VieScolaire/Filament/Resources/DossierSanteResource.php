<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Filament\Resources;

use App\Modules\VieScolaire\Models\DossierSante;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
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

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('eleve_id')
                    ->label('ID élève')
                    ->numeric()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->helperText('En attendant un Select alimenté par EleveServiceInterface (Scolarité).'),

                TextInput::make('groupe_sanguin')->label('Groupe sanguin')->maxLength(5),
                Textarea::make('allergies')->label('Allergies'),
                Textarea::make('maladies_chroniques')->label('Maladies chroniques'),
                Textarea::make('medicaments_autorises')->label('Médicaments autorisés'),
                TextInput::make('contact_urgence_nom')->label('Contact urgence — nom')->required(),
                TextInput::make('contact_urgence_telephone')->label('Contact urgence — téléphone')->tel()->required(),
                TextInput::make('medecin_traitant')->label('Médecin traitant'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('eleve_id')->label('Élève (ID)'),
                Tables\Columns\TextColumn::make('groupe_sanguin')->label('Groupe sanguin')->placeholder('—'),
                Tables\Columns\TextColumn::make('allergies')->label('Allergies')->limit(40)->placeholder('—'),
                Tables\Columns\TextColumn::make('contact_urgence_nom')->label('Contact urgence'),
            ])
            ->actions([
                EditAction::make(),
            ]);
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
