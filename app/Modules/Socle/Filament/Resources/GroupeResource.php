<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources;

use App\Models\User;
use App\Modules\Socle\Contracts\GroupeServiceContract;
use App\Modules\Socle\Filament\Pages\ManageGroupePermissions;
use App\Modules\Socle\Models\Groupe;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class GroupeResource extends Resource
{
    protected static ?string $model = Groupe::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|\UnitEnum|null $navigationGroup = 'Utilisateurs & accès';

    protected static ?string $modelLabel = 'Groupe';

    protected static ?string $pluralModelLabel = 'Groupes';

    public static function canViewAny(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->hasRole('Fondateur') === true;
    }

    public static function canCreate(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->hasRole('Fondateur') === true;
    }

    public static function canEdit($record): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->hasRole('Fondateur') === true;
    }

    public static function canDelete($record): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->hasRole('Fondateur') === true;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nom')
                    ->label('Nom du groupe')
                    ->required()
                    ->unique(ignoreRecord: true),
                Textarea::make('description')
                    ->label('Description')
                    ->rows(3),
                Select::make('membres')
                    ->label('Membres')
                    ->relationship('membres', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->saveRelationshipsUsing(function (Groupe $record, array $state): void {
                        app(GroupeServiceContract::class)->synchroniserMembres($record, $state);
                    }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nom')->label('Nom')->searchable()->sortable(),
                TextColumn::make('membres.name')
                    ->label('Membres')
                    ->badge()
                    ->listWithLineBreaks()
                    ->limitList(5)
                    ->expandableLimitedList(),
                TextColumn::make('habilitations_resume')
                    ->label('Habilitations accordées')
                    ->state(fn (Groupe $record): array => $record->fonctionnalites()
                        ->wherePivot('actif', true)
                        ->orderBy('categorie')
                        ->orderBy('ordre')
                        ->get()
                        ->map(fn ($fonctionnalite): string => "{$fonctionnalite->categorie} — {$fonctionnalite->nom}")
                        ->all())
                    ->badge()
                    ->listWithLineBreaks()
                    ->limitList(6)
                    ->expandableLimitedList()
                    ->placeholder('Aucune habilitation'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->recordActions([
                Action::make('permissions')
                    ->label('Gérer les permissions')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->url(fn (Groupe $record): string => ManageGroupePermissions::getUrl(['groupe' => $record->id])),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => GroupeResource\Pages\ListGroupes::route('/'),
            'create' => GroupeResource\Pages\CreateGroupe::route('/create'),
            'edit' => GroupeResource\Pages\EditGroupe::route('/{record}/edit'),
        ];
    }
}
