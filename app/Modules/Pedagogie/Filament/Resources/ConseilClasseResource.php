<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Resources;

use App\Filament\Support\RecordModal;
use App\Modules\Pedagogie\Filament\Resources\ConseilClasseResource\Pages;
use App\Modules\Pedagogie\Models\Bulletin;
use App\Modules\Pedagogie\Models\ConseilClasse;
use App\Modules\Scolarite\Contracts\ClasseServiceInterface;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class ConseilClasseResource extends Resource
{
    protected static ?string $model = ConseilClasse::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'conseil de classe';

    protected static ?string $pluralModelLabel = 'conseils de classe';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('classe_id')->label('Classe')->options(fn () => collect(app(ClasseServiceInterface::class)->getToutesLesClasses((int) app(AnneeScolaireServiceContract::class)->getAnneeCouranteId()))->pluck('nom', 'id'))->searchable()->required(),
            Select::make('periode_id')->label('Période')->options(fn () => collect(app(AnneeScolaireServiceContract::class)->getPeriodes((int) app(AnneeScolaireServiceContract::class)->getAnneeCouranteId()))->mapWithKeys(fn ($p) => [data_get($p, 'id') => data_get($p, 'libelle', data_get($p, 'nom'))]))->required(),
            DatePicker::make('date_conseil')->label('Date du conseil')->required(),
            Select::make('statut')->options(['planifie' => 'Planifié', 'tenu' => 'Tenu', 'valide' => 'Validé'])->default('planifie')->required(),
            TextInput::make('effectif')->numeric()->default(0)->required(),
            TextInput::make('moyenne_classe')->numeric(),
            TextInput::make('taux_reussite')->label('Taux de réussite (%)')->numeric(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return RecordModal::configure($table->columns([
            Tables\Columns\TextColumn::make('classe_id')->label('Classe')->formatStateUsing(fn ($state) => app(ClasseServiceInterface::class)->getClasse((int) $state)['nom'] ?? "#{$state}"),
            Tables\Columns\TextColumn::make('periode_id')->label('Période #'),
            Tables\Columns\TextColumn::make('date_conseil')->label('Date')->date('d/m/Y')->sortable(),
            Tables\Columns\TextColumn::make('effectif'),
            Tables\Columns\TextColumn::make('moyenne_classe')->label('Moyenne')->numeric(2),
            Tables\Columns\TextColumn::make('taux_reussite')->label('Réussite')->suffix(' %'),
            Tables\Columns\TextColumn::make('statut')->badge()->color(fn ($state) => match ($state) {
                'valide' => 'success','tenu' => 'info',default => 'warning'
            }),
        ])->actions([
            EditAction::make(),
            Action::make('valider')->label('Valider le conseil')->icon('heroicon-o-check-badge')->color('success')->visible(fn (ConseilClasse $record) => $record->statut === 'tenu')->requiresConfirmation()->action(function (ConseilClasse $record): void {
                DB::transaction(function () use ($record): void {
                    Bulletin::where('classe_id', $record->classe_id)->where('periode_id', $record->periode_id)->whereIn('statut', ['calcule', 'soumis'])->update(['statut' => 'valide', 'valide_par' => auth()->id(), 'valide_le' => now()]);
                    $record->update(['statut' => 'valide', 'valide_par' => auth()->id(), 'valide_le' => now()]);
                });
                Notification::make()->title('Conseil validé')->body('Les bulletins de la classe sont prêts à être publiés.')->success()->send();
            }),
        ])->defaultPaginationPageOption(5), static::class);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListConseilsClasse::route('/'), 'create' => Pages\CreateConseilClasse::route('/create'), 'edit' => Pages\EditConseilClasse::route('/{record}/edit')];
    }
}
