<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources;

use App\Modules\Scolarite\Filament\Resources\EleveResource\Pages;
use App\Modules\Scolarite\Models\Eleve;
use App\Modules\Scolarite\Models\TypeDocumentEleve;
use App\Modules\Scolarite\Support\ElevePhoto;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Table;
use UnitEnum;

class EleveResource extends Resource
{
    protected static ?string $model = Eleve::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user';

    protected static string|UnitEnum|null $navigationGroup = 'Scolarité';

    protected static ?string $navigationLabel = 'Élèves';

    protected static ?string $modelLabel = 'élève';

    protected static ?string $pluralModelLabel = 'élèves';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nom')->label('Nom')->required()->maxLength(100),
                TextInput::make('prenom')->label('Prénom')->required()->maxLength(100),
                DatePicker::make('date_naissance')->label('Date de naissance'),

                Select::make('sexe')
                    ->label('Sexe')
                    ->options(['M' => 'Masculin', 'F' => 'Féminin']),

                FileUpload::make('photo')
                    ->label('Photo de l’enfant')
                    ->helperText('Formats JPG, JPEG ou PNG — 5 Mo maximum.')
                    ->disk('public')
                    ->image()
                    ->acceptedFileTypes(ElevePhoto::MIME_TYPES)
                    ->maxSize(ElevePhoto::MAX_SIZE_KO)
                    ->rules(ElevePhoto::validationRules())
                    ->directory('eleves/photos')
                    ->imagePreviewHeight('180')
                    ->previewable(),

                Select::make('statut')
                    ->label('Statut')
                    ->options([
                        'prospect' => 'Prospect', 'candidat' => 'Candidat', 'admis' => 'Admis',
                        'inscrit' => 'Inscrit', 'actif' => 'Actif', 'suspendu' => 'Suspendu',
                        'retire' => 'Retiré', 'diplome' => 'Diplômé', 'archive' => 'Archivé',
                    ])
                    ->default('prospect')
                    ->disabledOn('create')
                    ->helperText('Le statut évolue automatiquement (ex: passe à "inscrit" via le module Inscriptions).'),
                Repeater::make('documentsAdmission')->relationship()->label('Documents d’admission')->schema([
                    Select::make('type_document_eleve_id')->label('Type')->options(fn (): array => TypeDocumentEleve::query()->where('actif', true)->orderByDesc('obligatoire')->orderBy('nom')->get()->mapWithKeys(fn ($type): array => [$type->id => $type->nom.($type->obligatoire ? ' — obligatoire' : '')])->all())->required()->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                    FileUpload::make('fichier')->disk('public')->directory('eleves/documents')->required(),
                    DatePicker::make('date_ajout')->default(today())->required(),
                ])->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                    $data['ajoute_par'] = auth()->id();

                    return $data;
                })->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        $livewire = $table->getLivewire();

        $cards = $livewire instanceof Pages\ListEleves
            && method_exists($livewire, 'usesCardLayout')
            && $livewire->usesCardLayout();

        return $table
            ->columns($cards ? self::cardColumns() : self::tableColumns())

            ->contentGrid($cards ? [
                'default' => 1,
                'sm' => 2,
                'md' => 2,
                'lg' => 3,
            ] : null)

            ->filters([
                Tables\Filters\SelectFilter::make('statut')
                    ->options([
                        'prospect' => 'Prospect',
                        'candidat' => 'Candidat',
                        'admis' => 'Admis',
                        'inscrit' => 'Inscrit',
                        'actif' => 'Actif',
                        'suspendu' => 'Suspendu',
                        'retire' => 'Retiré',
                        'diplome' => 'Diplômé',
                        'archive' => 'Archivé',
                    ]),
                Tables\Filters\SelectFilter::make('sexe')
                    ->label('Sexe')
                    ->options(['M' => 'Masculin', 'F' => 'Féminin']),
            ])

            ->contentFooter(function ($livewire) {
                $query = $livewire->getFilteredTableQuery();

                $total = (clone $query)->count();

                $filles = (clone $query)
                    ->where('sexe', 'F')
                    ->count();

                $garcons = (clone $query)
                    ->where('sexe', 'M')
                    ->count();

                return view('scolarite::filament.tables.eleves-statistics', [
                    'total' => $total,
                    'filles' => $filles,
                    'garcons' => $garcons,
                ]);
            });
    }

    /** @return array<int, mixed> */
    private static function tableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('matricule_permanent')
                ->label('Matricule')
                ->placeholder('—')
                ->searchable(),

            Tables\Columns\TextColumn::make('nom')
                ->label('Nom')
                ->searchable()
                ->sortable(),

            Tables\Columns\TextColumn::make('prenom')
                ->label('Prénom')
                ->searchable()
                ->sortable(),

            Tables\Columns\TextColumn::make('sexe')
                ->label('Sexe'),

            Tables\Columns\TextColumn::make('statut')
                ->label('Statut')
                ->badge(),
            Tables\Columns\TextColumn::make('documents_manquants')->label('Documents')->state(fn (Eleve $record): string => $record->documents_obligatoires_manquants->isEmpty() ? 'Complet' : $record->documents_obligatoires_manquants->count().' manquant(s)')->badge()->color(fn (string $state): string => $state === 'Complet' ? 'success' : 'warning'),

            Tables\Columns\TextColumn::make('created_at')
                ->label('Créé le')
                ->dateTime('d/m/Y')
                ->sortable(),
        ];
    }

    /** @return array<int, mixed> */

    // Eleve :
    private static function cardColumns(): array
    {
        return [
            Stack::make([
                Tables\Columns\ViewColumn::make('carte_eleve')
                    ->view('scolarite::filament.tables.carte-eleve'),
            ]),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEleves::route('/'),
            'create' => Pages\CreateEleve::route('/create'),
            'edit' => Pages\EditEleve::route('/{record}/edit'),
        ];
    }
}
