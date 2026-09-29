<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Resources;

use App\Modules\Communication\Filament\Resources\MessageParentResource\Pages;
use App\Modules\Communication\Models\MessageParent;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class MessageParentResource extends Resource
{
    protected static ?string $model = MessageParent::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string|UnitEnum|null $navigationGroup = 'Communication';

    protected static ?string $navigationLabel = 'Messages Parents';

    protected static ?string $modelLabel = 'message parent';

    protected static ?string $pluralModelLabel = 'messages parents';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->options([
                        'individuel' => 'Individuel',
                        'collectif' => 'Collectif',
                    ])
                    ->required()
                    ->live(),

                Select::make('parent_id')
                    ->label('Parent / Tuteur')
                    ->options(fn (): array => \Illuminate\Support\Facades\DB::table('parents_tuteurs')
                        ->get()
                        ->mapWithKeys(fn ($p) => [$p->id => trim("{$p->prenom} {$p->nom}")])
                        ->toArray()
                    )
                    ->searchable()
                    ->visible(fn ($get) => $get('type') === 'individuel')
                    ->required(fn ($get) => $get('type') === 'individuel'),

                TextInput::make('sujet')
                    ->required()
                    ->maxLength(255),

                Textarea::make('contenu')
                    ->required()
                    ->rows(5),

                Select::make('cible_type')
                    ->options([
                        'classe' => 'Classe',
                        'niveau' => 'Niveau',
                        'tous' => 'Tous les parents',
                    ])
                    ->visible(fn ($get) => $get('type') === 'collectif')
                    ->live(),

                Select::make('cible_id')
                    ->label('Cible spécifique')
                    ->options(function ($get): array {
                        if ($get('cible_type') === 'classe') {
                            return \Illuminate\Support\Facades\DB::table('classes')->pluck('nom', 'id')->toArray();
                        }
                        if ($get('cible_type') === 'niveau') {
                            return \Illuminate\Support\Facades\DB::table('niveaux')->pluck('nom', 'id')->toArray();
                        }

                        return [];
                    })
                    ->visible(fn ($get) => $get('type') === 'collectif' && in_array($get('cible_type'), ['classe', 'niveau'])),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('sujet')->searchable(),
                Tables\Columns\TextColumn::make('expediteur.name')->label('Expéditeur'),
                Tables\Columns\TextColumn::make('cible_type')->label('Cible'),
                Tables\Columns\TextColumn::make('created_at')->label('Envoyé le')->dateTime()->sortable(),
            ])
            ->actions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMessageParents::route('/'),
            'create' => Pages\CreateMessageParent::route('/create'),
            'view' => Pages\ViewMessageParent::route('/{record}'),
        ];
    }
}
