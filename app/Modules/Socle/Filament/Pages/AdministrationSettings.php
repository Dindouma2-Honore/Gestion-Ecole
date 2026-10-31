<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Pages;

use App\Filament\Support\ModuleAccess;
use App\Modules\RH\Filament\Resources\PosteAdministratifResource;
use App\Modules\Socle\Filament\Resources;
use Filament\Pages\Page;
use Filament\Resources\Resource;

class AdministrationSettings extends Page
{
    protected static ?string $slug = 'administration/parametres';

    protected static ?string $title = 'Paramètres';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'socle::filament.pages.administration-settings';

    public static function canAccess(): bool
    {
        return ModuleAccess::canAccessComponent(auth()->user(), static::class);
    }

    protected const CATEGORY = null;

    public static function sectionUrl(string $id): string
    {
        return match ($id) {
            'etablissement' => ManageEtablissementSettings::getUrl(),
            'organisation' => OrganisationSettings::getUrl(),
            'annees' => AcademicYearSettings::getUrl(),
            'calendrier' => CalendarSettings::getUrl(),
        };
    }

    public function displayedSections(): array
    {
        $sections = static::sections();
        if (static::CATEGORY !== null) {
            return array_values(array_filter($sections, fn (array $section): bool => $section['id'] === static::CATEGORY));
        }

        return array_map(fn (array $section): array => array_merge($section, [
            'links' => [['label' => 'Ouvrir', 'url' => static::sectionUrl($section['id'])]],
        ]), $sections);
    }

    public static function sections(): array
    {
        $sections = [
            ['etablissement', 'Établissement', 'heroicon-o-building-office-2', [
                ['Informations de l’établissement', ManageEtablissementSettings::class, []],
            ]],
            ['organisation', 'Organisation', 'heroicon-o-building-office', [
                ['Départements', null, []],
                ['Services', null, []],
                ['Fonctions', PosteAdministratifResource::class, []],
            ]],
            ['annees', 'Années scolaires & périodes', 'heroicon-o-calendar-days', [
                ['Années scolaires', Resources\AnneeScolaireResource::class, []],
                ['Périodes', Resources\PeriodeResource::class, []],
            ]],
            ['calendrier', 'Calendrier', 'heroicon-o-clock', [
                ['Jours ouvrables', ManageWorkingDays::class, []],
                ['Jours fériés', Resources\JourFerieResource::class, []],
                ['Horaires généraux', ManageGeneralHours::class, []],
            ]],

        ];

        $result = [];
        foreach ($sections as [$id, $label, $icon, $entries]) {
            $links = [];
            foreach ($entries as [$name, $component, $parameters]) {
                if ($component === null) {
                    if (auth()->user()?->hasRole('Fondateur')) {
                        $links[] = ['label' => $name, 'url' => null];
                    }

                    continue;
                }
                $allowed = is_subclass_of($component, Resource::class) ? $component::canViewAny() : $component::canAccess();
                if ($allowed && ModuleAccess::canAccessComponent(auth()->user(), $component)) {
                    $url = is_subclass_of($component, Resource::class)
                        ? $component::getUrl('index', $parameters)
                        : $component::getUrl($parameters);
                    $links[] = ['label' => $name, 'url' => $url];
                }
            }
            if ($links !== []) {
                $result[] = compact('id', 'label', 'icon', 'links');
            }
        }

        return $result;
    }
}
