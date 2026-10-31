@php
    $argent = static fn ($montant): string => number_format((float) ($montant ?? 0), 0, ',', ' ').' FCFA';
    $date = static fn ($valeur): string => $valeur ? \Illuminate\Support\Carbon::parse($valeur)->format('d/m/Y') : '—';

    $ancienne = $simulation['ancienne_classe'] ?? [];
    $nouvelle = $simulation['nouvelle_classe'] ?? [];
    $annexes = $simulation['frais_annexes'] ?? [];
    $totalPaye = (float) ($simulation['total_paye'] ?? 0);
    $diff = (float) ($simulation['difference'] ?? 0);
    $tranches = $simulation['tranches'] ?? [];
@endphp

<div class="space-y-4 text-sm">
    {{-- BANDEAU RÉCAPITULATIF DE L'IMPACT FINANCIER --}}
    <div class="rounded-xl border p-4 shadow-sm transition-all {{ $diff > 0 ? 'border-amber-300 bg-amber-50/80 dark:border-amber-700/60 dark:bg-amber-950/30' : ($diff < 0 ? 'border-emerald-300 bg-emerald-50/80 dark:border-emerald-700/60 dark:bg-emerald-950/30' : 'border-sky-300 bg-sky-50/80 dark:border-sky-700/60 dark:bg-sky-950/30') }}">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                    Aperçu du recalcul avant validation
                </p>
                <div class="flex items-center gap-2 mt-0.5">
                    <span class="font-bold text-gray-900 dark:text-white">
                        {{ $ancienne['nom'] ?? 'Classe d’origine' }}
                    </span>
                    <span class="text-gray-400">➔</span>
                    <span class="font-bold text-primary-600 dark:text-primary-400">
                        {{ $nouvelle['nom'] ?? 'Nouvelle classe' }}
                    </span>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if($diff > 0)
                    <span class="inline-flex items-center gap-1 rounded-full border border-amber-400 bg-amber-100 px-3 py-1 text-xs font-bold text-amber-900 dark:border-amber-600 dark:bg-amber-900/60 dark:text-amber-200">
                        🔺 Majoration : +{{ $argent($diff) }}
                    </span>
                @elseif($diff < 0)
                    <span class="inline-flex items-center gap-1 rounded-full border border-emerald-400 bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-900 dark:border-emerald-600 dark:bg-emerald-900/60 dark:text-emerald-200">
                        🔻 Réduction : {{ $argent($diff) }}
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 rounded-full border border-sky-400 bg-sky-100 px-3 py-1 text-xs font-bold text-sky-900 dark:border-sky-600 dark:bg-sky-900/60 dark:text-sky-200">
                        ⚖️ Scolarité identique
                    </span>
                @endif

                <span class="inline-flex items-center rounded-full border border-gray-300 bg-white px-3 py-1 text-xs font-semibold text-gray-700 shadow-xs dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                    Déjà perçu : <strong>&nbsp;{{ $argent($totalPaye) }}</strong>
                </span>
            </div>
        </div>
    </div>

    {{-- TABLEAU COMPARATIF DÉTAILLÉ --}}
    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-gray-900">
        <table class="w-full text-left border-collapse text-xs sm:text-sm">
            <thead>
                <tr class="border-b border-gray-200 bg-gray-50/80 text-gray-600 dark:border-gray-800 dark:bg-gray-800/60 dark:text-gray-300">
                    <th class="py-2.5 px-3 font-semibold">Rubrique</th>
                    <th class="py-2.5 px-3 font-semibold text-right">Origine ({{ $ancienne['nom'] ?? '—' }})</th>
                    <th class="py-2.5 px-3 font-semibold text-right text-primary-700 dark:text-primary-300">Destination ({{ $nouvelle['nom'] ?? '—' }})</th>
                    <th class="py-2.5 px-3 font-semibold text-right">Différence</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                <tr>
                    <td class="py-2 px-3 text-gray-700 dark:text-gray-300">Frais d'inscription</td>
                    <td class="py-2 px-3 text-right text-gray-600 dark:text-gray-400">{{ $argent($annexes['inscription_montant'] ?? 0) }}</td>
                    <td class="py-2 px-3 text-right font-medium text-gray-900 dark:text-gray-100">{{ $argent($annexes['inscription_montant'] ?? 0) }}</td>
                    <td class="py-2 px-3 text-right text-gray-400">0 FCFA</td>
                </tr>

                @if(($annexes['divers_montant'] ?? 0) > 0)
                    <tr>
                        <td class="py-2 px-3 text-gray-700 dark:text-gray-300">Frais divers souscrits</td>
                        <td class="py-2 px-3 text-right text-gray-600 dark:text-gray-400">{{ $argent($annexes['divers_montant'] ?? 0) }}</td>
                        <td class="py-2 px-3 text-right font-medium text-gray-900 dark:text-gray-100">{{ $argent($annexes['divers_montant'] ?? 0) }}</td>
                        <td class="py-2 px-3 text-right text-gray-400">0 FCFA</td>
                    </tr>
                @endif

                <tr class="bg-gray-50/50 dark:bg-gray-800/40">
                    <td class="py-2 px-3 font-medium text-gray-800 dark:text-gray-200">Scolarité de la classe</td>
                    <td class="py-2 px-3 text-right text-gray-700 dark:text-gray-300">{{ $argent($ancienne['scolarite'] ?? 0) }}</td>
                    <td class="py-2 px-3 text-right font-bold text-primary-700 dark:text-primary-300">{{ $argent($nouvelle['scolarite'] ?? 0) }}</td>
                    <td class="py-2 px-3 text-right font-semibold {{ $diff > 0 ? 'text-amber-600' : ($diff < 0 ? 'text-emerald-600' : 'text-gray-500') }}">
                        {{ $diff > 0 ? '+'.$argent($diff) : ($diff < 0 ? $argent($diff) : '0 FCFA') }}
                    </td>
                </tr>

                <tr class="font-bold border-t border-gray-200 dark:border-gray-700 bg-gray-100/60 dark:bg-gray-800/60">
                    <td class="py-2.5 px-3 text-gray-900 dark:text-white">TOTAL ANNUEL EXIGÉ</td>
                    <td class="py-2.5 px-3 text-right text-gray-800 dark:text-gray-200">{{ $argent($ancienne['total_annuel'] ?? 0) }}</td>
                    <td class="py-2.5 px-3 text-right text-primary-700 dark:text-primary-300">{{ $argent($nouvelle['total_annuel'] ?? 0) }}</td>
                    <td class="py-2.5 px-3 text-right {{ $diff > 0 ? 'text-amber-600' : ($diff < 0 ? 'text-emerald-600' : 'text-gray-500') }}">
                        {{ $diff > 0 ? '+'.$argent($diff) : ($diff < 0 ? $argent($diff) : '0 FCFA') }}
                    </td>
                </tr>

                <tr class="border-t border-gray-200 dark:border-gray-700">
                    <td class="py-2 px-3 text-gray-700 dark:text-gray-300">Total versements effectués</td>
                    <td class="py-2 px-3 text-right text-emerald-600 font-semibold">{{ $argent($totalPaye) }}</td>
                    <td class="py-2 px-3 text-right text-emerald-600 font-semibold">{{ $argent($totalPaye) }}</td>
                    <td class="py-2 px-3 text-right text-gray-400">Conservé</td>
                </tr>

                <tr class="font-extrabold text-sm border-t-2 border-primary-500 bg-primary-50/50 dark:bg-primary-950/30">
                    <td class="py-3 px-3 text-gray-900 dark:text-white">
                        SOLDE RESTANT À PAYER
                    </td>
                    <td class="py-3 px-3 text-right text-gray-700 dark:text-gray-300">
                        {{ $argent($ancienne['solde_restant'] ?? 0) }}
                    </td>
                    <td class="py-3 px-3 text-right text-primary-700 dark:text-primary-300">
                        {{ $argent($nouvelle['solde_restant'] ?? 0) }}
                    </td>
                    <td class="py-3 px-3 text-right {{ (($nouvelle['solde_restant'] ?? 0) > ($ancienne['solde_restant'] ?? 0)) ? 'text-amber-600' : 'text-emerald-600' }}">
                        @php
                            $diffSolde = (float) ($nouvelle['solde_restant'] ?? 0) - (float) ($ancienne['solde_restant'] ?? 0);
                        @endphp
                        {{ $diffSolde > 0 ? '+'.$argent($diffSolde) : ($diffSolde < 0 ? $argent($diffSolde) : '0 FCFA') }}
                    </td>
                </tr>

                @if(($nouvelle['surplus_remboursable'] ?? 0) > 0)
                    <tr class="bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-200 font-bold">
                        <td class="py-2 px-3">Surplus / Avoir en faveur du parent</td>
                        <td class="py-2 px-3 text-right">—</td>
                        <td class="py-2 px-3 text-right">{{ $argent($nouvelle['surplus_remboursable'] ?? 0) }}</td>
                        <td class="py-2 px-3 text-right">+{{ $argent($nouvelle['surplus_remboursable'] ?? 0) }}</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>

    {{-- PROJECTION DU CALENDRIER DES TRANCHES DANS LA NOUVELLE CLASSE --}}
    @if(!empty($tranches))
        <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-xs dark:border-gray-800 dark:bg-gray-900">
            <p class="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-2">
                Projection des tranches de scolarité (Nouvelle classe)
            </p>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50/50 text-gray-500 dark:border-gray-800 dark:bg-gray-800/40 dark:text-gray-400">
                            <th class="py-1.5 px-2 font-semibold">Tranche</th>
                            <th class="py-1.5 px-2 font-semibold">Échéance</th>
                            <th class="py-1.5 px-2 font-semibold text-right">Montant exigé</th>
                            <th class="py-1.5 px-2 font-semibold text-right">Réalloué (Payé)</th>
                            <th class="py-1.5 px-2 font-semibold text-right">Reste</th>
                            <th class="py-1.5 px-2 font-semibold text-center">Statut</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($tranches as $tranche)
                            <tr>
                                <td class="py-1.5 px-2 font-medium text-gray-800 dark:text-gray-200">{{ $tranche['libelle'] }}</td>
                                <td class="py-1.5 px-2 text-gray-500">{{ $date($tranche['date_echeance']) }}</td>
                                <td class="py-1.5 px-2 text-right font-medium">{{ $argent($tranche['montant']) }}</td>
                                <td class="py-1.5 px-2 text-right text-emerald-600 font-semibold">{{ $argent($tranche['paye']) }}</td>
                                <td class="py-1.5 px-2 text-right font-semibold {{ ($tranche['reste'] ?? 0) > 0 ? 'text-amber-600' : 'text-gray-400' }}">
                                    {{ $argent($tranche['reste']) }}
                                </td>
                                <td class="py-1.5 px-2 text-center">
                                    @if(($tranche['statut'] ?? '') === 'payee')
                                        <span class="inline-block rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                            Soldée
                                        </span>
                                    @elseif(($tranche['statut'] ?? '') === 'partiellement_payee')
                                        <span class="inline-block rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                            Partielle
                                        </span>
                                    @else
                                        <span class="inline-block rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                            À venir
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
