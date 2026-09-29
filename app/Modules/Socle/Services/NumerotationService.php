<?php

declare(strict_types=1);

namespace App\Modules\Socle\Services;

use App\Modules\Socle\Contracts\NumerotationServiceContract;
use App\Modules\Socle\Exceptions\FormatNumerotationInexistantException;
use App\Modules\Socle\Models\AnneeScolaire;
use App\Modules\Socle\Models\FormatNumerotation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NumerotationService implements NumerotationServiceContract
{
    /**
     * Génère le prochain numéro pour un type de document donné avec verrouillage pessimiste.
     *
     * @throws FormatNumerotationInexistantException
     */
    public function genererNumero(string $typeDocument, array $variables = []): string
    {
        return $this->next($typeDocument, $variables);
    }

    public function next(string $documentType, array $variables = []): string
    {
        return DB::transaction(function () use ($documentType, $variables): string {
            $format = FormatNumerotation::where('type_document', $documentType)
                ->lockForUpdate()
                ->first();

            if (! $format) {
                throw new FormatNumerotationInexistantException($documentType);
            }

            $this->validerPattern($format->format);
            $variables = $this->completerVariables($variables);
            $cleCompteur = $this->cleCompteur($format->reinitialisation, $variables);

            if ($format->derniere_cle_compteur === null) {
                $format->derniere_cle_compteur = $cleCompteur;
            } elseif ($format->derniere_cle_compteur !== $cleCompteur) {
                $format->prochain_numero = 1;
                $format->derniere_cle_compteur = $cleCompteur;
            }

            $numero = $this->construireNumero($format->format, (int) $format->prochain_numero, $variables);
            $format->prochain_numero++;
            $format->save();

            return $numero;
        });
    }

    private function construireNumero(string $format, int $seq, array $variables): string
    {
        $numero = preg_replace_callback(
            '/\{\{?SEQ:(\d{1,2})\}\}?/i',
            fn (array $matches): string => str_pad((string) $seq, (int) $matches[1], '0', STR_PAD_LEFT),
            $format,
        );

        $tokens = [];
        foreach ($variables as $cle => $valeur) {
            // Le format documenté utilise une accolade. L'ancien format à deux
            // accolades reste accepté afin de ne pas invalider les formats existants.
            $tokens['{'.$cle.'}'] = $valeur;
            $tokens['{{'.$cle.'}}'] = $valeur;
        }

        return strtr((string) $numero, $tokens);
    }

    private function validerPattern(string $pattern): void
    {
        if (! preg_match('/\{\{?SEQ:\d{1,2}\}\}?/i', $pattern)) {
            throw ValidationException::withMessages([
                'format' => 'Le pattern doit contenir un compteur {SEQ:n}, par exemple {SEQ:5}.',
            ]);
        }
    }

    private function completerVariables(array $variables): array
    {
        $normalisees = [];
        foreach ($variables as $cle => $valeur) {
            $normalisees[strtoupper((string) $cle)] = (string) $valeur;
            $normalisees[strtolower((string) $cle)] = (string) $valeur;
        }

        $annee = $normalisees['ANNEE_SCOLAIRE']
            ?? $normalisees['ANNEE']
            ?? AnneeScolaire::query()->where('statut', 'active')->value('libelle')
            ?? (string) now()->year;

        $normalisees['ANNEE_SCOLAIRE'] = $annee;
        $normalisees['annee_scolaire'] = $annee;
        $normalisees['ANNEE'] = $annee;
        $normalisees['annee'] = $annee;
        $normalisees['MOIS'] ??= now()->format('Y-m');
        $normalisees['mois'] ??= $normalisees['MOIS'];

        return $normalisees;
    }

    private function cleCompteur(string $reinitialisation, array $variables): string
    {
        return match ($reinitialisation) {
            'annee_scolaire' => 'annee:'.$variables['ANNEE_SCOLAIRE'],
            'mois' => 'mois:'.$variables['MOIS'],
            default => 'jamais',
        };
    }
}
