<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Filament\Resources;

use App\Filament\Support\RecordModal;
use App\Modules\VieScolaire\Models\SortieEleve;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class SortieEleveResource extends Resource
{
    protected static ?string $model = SortieEleve::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-right-start-on-rectangle';

    protected static string|UnitEnum|null $navigationGroup = 'Vie scolaire';

    protected static ?string $navigationLabel = 'Sorties élèves';

    protected static ?string $modelLabel = 'sortie élève';

    protected static ?string $pluralModelLabel = 'sorties élèves';

    protected static bool $shouldRegisterNavigation = false;

    /**
     * Formulaire simplifié pour la sortie normale — la vérification
     * d'autorisation vit dans SortieEleveServiceInterface, jamais
     * recalculée ici. Pour une sortie exceptionnelle (avec justificatif
     * et motif), passer par une action dédiée plutôt que ce formulaire.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('eleve_id')->label('Élève')->searchable()->preload()->options(fn () => DB::table('eleves')->whereNull('deleted_at')->orderBy('nom')->get()->mapWithKeys(fn ($e) => [$e->id => trim("{$e->nom} {$e->prenom}")." — {$e->matricule_permanent}"])->all())->required(),

                Select::make('parent_id')->label('Parent / tuteur autorisé')->searchable()->preload()->options(fn () => DB::table('parents_tuteurs')->join('eleve_parent', 'eleve_parent.parent_id', '=', 'parents_tuteurs.id')->where('eleve_parent.autorise_recuperation', true)->orderBy('parents_tuteurs.nom')->get(['parents_tuteurs.id', 'parents_tuteurs.nom', 'parents_tuteurs.prenom', 'parents_tuteurs.telephone'])->mapWithKeys(fn ($p) => [$p->id => trim("{$p->nom} {$p->prenom}").($p->telephone ? " — {$p->telephone}" : '')])->all()),

                TextInput::make('personne_autorisee_nom')
                    ->label('Nom (sortie exceptionnelle)')
                    ->maxLength(255),

                Select::make('type')
                    ->label('Type')
                    ->options(['normale' => 'Normale', 'exceptionnelle' => 'Exceptionnelle'])
                    ->required(),
                Textarea::make('motif')->label('Motif')->visible(fn ($get) => $get('type') === 'exceptionnelle')->required(fn ($get) => $get('type') === 'exceptionnelle'),

                DateTimePicker::make('heure_sortie')
                    ->label('Heure de sortie')
                    ->default(now())
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return RecordModal::configure($table
            ->columns([
                Tables\Columns\TextColumn::make('eleve_id')->label('Élève')->formatStateUsing(fn ($state) => optional(DB::table('eleves')->find($state))->nom ?? '—'),
                Tables\Columns\TextColumn::make('type')->label('Type')->badge(),
                Tables\Columns\TextColumn::make('personne_autorisee_nom')->label('Personne')->placeholder('—'),
                Tables\Columns\TextColumn::make('heure_sortie')->label('Heure')->dateTime(),
                Tables\Columns\IconColumn::make('justificatif_document_id')
                    ->label('Justificatif')
                    ->boolean()
                    ->getStateUsing(fn (SortieEleve $record) => $record->justificatif_document_id !== null),
            ])
            ->defaultSort('heure_sortie', 'desc')
            ->actions([
                ViewAction::make(),
            ])->defaultPaginationPageOption(5), static::class);
    }

    public static function getPages(): array
    {
        return [
            'index' => SortieEleveResource\Pages\ListSortiesEleves::route('/'),
            'create' => SortieEleveResource\Pages\CreateSortieEleve::route('/create'),
            'edit' => SortieEleveResource\Pages\EditSortieEleve::route('/{record}/edit'),
        ];
    }
}
