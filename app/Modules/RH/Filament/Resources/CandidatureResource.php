<?php

declare(strict_types=1);

namespace App\Modules\RH\Filament\Resources;

use App\Modules\RH\Filament\Resources\CandidatureResource\Pages;
use App\Modules\RH\Models\Candidature;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class CandidatureResource extends Resource
{
    protected static ?string $model = Candidature::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';

    protected static string|\UnitEnum|null $navigationGroup = 'Ressources humaines';

    protected static ?string $modelLabel = 'Candidature';

    protected static ?string $pluralModelLabel = 'Recrutement';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('reference')->disabled()->dehydrated(false)->placeholder('Générée automatiquement'),
            TextInput::make('nom')->required()->maxLength(255),
            TextInput::make('prenom')->required()->maxLength(255),
            TextInput::make('email')->email()->maxLength(255),
            TextInput::make('telephone')->tel()->maxLength(30),
            TextInput::make('poste_souhaite')->label('Poste recherché')->required()->maxLength(255),
            FileUpload::make('cv_path')->label('CV')->directory('personnel/candidatures')->acceptedFileTypes(['application/pdf']),
            Select::make('statut')->options(self::statuts())->default('nouvelle')->required(),
            DateTimePicker::make('date_entretien')->label('Date de l’entretien'),
            Textarea::make('notes')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('reference')->searchable()->sortable(),
            TextColumn::make('nom_complet')->label('Candidat')->searchable(['nom', 'prenom'])->sortable(['nom']),
            TextColumn::make('poste_souhaite')->label('Poste')->searchable(),
            TextColumn::make('date_entretien')->label('Entretien')->dateTime('d/m/Y H:i')->sortable(),
            TextColumn::make('statut')->badge()->formatStateUsing(fn (string $state): string => self::statuts()[$state] ?? $state)
                ->color(fn (string $state): string => match ($state) {
                    'retenue', 'embauche' => 'success', 'rejetee' => 'danger',
                    'preselectionnee', 'entretien' => 'warning', default => 'gray',
                }),
        ])->actions([
            Action::make('preselectionner')->label('Présélectionner')->icon('heroicon-o-check')
                ->visible(fn (Candidature $record): bool => $record->statut === 'nouvelle')
                ->action(fn (Candidature $record) => self::changerStatut($record, 'preselectionnee')),
            Action::make('retenir')->label('Retenir')->color('success')
                ->visible(fn (Candidature $record): bool => in_array($record->statut, ['preselectionnee', 'entretien'], true))
                ->requiresConfirmation()
                ->action(fn (Candidature $record) => self::changerStatut($record, 'retenue')),
            Action::make('embaucher')->label('Embaucher')->icon('heroicon-o-user-plus')->color('success')
                ->visible(fn (Candidature $record): bool => $record->statut === 'retenue')
                ->url(fn (Candidature $record): string => EmployeResource::getUrl('create', ['candidature' => $record->id])),
            Action::make('rejeter')->label('Rejeter')->color('danger')->requiresConfirmation()
                ->visible(fn (Candidature $record): bool => ! in_array($record->statut, ['rejetee', 'embauche'], true))
                ->action(fn (Candidature $record) => self::changerStatut($record, 'rejetee')),
            EditAction::make(), DeleteAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCandidatures::route('/'),
            'create' => Pages\CreateCandidature::route('/create'),
            'edit' => Pages\EditCandidature::route('/{record}/edit'),
        ];
    }

    public static function statuts(): array
    {
        return ['nouvelle' => 'Nouvelle', 'preselectionnee' => 'Présélectionnée', 'entretien' => 'Entretien planifié', 'retenue' => 'Retenue', 'rejetee' => 'Rejetée', 'embauche' => 'Embauchée'];
    }

    private static function changerStatut(Candidature $record, string $statut): void
    {
        $record->update(['statut' => $statut, 'evaluee_par' => Auth::id()]);
        Notification::make()->success()->title('Candidature mise à jour')->send();
    }
}
