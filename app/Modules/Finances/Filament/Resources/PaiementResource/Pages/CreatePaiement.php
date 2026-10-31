<?php

declare(strict_types=1);

namespace App\Modules\Finances\Filament\Resources\PaiementResource\Pages;

use App\Modules\Finances\Contracts\PaiementServiceContract;
use App\Modules\Finances\Exceptions\EleveIntrouvableException;
use App\Modules\Finances\Exceptions\ModePaiementInvalideException;
use App\Modules\Finances\Exceptions\MontantPaiementInvalideException;
use App\Modules\Finances\Exceptions\ReferenceMobileMoneyRequiseException;
use App\Modules\Finances\Exceptions\SessionCaisseIntrouvableException;
use App\Modules\Finances\Filament\Resources\PaiementResource;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class CreatePaiement extends CreateRecord
{
    protected static string $resource = PaiementResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(PaiementServiceContract::class)->enregistrerPaiement(
                (int) $data['inscription_id'],
                (float) $data['montant'],
                $data['mode'],
                $data['reference_mobile_money'] ?? null,
            );
        } catch (SessionCaisseIntrouvableException $e) {
            Notification::make()
                ->title('Caisse non disponible')
                ->body("Impossible d'enregistrer le paiement : la session de caisse du jour est clôturée ou aucune session n'est ouverte.")
                ->danger()
                ->persistent()
                ->send();

            $this->halt();
        } catch (MontantPaiementInvalideException|ModePaiementInvalideException|ReferenceMobileMoneyRequiseException|EleveIntrouvableException|DomainException $e) {
            Notification::make()
                ->title('Paiement non autorisé')
                ->body($e->getMessage())
                ->danger()
                ->send();

            $this->halt();
        } catch (Throwable $e) {
            report($e);
            Notification::make()
                ->title('Erreur lors de l’enregistrement')
                ->body('Une erreur est survenue : '.$e->getMessage())
                ->danger()
                ->send();

            $this->halt();
        }
    }
}
