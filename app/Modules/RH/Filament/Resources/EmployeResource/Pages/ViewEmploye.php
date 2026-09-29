<?php

declare(strict_types=1);

namespace App\Modules\RH\Filament\Resources\EmployeResource\Pages;

use App\Modules\RH\Filament\Resources\EmployeResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewEmploye extends ViewRecord
{
    protected static string $resource = EmployeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->label('Modifier'),
            DeleteAction::make()->label('Supprimer'),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identité du membre du personnel')
                ->icon('heroicon-o-identification')
                ->schema([
                    Grid::make(3)->schema([
                        ImageEntry::make('photo')->label('Photo')->disk('public')->circular()->columnSpan(1),
                        Grid::make(2)->schema([
                            TextEntry::make('nom_complet')->label('Nom complet')->weight('bold'),
                            TextEntry::make('matricule')->label('Matricule'),
                            TextEntry::make('sexe')->label('Sexe')->formatStateUsing(fn (?string $state): string => $state === 'F' ? 'Féminin' : ($state === 'M' ? 'Masculin' : 'Non renseigné')),
                            TextEntry::make('date_naissance')->label('Date de naissance')->date('d/m/Y')->placeholder('Non renseignée'),
                        ])->columnSpan(2),
                    ]),
                ]),
            Section::make('Affectation et coordonnées')->schema([
                Grid::make(2)->schema([
                    TextEntry::make('poste')->label('Poste')->placeholder('Non renseigné'),
                    TextEntry::make('departement')->label('Département')->placeholder('Non renseigné'),
                    TextEntry::make('role.name')->label('Rôle')->placeholder('Non renseigné'),
                    TextEntry::make('posteAdministratif.nom')->label('Poste administratif')->placeholder('Non renseigné'),
                    TextEntry::make('telephone')->label('Téléphone')->placeholder('Non renseigné'),
                    TextEntry::make('email')->label('E-mail')->placeholder('Non renseigné'),
                    TextEntry::make('date_embauche')->label('Date d’embauche')->date('d/m/Y'),
                    TextEntry::make('statut')->label('Statut')->badge(),
                ]),
            ]),
        ]);
    }
}
