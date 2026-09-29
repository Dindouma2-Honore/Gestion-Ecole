<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources;

use App\Models\User;
use App\Modules\Socle\Models\Document;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Actions\Action as TableAction;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'Module Complémentaire';

    protected static ?string $modelLabel = 'Document';

    protected static ?string $pluralModelLabel = 'Documents';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nom')
                ->label('Nom du document')
                ->required()
                ->maxLength(255),
            Select::make('categorie')
                ->label('Catégorie')
                ->options([
                    'Administratif' => 'Administratif',
                    'Pédagogique' => 'Pédagogique',
                    'Financier' => 'Financier',
                    'RH' => 'Ressources Humaines',
                    'Autre' => 'Autre',
                ])
                ->required(),
            FileUpload::make('fichier_path')
                ->label('Fichier')
                ->disk('public')
                ->directory('documents')
                ->visibility('public')
                ->required()
                ->preserveFilenames()
                ->maxSize(10240),
            Select::make('niveau_confidentialite')
                ->label('Niveau de confidentialité')
                ->options([
                    'public' => 'Public',
                    'interne' => 'Interne',
                    'restreint' => 'Restreint',
                ])
                ->default('interne')
                ->required(),
            DatePicker::make('date_expiration')
                ->label('Date d\'expiration'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nom')->searchable()->sortable(),
                TextColumn::make('categorie')->searchable(),
                TextColumn::make('creator.name')->label('Ajouté par')->searchable(),
                TextColumn::make('versions_count')->label('Versions')->counts('versions'),
                TextColumn::make('niveau_confidentialite')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'public' => 'success',
                        'interne' => 'info',
                        'restreint' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('date_expiration')->date(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('niveau_confidentialite')
                    ->label('Confidentialité')
                    ->options([
                        'public' => 'Public',
                        'interne' => 'Interne',
                        'restreint' => 'Restreint',
                    ]),
                SelectFilter::make('categorie')
                    ->options(fn (): array => Document::query()
                        ->distinct()
                        ->orderBy('categorie')
                        ->pluck('categorie', 'categorie')
                        ->all()),
            ])
            ->headerActions([
                TableAction::make('creer')
                    ->label('Créer un document')
                    ->icon('heroicon-o-plus')
                    ->url(fn (): string => static::getUrl('create')),
            ])
            ->recordActions([
                TableAction::make('telecharger_pdf')
                    ->label('Télécharger PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('danger')
                    ->action(function (Document $record) {
                        /** @var FilesystemAdapter $storage */
                        $storage = Storage::disk('public');

                        if (! empty($record->fichier_path) && str_ends_with(strtolower($record->fichier_path), '.pdf') && $storage->exists($record->fichier_path)) {
                            return $storage->download($record->fichier_path, str($record->nom)->slug().'.pdf');
                        }

                        $pdf = Pdf::loadView('socle::pdf.document-fiche', ['document' => $record]);

                        return response()->streamDownload(
                            fn () => print($pdf->output()),
                            str($record->nom)->slug().'.pdf',
                            ['Content-Type' => 'application/pdf']
                        );
                    }),
                TableAction::make('telecharger')
                    ->label('Télécharger original')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->url(fn (Document $record): string => Storage::url($record->fichier_path))
                    ->openUrlInNewTab()
                    ->visible(fn (Document $record): bool => ! empty($record->fichier_path)),
                DeleteAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        /** @var User|null $user */
        $user = Filament::auth()->user();

        if ($user?->hasAnyRole(['Fondateur', 'Directeur', 'Admin', 'Administrateur'])) {
            return $query;
        }

        return $query->whereIn('niveau_confidentialite', ['public', 'interne']);
    }

    public static function getPages(): array
    {
        return [
            'index' => DocumentResource\Pages\ListDocuments::route('/'),
            'create' => DocumentResource\Pages\CreateDocument::route('/create'),
        ];
    }
}
