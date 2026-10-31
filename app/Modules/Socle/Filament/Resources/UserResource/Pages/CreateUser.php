<?php

namespace App\Modules\Socle\Filament\Resources\UserResource\Pages;

use App\Modules\Socle\Filament\Resources\UserResource;
use App\Modules\Socle\Services\StaffAccountService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(StaffAccountService::class)->create(array_merge($data, array_filter(['role' => $this->data['role'] ?? null])));
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn ($messages, $key) => ['data.'.$key => $messages])->all());
        }
    }

    protected function getCreatedNotification(): ?Notification
    {
        return static::creationNotification($this->record);
    }

    public static function creationNotification(Model $record): Notification
    {
        return Notification::make()
            ->success()
            ->title('Utilisateur créé avec succès')
            ->body("Le lien d'activation a été envoyé.");
    }

    protected function getRedirectUrl(): string
    {
        return UserResource::getUrl();
    }
}
