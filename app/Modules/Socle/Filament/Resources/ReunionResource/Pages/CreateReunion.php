<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources\ReunionResource\Pages;

use App\Filament\Support\NiveauScopeSelect;
use App\Models\User;
use App\Modules\Socle\Contracts\ReunionServiceContract;
use App\Modules\Socle\Filament\Resources\ReunionResource;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class CreateReunion extends CreateRecord
{
    protected static string $resource = ReunionResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('titre')
                    ->required(),
                Select::make('type')
                    ->options([
                        'conseil_classe' => 'Conseil de classe',
                        'pedagogique' => 'Pédagogique',
                        'administrative' => 'Administrative',
                        'parents' => 'Parents d\'élèves',
                        'discipline' => 'Discipline',
                    ])
                    ->required(),
                DateTimePicker::make('date_heure')
                    ->label('Date & Heure')
                    ->required(),
                TextInput::make('lieu')
                    ->placeholder('Ex: Salle du conseil'),
                NiveauScopeSelect::make(),
                Select::make('participant_ids')
                    ->label('Participants')
                    ->options(User::pluck('name', 'id'))
                    ->multiple()
                    ->searchable()
                    ->required(),
                Repeater::make('ordre_du_jour')
                    ->label('Ordre du jour')
                    ->schema([
                        TextInput::make('point')->label('Point')->required(),
                    ])
                    ->addActionLabel('Ajouter un point')
                    ->reorderable()
                    ->defaultItems(1),
            ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $participantIds = array_map('intval', $data['participant_ids'] ?? []);
        $ordreDuJour = collect($data['ordre_du_jour'] ?? [])
            ->pluck('point')
            ->filter(fn (?string $point): bool => filled($point))
            ->values()
            ->all();

        return app(ReunionServiceContract::class)->planifierReunion(
            Arr::except($data, ['participant_ids', 'ordre_du_jour']),
            $participantIds,
            $ordreDuJour,
        );
    }
}
