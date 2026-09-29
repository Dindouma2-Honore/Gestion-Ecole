<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Resources;

use App\Modules\Pedagogie\Contracts\NoteServiceInterface;
use App\Modules\Pedagogie\Filament\Resources\NoteResource\Pages;
use App\Modules\Pedagogie\Models\Evaluation;
use App\Modules\Pedagogie\Models\Note;
use App\Modules\Scolarite\Contracts\EleveServiceInterface;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use OpenSpout\Reader\Common\Creator\ReaderFactory;
use UnitEnum;

class NoteResource extends Resource
{
    protected static ?string $model = Note::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-pencil-square';

    protected static string|UnitEnum|null $navigationGroup = 'Pédagogie';

    protected static ?string $navigationLabel = 'Notes';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('evaluation_id')->label('Évaluation')->relationship('evaluation', 'titre')->searchable()->preload()->required()
                ->live()
                ->disabled(fn (?Note $record): bool => $record !== null),
            Select::make('eleve_id')->label('Élève')
                ->options(function (Get $get) {
                    $evaluation = Evaluation::find($get('evaluation_id'));

                    if ($evaluation === null) {
                        return [];
                    }

                    return collect(app(EleveServiceInterface::class)->getElevesParClasse($evaluation->classe_id))
                        ->mapWithKeys(fn ($e) => [$e['id'] => "{$e['nom']} {$e['prenom']}"]);
                })
                ->searchable()
                ->required()
                ->disabled(fn (?Note $record): bool => $record !== null),
            TextInput::make('valeur')->label('Note')->numeric()->required()
                ->hidden(fn (Get $get): bool => (bool) $get('absent'))
                ->disabled(fn (?Note $record): bool => $record !== null && ! app(NoteServiceInterface::class)->peutModifier($record->id))
                ->helperText(fn (?Note $record): ?string => $record ? 'Verrouillage prévu le '.app(NoteServiceInterface::class)->dateVerrouillage($record->id)->format('d/m/Y H:i') : null),
            Toggle::make('absent')->label('Élève absent')->live()->helperText("Une absence n'entre pas dans le calcul de la moyenne."),

            // Scan/photo de la copie de l'élève, attachée à la note via
            // Socle\Contracts\DocumentServiceContract.
            FileUpload::make('copie')
                ->label("Copie de l'élève (scan/photo, optionnel)")
                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                ->maxSize(10240)
                ->storeFiles(false)
                ->downloadable()
                ->openable()
                ->disabled(fn (?Note $record): bool => $record !== null && ! app(NoteServiceInterface::class)->peutModifier($record->id)),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('evaluation.titre')->label('Évaluation')->searchable(),
            Tables\Columns\TextColumn::make('eleve_id')->label('Élève')
                ->formatStateUsing(function ($state) {
                    $eleve = app(EleveServiceInterface::class)->getEleve($state);

                    return "{$eleve['nom']} {$eleve['prenom']}";
                }),
            Tables\Columns\TextColumn::make('valeur')->label('Note'),
            Tables\Columns\IconColumn::make('absent')->label('Absent')->boolean(),
            Tables\Columns\IconColumn::make('document_copie_id')->label('Copie jointe')->boolean()->getStateUsing(fn (Note $record) => $record->document_copie_id !== null),
            Tables\Columns\TextColumn::make('premiere_saisie_at')->label('Première saisie')->dateTime(),
        ])->headerActions([
            Action::make('importer')
                ->label('Importer des notes Excel / CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->schema([
                    Select::make('evaluation_id')->label('Évaluation')->relationship('evaluation', 'titre')->searchable()->preload()->required(),
                    FileUpload::make('fichier')->label('Fichier Excel (.xlsx) ou CSV')->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'text/csv',
                    ])->storeFiles(false)->required(),
                ])
                ->action(function (array $data): void {
                    $fichier = $data['fichier'];
                    $erreurs = [];
                    $importees = 0;

                    try {
                        $reader = ReaderFactory::createFromFileByMimeType($fichier->getRealPath());
                        $reader->open($fichier->getRealPath());
                        $numero = 0;
                        foreach ($reader->getSheetIterator() as $sheet) {
                            foreach ($sheet->getRowIterator() as $row) {
                                $numero++;
                                if ($numero === 1) {
                                    continue;
                                }
                                $ligne = array_map(fn ($cell) => $cell->getValue(), $row->getCells());
                                try {
                                    $eleveId = filter_var($ligne[0] ?? null, FILTER_VALIDATE_INT);
                                    $absent = in_array(mb_strtolower(trim((string) ($ligne[2] ?? ''))), ['1', 'oui', 'yes', 'absent'], true);
                                    if (! $eleveId) {
                                        throw new \InvalidArgumentException("identifiant d'élève invalide");
                                    }
                                    if ($absent) {
                                        app(NoteServiceInterface::class)->enregistrerAbsence((int) $data['evaluation_id'], (int) $eleveId);
                                    } else {
                                        if (! is_numeric($ligne[1] ?? null)) {
                                            throw new \InvalidArgumentException('note manquante ou invalide');
                                        }
                                        app(NoteServiceInterface::class)->enregistrer((int) $data['evaluation_id'], (int) $eleveId, (float) $ligne[1]);
                                    }
                                    $importees++;
                                } catch (\Throwable $e) {
                                    $erreurs[] = "Ligne {$numero} : {$e->getMessage()}";
                                }
                            }
                            break;
                        }
                        $reader->close();
                    } catch (\Throwable $e) {
                        $erreurs[] = 'Lecture du fichier impossible : '.$e->getMessage();
                    }

                    $notification = Notification::make()->title("{$importees} note(s) importée(s)");
                    if ($erreurs !== []) {
                        $notification->body(implode("\n", array_slice($erreurs, 0, 10)))->warning()->persistent();
                    } else {
                        $notification->success();
                    }
                    $notification->send();
                }),
        ])->recordActions([
            Action::make('debloquer')->label('Autoriser une correction')->icon('heroicon-o-lock-open')
                ->visible(fn (Note $record): bool => auth()->user()?->hasRole('Fondateur') === true && ! app(NoteServiceInterface::class)->peutModifier($record->id))
                ->schema([Textarea::make('motif')->required()->minLength(3)])
                ->action(function (Note $record, array $data): void {
                    app(NoteServiceInterface::class)->debloquer($record->id, $data['motif']);
                    Notification::make()->title('Une correction unique est autorisée.')->success()->send();
                }),
            EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListNotes::route('/'), 'create' => Pages\CreateNote::route('/create'), 'edit' => Pages\EditNote::route('/{record}/edit')];
    }
}
