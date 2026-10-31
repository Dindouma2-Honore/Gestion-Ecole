<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources\UserResource\Pages;

use App\Filament\Support\AmbassadorsDesign;
use App\Modules\Socle\Filament\Resources\UserResource;
use App\Modules\Socle\Services\StaffAccountService;
use Closure;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    public function getDefaultActionSchemaResolver(Actions\Action $action): ?Closure
    {
        if ($action instanceof Actions\CreateAction) {
            return fn (Schema $schema): Schema => $this->form($schema->columns(2)->operation('create'));
        }

        if ($action instanceof Actions\EditAction) {
            return fn (Schema $schema): Schema => $this->form($schema->columns(2)->operation('edit'));
        }

        return parent::getDefaultActionSchemaResolver($action);
    }

    protected function getHeaderActions(): array
    {
        return [
            AmbassadorsDesign::goldenFormModal(Actions\CreateAction::make())
                ->label('Créer un utilisateur')
                ->modalHeading('Créer un compte utilisateur')
                ->createAnother(false)
                ->schema(fn (Schema $schema): Schema => UserResource::form($schema->columns(2)->operation('create')))
                ->using(function (array $data, Actions\CreateAction $action): Model {
                    try {
                        $role = $data['role'] ?? $action->getRawData()['role'] ?? $action->getFormData()['role'] ?? null;

                        return app(StaffAccountService::class)->create(array_merge($data, array_filter(['role' => $role])));
                    } catch (ValidationException $exception) {
                        $prefix = 'mountedActions.'.$action->getNestingIndex().'.data.';
                        $mapped = [];
                        foreach ($exception->errors() as $key => $messages) {
                            $mapped[$prefix.$key] = $messages;
                            $mapped['data.'.$key] = $messages;
                            $mapped[$key] = $messages;
                        }

                        throw ValidationException::withMessages($mapped);
                    }
                })
                ->after(function (ListUsers $livewire): void {
                    $livewire->resetTable();
                    $livewire->flushCachedTableRecords();
                })
                ->successNotification(fn (Model $record): Notification => CreateUser::creationNotification($record)),
        ];
    }
}
