<?php

declare(strict_types=1);

namespace App\Modules\Socle\Services;

use App\Modules\Socle\Contracts\ParametrageServiceContract;
use App\Modules\Socle\Contracts\NumerotationServiceContract;
use App\Modules\Socle\Exceptions\TemplateNotificationInexistantException;
use App\Modules\Socle\Models\ConfigEtablissement;
use App\Modules\Socle\Models\JourFerie;
use App\Modules\Socle\Models\Niveau;
use App\Modules\Socle\Models\SeuilValidation;
use App\Modules\Socle\Models\TemplateNotification;
use App\Modules\Socle\Settings\SystemSettings;
use DateTimeInterface;

class ParametrageService implements ParametrageServiceContract
{
    public function __construct(
        private NumerotationServiceContract $numerotationService,
        private SystemSettings $systemSettings,
    ) {}

    public function getConfigEtablissement(): object
    {
        return ConfigEtablissement::get();
    }

    public function getTousLesNiveaux(): array
    {
        return Niveau::orderBy('ordre')->get()->toArray();
    }

    public function getNiveau(int $id): ?object
    {
        return Niveau::find($id);
    }

    public function getParametre(string $cle, mixed $defaut = null): mixed
    {
        return property_exists($this->systemSettings, $cle)
            ? $this->systemSettings->{$cle}
            : $defaut;
    }

    public function genererNumero(string $typeDocument, array $variables = []): string
    {
        return $this->numerotationService->next($typeDocument, $variables);
    }

    public function getTemplateNotification(string $canal, string $code, array $donnees): string
    {
        $template = TemplateNotification::where('canal', $canal)
            ->where('code', $code)
            ->where('actif', true)
            ->first();

        if (! $template) {
            throw new TemplateNotificationInexistantException($canal, $code);
        }

        $remplacements = [];
        foreach ($donnees as $cle => $valeur) {
            $remplacements["{{$cle}}"] = (string) $valeur;
        }

        return strtr($template->contenu, $remplacements);
    }

    public function getValidateurRequis(int $categorieDepenseId, float $montant): string
    {
        $seuil = SeuilValidation::where('categorie_depense_id', $categorieDepenseId)
            ->where('montant_min', '<=', $montant)
            ->where(function ($q) use ($montant) {
                $q->whereNull('montant_max')->orWhere('montant_max', '>=', $montant);
            })
            ->first();

        return $seuil?->role_validateur_requis ?? 'Fondateur';
    }

    public function estJourFerie(DateTimeInterface $date, ?int $niveauId = null): bool
    {
        $dateStr = $date->format('Y-m-d');

        return JourFerie::where(function ($q) use ($niveauId) {
            $q->whereNull('niveau_id');
            if ($niveauId) {
                $q->orWhere('niveau_id', $niveauId);
            }
        })
            ->whereDate('date', $dateStr)
            ->exists();
    }
}
