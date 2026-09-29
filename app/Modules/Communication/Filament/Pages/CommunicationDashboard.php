<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Pages;

use App\Modules\Communication\Filament\Resources\AnnonceResource;
use App\Modules\Communication\Filament\Resources\MessageParentResource;
use App\Modules\Communication\Filament\Resources\NotificationEnvoyeeResource;
use App\Modules\Communication\Filament\Resources\RendezVousResource;
use App\Modules\Communication\Filament\Widgets\NotificationQueueWidget;
use Filament\Pages\Page;

class CommunicationDashboard extends Page
{
    protected static ?string $slug = 'communication';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string|\UnitEnum|null $navigationGroup = 'Communication';

    protected static ?string $navigationLabel = 'Tableau de bord';

    protected static ?string $title = 'Communication';

    protected string $view = 'filament.pages.module-placeholder';

    public function getModuleIcon(): string
    {
        return 'heroicon-o-chat-bubble-left-right';
    }

    public function getModuleDescription(): string
    {
        return 'Gérez les notifications multi-canal, la messagerie avec les parents, les rendez-vous et les annonces.';
    }

    public function getModuleLinks(): array
    {
        return [
            ['label' => 'Notifications envoyées', 'description' => "Historique et statut d'envoi multi-canal", 'icon' => 'heroicon-o-bell', 'url' => NotificationEnvoyeeResource::getUrl('index')],
            ['label' => 'Messages Parents', 'description' => 'Messagerie individuelle et collective avec les parents', 'icon' => 'heroicon-o-chat-bubble-left-right', 'url' => MessageParentResource::getUrl('index')],
            ['label' => 'Rendez-vous', 'description' => 'Demandes, confirmations et comptes rendus de rendez-vous', 'icon' => 'heroicon-o-calendar-days', 'url' => RendezVousResource::getUrl('index')],
            ['label' => 'Annonces', 'description' => 'Publication et diffusion des annonces générales ou ciblées', 'icon' => 'heroicon-o-megaphone', 'url' => AnnonceResource::getUrl('index')],
        ];
    }

    public function getHeaderWidgets(): array
    {
        return [
            NotificationQueueWidget::class,
        ];
    }
}
