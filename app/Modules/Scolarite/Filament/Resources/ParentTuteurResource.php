<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources;

use App\Modules\Scolarite\Filament\Support\ScolariteActions;
use App\Modules\Scolarite\Filament\Resources\ParentTuteurResource\Pages;
use App\Modules\Scolarite\Models\ParentTuteur;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ParentTuteurResource extends Resource
{
    protected static ?string $model = ParentTuteur::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|UnitEnum|null $navigationGroup = 'Scolarité';

    protected static ?string $navigationLabel = 'Parents / Tuteurs';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'parent ou tuteur';

    protected static ?string $pluralModelLabel = 'parents & tuteurs';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nom')->label('Nom')->required()->maxLength(100),
                TextInput::make('prenom')->label('Prénom')->required()->maxLength(100),
                TextInput::make('profession')->label('Profession')->maxLength(150),
                TextInput::make('telephone')->label('Téléphone')->tel()->maxLength(30),
                TextInput::make('email')->label('E-mail')->email()->maxLength(150),
                Toggle::make('portail_actif')->label('Portail actif')->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        $livewire = $table->getLivewire();
        $cards = $livewire instanceof Pages\ListParentTuteurs
            && method_exists($livewire, 'usesCardLayout')
            && $livewire->usesCardLayout();

        ScolariteActions::configureTable($table, 'view');

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('eleves'))
            ->columns($cards ? self::cardColumns() : self::tableColumns())
            ->contentGrid($cards ? ['md' => 2, 'xl' => 3] : null)
            ->actions([
                \App\Filament\Support\AmbassadorsDesign::tableActionGroup([
                    ScolariteActions::view()
                        ->label('Voir')
                        ->icon('heroicon-o-eye')
                        ->color('gray')
                        ->tooltip('Consulter le parent et ses enfants'),
                    ScolariteActions::edit()
                        ->label('Modifier')
                        ->icon('heroicon-o-pencil-square')
                        ->color('primary')
                        ->tooltip('Modifier le parent / tuteur'),
                    ScolariteActions::delete()
                        ->label('Supprimer')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->tooltip('Supprimer le parent / tuteur'),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('scolarite::filament.pages.parent-details')
                    ->viewData(function (ParentTuteur $record): array {
                        return [
                            'record' => $record->loadMissing([
                                'eleves.inscriptions' => fn ($q) => $q->withoutGlobalScopes()->with(['classe', 'anneeScolaire'])->orderByDesc('created_at'),
                            ]),
                        ];
                    })
                    ->columnSpanFull(),
            ]);
    }

    private static function tableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('nom')->label('Nom')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('prenom')->label('Prénom')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('telephone')->label('Téléphone')->searchable(),
            Tables\Columns\TextColumn::make('email')->label('E-mail')->searchable(),
            Tables\Columns\TextColumn::make('eleves_count')->label('Enfants')->counts('eleves'),
            Tables\Columns\IconColumn::make('portail_actif')->label('Portail')->boolean(),
        ];
    }

    private static function cardColumns(): array
    {
        return [
            Stack::make([
                Tables\Columns\ViewColumn::make('carte_parent')
                    ->view('scolarite::filament.tables.carte-parent'),
            ]),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListParentTuteurs::route('/'),
            'create' => Pages\CreateParentTuteur::route('/create'),
            'view' => Pages\ViewParentTuteur::route('/{record}'),
            'edit' => Pages\EditParentTuteur::route('/{record}/edit'),
        ];
    }
}
