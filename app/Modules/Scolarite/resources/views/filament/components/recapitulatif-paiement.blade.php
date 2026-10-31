@props([
    'simulation' => null,
])

@if($simulation)
<div class="space-y-4 rounded-xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-gray-900" data-testid="recapitulatif-paiement">
    {{-- En-tête : Montant minimum & Statut de validité --}}
    @if(!empty($simulation['depassement_total']) && $simulation['depassement_total'])
        <div class="rounded-lg border border-amber-300 bg-amber-50 p-3.5 text-amber-900 dark:border-amber-800 dark:bg-amber-950/50 dark:text-amber-200" data-testid="alerte-trop-percu">
            <div class="flex items-start gap-3">
                <x-filament::icon icon="heroicon-o-information-circle" class="mt-0.5 h-6 w-6 shrink-0 text-amber-600 dark:text-amber-400" />
                <div class="text-sm">
                    <p class="font-bold text-amber-950 dark:text-amber-100">
                        Surplus détecté — Monnaie à restituer : {{ number_format($simulation['trop_percu'], 0, ',', ' ') }} FCFA
                    </p>
                    <p class="mt-1 leading-5">
                        Le montant versé (<strong>{{ number_format($simulation['montant_verse'], 0, ',', ' ') }} FCFA</strong>) couvre 100% de la totalité annuelle due (<strong>{{ number_format($simulation['total_annuel'], 0, ',', ' ') }} FCFA</strong> : inscription, frais divers et l'intégralité des tranches de scolarité).
                    </p>
                    <p class="mt-1.5 text-xs font-medium text-amber-800 dark:text-amber-300">
                        💡 Veuillez restituer le surplus de <strong>{{ number_format($simulation['trop_percu'], 0, ',', ' ') }} FCFA</strong> en monnaie au parent ou ajuster le montant versé à <strong>{{ number_format($simulation['total_annuel'], 0, ',', ' ') }} FCFA</strong> pour solder l'année complète.
                    </p>
                </div>
            </div>
        </div>
    @elseif($simulation['est_suffisant'])
        <div class="flex flex-col gap-2 rounded-lg border border-emerald-200 bg-emerald-50/70 p-3 sm:flex-row sm:items-center sm:justify-between dark:border-emerald-800/60 dark:bg-emerald-950/30">
            <div class="flex items-center gap-2.5 text-emerald-800 dark:text-emerald-300">
                <x-filament::icon icon="heroicon-o-check-circle" class="h-6 w-6 shrink-0 text-emerald-600 dark:text-emerald-400" />
                <div>
                    <span class="text-sm font-semibold tracking-wide uppercase text-emerald-700 dark:text-emerald-300">Montant minimum requis :</span>
                    <span class="ml-1 text-base font-extrabold text-emerald-900 dark:text-emerald-100">
                        {{ number_format($simulation['montant_minimum'], 0, ',', ' ') }} FCFA
                    </span>
                    @if($simulation['has_divers'])
                        <span class="ml-1.5 text-xs text-emerald-700/80 dark:text-emerald-400/80">(Inscription + Frais divers sélectionnés)</span>
                    @else
                        <span class="ml-1.5 text-xs text-emerald-700/80 dark:text-emerald-400/80">(Frais d'inscription)</span>
                    @endif
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center rounded-md bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200">
                    Saisi : {{ number_format($simulation['montant_verse'], 0, ',', ' ') }} FCFA
                </span>
                @if($simulation['scolarite']['montant_affecte'] > 0)
                    <span class="inline-flex items-center rounded-md bg-blue-100 px-2.5 py-1 text-xs font-medium text-blue-800 dark:bg-blue-900/60 dark:text-blue-200">
                        +{{ number_format($simulation['scolarite']['montant_affecte'], 0, ',', ' ') }} FCFA scolarité
                    </span>
                @endif
            </div>
        </div>
    @else
        <div class="rounded-lg border border-rose-300 bg-rose-50 p-3.5 text-rose-900 dark:border-rose-800 dark:bg-rose-950/50 dark:text-rose-200">
            <div class="flex items-start gap-3">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="mt-0.5 h-6 w-6 shrink-0 text-rose-600 dark:text-rose-400" />
                <div class="text-sm">
                    <p class="font-bold text-rose-950 dark:text-rose-100">
                        Transaction impossible — Montant minimum requis : {{ number_format($simulation['montant_minimum'], 0, ',', ' ') }} FCFA
                    </p>
                    <p class="mt-1 leading-5">
                        @if($simulation['has_divers'])
                            Le montant minimum à payer est de <strong>{{ number_format($simulation['montant_minimum'], 0, ',', ' ') }} FCFA</strong>, comprenant l'inscription et les frais divers sélectionnés.
                        @else
                            Le montant minimum à payer est de <strong>{{ number_format($simulation['montant_minimum'], 0, ',', ' ') }} FCFA</strong>, correspondant aux frais d'inscription.
                        @endif
                        (Montant saisi : {{ number_format($simulation['montant_verse'], 0, ',', ' ') }} FCFA &bull; Manquant : <strong class="text-rose-700 dark:text-rose-300">{{ number_format($simulation['manquant'], 0, ',', ' ') }} FCFA</strong>).
                    </p>
                </div>
            </div>
        </div>
    @endif

    {{-- Tableau récapitulatif d'affectation ERP --}}
    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-800">
        <table class="w-full text-left text-xs">
            <thead class="bg-gray-50 uppercase text-gray-500 dark:bg-gray-800/60 dark:text-gray-400">
                <tr>
                    <th scope="col" class="px-3.5 py-2.5 font-semibold">Affectation</th>
                    <th scope="col" class="px-3.5 py-2.5 font-semibold text-right">Exigé / Prévu</th>
                    <th scope="col" class="px-3.5 py-2.5 font-semibold text-right">Montant Affecté</th>
                    <th scope="col" class="px-3.5 py-2.5 font-semibold text-center">Statut</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                {{-- Ligne 1 : Inscription (Priorité 1) --}}
                <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30">
                    <td class="px-3.5 py-2.5 font-medium text-gray-900 dark:text-white">
                        <span class="flex items-center gap-1.5">
                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-primary-100 text-[10px] font-bold text-primary-700 dark:bg-primary-900 dark:text-primary-300">1</span>
                            {{ $simulation['inscription']['label'] }}
                        </span>
                    </td>
                    <td class="px-3.5 py-2.5 text-right text-gray-600 dark:text-gray-300">
                        {{ number_format($simulation['inscription']['montant_exige'], 0, ',', ' ') }} FCFA
                    </td>
                    <td class="px-3.5 py-2.5 text-right font-semibold text-gray-900 dark:text-white">
                        {{ number_format($simulation['inscription']['montant_affecte'], 0, ',', ' ') }} FCFA
                    </td>
                    <td class="px-3.5 py-2.5 text-center">
                        @if($simulation['inscription']['statut'] === 'couvert')
                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-medium text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                🟢 Couvert
                            </span>
                        @elseif($simulation['inscription']['statut'] === 'partiel')
                            <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                🟡 Partiel
                            </span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-[11px] font-medium text-rose-800 dark:bg-rose-950 dark:text-rose-300">
                                🔴 Non couvert
                            </span>
                        @endif
                    </td>
                </tr>

                {{-- Ligne 2 : Frais divers sélectionnés (Priorité 2) --}}
                @if($simulation['has_divers'])
                    @foreach($simulation['frais_divers']['items'] as $item)
                        <tr class="bg-gray-50/20 hover:bg-gray-50/50 dark:hover:bg-gray-800/30">
                            <td class="px-3.5 py-2.5 font-medium text-gray-900 dark:text-white">
                                <span class="flex items-center gap-1.5 pl-2">
                                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-amber-100 text-[10px] font-bold text-amber-700 dark:bg-amber-900 dark:text-amber-300">2</span>
                                    {{ $item['nom'] }} <span class="text-[11px] text-gray-400">(Frais divers)</span>
                                </span>
                            </td>
                            <td class="px-3.5 py-2.5 text-right text-gray-600 dark:text-gray-300">
                                {{ number_format($item['montant'], 0, ',', ' ') }} FCFA
                            </td>
                            <td class="px-3.5 py-2.5 text-right font-semibold text-gray-900 dark:text-white">
                                {{ number_format($item['montant_affecte'], 0, ',', ' ') }} FCFA
                            </td>
                            <td class="px-3.5 py-2.5 text-center">
                                @if($item['statut'] === 'couvert')
                                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-medium text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                        🟢 Couvert
                                    </span>
                                @elseif($item['statut'] === 'partiel')
                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                        🟡 Partiel
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-[11px] font-medium text-rose-800 dark:bg-rose-950 dark:text-rose-300">
                                        🔴 Non couvert
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                @endif

                {{-- Lignes 3 : Tranches de scolarité (Priorité 3) --}}
                @if(!empty($simulation['scolarite']['tranches']))
                    @foreach($simulation['scolarite']['tranches'] as $tranche)
                        <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30">
                            <td class="px-3.5 py-2.5 text-gray-700 dark:text-gray-300">
                                <span class="flex items-center gap-1.5 pl-2">
                                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-blue-100 text-[10px] font-bold text-blue-700 dark:bg-blue-900 dark:text-blue-300">3</span>
                                    Scolarité — {{ $tranche['nom'] }}
                                </span>
                            </td>
                            <td class="px-3.5 py-2.5 text-right text-gray-600 dark:text-gray-400">
                                {{ number_format($tranche['montant_total'], 0, ',', ' ') }} FCFA
                            </td>
                            <td class="px-3.5 py-2.5 text-right font-medium text-gray-900 dark:text-white">
                                {{ number_format($tranche['montant_affecte'], 0, ',', ' ') }} FCFA
                            </td>
                            <td class="px-3.5 py-2.5 text-center">
                                @if($tranche['statut'] === 'couvert')
                                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-medium text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                        🟢 Couvert
                                    </span>
                                @elseif($tranche['statut'] === 'partiel')
                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                        🟡 Partiel
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                        ⚪ Non affecté
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                @elseif($simulation['scolarite']['montant_total'] > 0)
                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30">
                        <td class="px-3.5 py-2.5 text-gray-700 dark:text-gray-300">
                            <span class="flex items-center gap-1.5 pl-2">
                                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-blue-100 text-[10px] font-bold text-blue-700 dark:bg-blue-900 dark:text-blue-300">3</span>
                                Frais de scolarité
                            </span>
                        </td>
                        <td class="px-3.5 py-2.5 text-right text-gray-600 dark:text-gray-400">
                            {{ number_format($simulation['scolarite']['montant_total'], 0, ',', ' ') }} FCFA
                        </td>
                        <td class="px-3.5 py-2.5 text-right font-medium text-gray-900 dark:text-white">
                            {{ number_format($simulation['scolarite']['montant_affecte'], 0, ',', ' ') }} FCFA
                        </td>
                        <td class="px-3.5 py-2.5 text-center">
                            @if($simulation['scolarite']['statut'] === 'couvert')
                                <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-medium text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                    🟢 Couvert
                                </span>
                            @elseif($simulation['scolarite']['statut'] === 'partiel')
                                <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                    🟡 Partiel
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                    ⚪ Non affecté
                                </span>
                            @endif
                        </td>
                    </tr>
                @endif
            </tbody>
            <tfoot class="border-t-2 border-gray-200 bg-gray-50/70 font-bold dark:border-gray-700 dark:bg-gray-800/80">
                <tr>
                    <td class="px-3.5 py-2.5 text-gray-900 dark:text-white">Total versement saisi</td>
                    <td class="px-3.5 py-2.5 text-right text-xs font-semibold text-gray-500 dark:text-gray-400">
                        Min. {{ number_format($simulation['montant_minimum'], 0, ',', ' ') }} FCFA
                    </td>
                    <td class="px-3.5 py-2.5 text-right text-sm font-extrabold text-primary-700 dark:text-primary-400">
                        {{ number_format($simulation['total_affecte'], 0, ',', ' ') }} FCFA
                    </td>
                    <td class="px-3.5 py-2.5 text-center">
                        @if(!empty($simulation['depassement_total']) && $simulation['depassement_total'])
                            <span class="inline-flex items-center rounded-md bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-900 dark:text-amber-200">
                                Surplus
                            </span>
                        @elseif($simulation['est_suffisant'])
                            <span class="inline-flex items-center rounded-md bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200">
                                Valide
                            </span>
                        @else
                            <span class="inline-flex items-center rounded-md bg-rose-100 px-2.5 py-0.5 text-xs font-semibold text-rose-800 dark:bg-rose-900 dark:text-rose-200">
                                Bloquant
                            </span>
                        @endif
                    </td>
                </tr>
                @if(!empty($simulation['depassement_total']) && $simulation['depassement_total'])
                <tr class="border-t border-amber-200 bg-amber-50/70 text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200" data-testid="ligne-monnaie-restituer">
                    <td colspan="2" class="px-3.5 py-2 text-xs font-semibold">
                        Monnaie à restituer au parent (trop-perçu)
                    </td>
                    <td class="px-3.5 py-2 text-right text-sm font-black text-amber-700 dark:text-amber-300">
                        {{ number_format($simulation['trop_percu'], 0, ',', ' ') }} FCFA
                    </td>
                    <td class="px-3.5 py-2 text-center text-xs font-bold text-amber-800 dark:text-amber-300">
                        À rendre
                    </td>
                </tr>
                @endif
            </tfoot>
        </table>
    </div>
</div>
@endif
