<?php

namespace App\Providers;

use App\Filament\Auth\LoginResponse;
use App\Models\User;
use App\Modules\Communication\Models\Annonce;
use App\Modules\Communication\Models\MessageParent;
use App\Modules\Communication\Models\RendezVous;
use App\Modules\RH\Models\Conge;
use App\Modules\RH\Models\Employe;
use App\Modules\RH\Models\Enseignant;
use App\Modules\RH\Models\Inspection;
use App\Modules\RH\Models\Pointage;
use App\Modules\RH\Models\Prime;
use App\Modules\RH\Models\SanctionPersonnel;
use App\Modules\Socle\Models\Tache;
use App\Modules\Socle\Models\TacheValidation;
use App\Observers\AnnonceObserver;
use App\Observers\CongeObserver;
use App\Observers\EmployeObserver;
use App\Observers\EnseignantObserver;
use App\Observers\InspectionObserver;
use App\Observers\MessageParentObserver;
use App\Observers\PointageObserver;
use App\Observers\PrimeObserver;
use App\Observers\RendezVousObserver;
use App\Observers\SanctionPersonnelObserver;
use App\Observers\TacheObserver;
use App\Observers\TacheValidationObserver;
use App\Observers\UserObserver;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Notifications\Notification;
use Filament\Tables\Table;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LoginResponseContract::class, LoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Notification::configureUsing(fn (Notification $notification): Notification => $notification
            ->duration(fn (Notification $notification): int|string => match ($notification->getStatus()) {
                'success' => 4000,
                'info' => 5000,
                'warning' => 6000,
                'danger' => 8000,
                default => 5000,
            }));

        ActionGroup::configureUsing(fn (ActionGroup $group): ActionGroup => $group
            ->label('Actions')
            ->icon('heroicon-m-ellipsis-vertical')
            ->iconButton()
            ->color('gray')
            ->tooltip('Actions'));

        Table::configureUsing(function (Table $table): void {
            $table
                ->recordActionsColumnLabel('Actions')
                ->recordActionsAlignment('center')
                ->paginationPageOptions([5, 10, 25, 50])
                ->defaultPaginationPageOption(5)
                ->modifyUngroupedRecordActionsUsing(function (Action $action): void {
                    match (true) {
                        $action instanceof ViewAction => $action->label('Voir')->color('gray'),
                        $action instanceof EditAction => $action->label('Modifier')->color('primary'),
                        $action instanceof DeleteAction => $action->label('Supprimer')->color('danger'),
                        default => null,
                    };
                });
        });

        User::observe(UserObserver::class);
        Event::listen(PasswordReset::class, function (PasswordReset $event): void {
            if ($event->user instanceof User) {
                $event->user->forceFill(['must_change_password' => false])->save();
            }
        });
        SanctionPersonnel::observe(SanctionPersonnelObserver::class);
        Employe::observe(EmployeObserver::class);
        Enseignant::observe(EnseignantObserver::class);
        Inspection::observe(InspectionObserver::class);
        Pointage::observe(PointageObserver::class);
        Prime::observe(PrimeObserver::class);
        Conge::observe(CongeObserver::class);
        Annonce::observe(AnnonceObserver::class);
        MessageParent::observe(MessageParentObserver::class);
        RendezVous::observe(RendezVousObserver::class);
        Tache::observe(TacheObserver::class);
        TacheValidation::observe(TacheValidationObserver::class);
    }
}
