<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Filament\Resources\EquipementResource\RelationManagers;

use App\Modules\Logistique\Contracts\EquipementServiceInterface;
use App\Modules\Logistique\Models\EquipementPhoto;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PhotosRelationManager extends RelationManager
{
    protected static string $relationship = 'photos';

    protected static ?string $title = 'Photos et documents visuels';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('type')
            ->columns([
                Tables\Columns\ImageColumn::make('url')->label('Aperçu')->circular(),
                Tables\Columns\TextColumn::make('type')->label('Type')->badge(),
                Tables\Columns\TextColumn::make('description')->label('Description')->placeholder('—'),
                Tables\Columns\IconColumn::make('principal')->label('Principale')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->label('Ajouté le')->dateTime()->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options([
                    'principale' => 'Principale',
                    'etat' => 'État',
                    'autre' => 'Autre',
                ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Ajouter une photo')
                    ->form([
                        FileUpload::make('fichier')
                            ->label('Fichier image')
                            ->image()
                            ->required(),
                        Select::make('type')
                            ->label('Type de photo')
                            ->options([
                                'principale' => 'Principale',
                                'etat' => 'État',
                                'autre' => 'Autre',
                            ])
                            ->default('principale')
                            ->required(),
                        Textarea::make('description')->label('Description'),
                        Toggle::make('principal')->label('Définir comme principale')->default(false),
                    ])
                    ->using(function (array $data, RelationManager $livewire): Model {
                        return app(EquipementServiceInterface::class)->ajouterPhoto(
                            $livewire->getOwnerRecord()->id,
                            $data['fichier'],
                            $data['type'] ?? 'principale',
                            $data['description'] ?? null,
                            (bool) ($data['principal'] ?? false)
                        );
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('principale')
                    ->label('Définir principale')
                    ->icon('heroicon-o-star')
                    ->visible(fn (EquipementPhoto $record) => ! $record->principal)
                    ->action(function (EquipementPhoto $record): void {
                        app(EquipementServiceInterface::class)->definirPhotoPrincipale($record->equipement_id, $record->id);
                    }),
                Tables\Actions\DeleteAction::make()
                    ->label('Retirer')
                    ->action(function (EquipementPhoto $record): void {
                        app(EquipementServiceInterface::class)->retirerPhoto($record->id);
                    }),
            ])
            ->defaultSort('principal', 'desc');
    }
}
