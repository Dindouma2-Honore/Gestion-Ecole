<x-filament-panels::page>
    @php
        $data = $this->getSupervisionData();
    @endphp

    <div class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900 shadow-sm">
                <span class="text-xs font-medium text-gray-500 uppercase">Cours suivis</span>
                <p class="text-2xl font-bold text-gray-950 dark:text-white mt-1">{{ $data['total_cours_suivis'] }}</p>
                <span class="text-xs text-gray-400 mt-2 block">Matières & classes combinées</span>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900 shadow-sm">
                <span class="text-xs font-medium text-gray-500 uppercase">Cours conformes / en avance</span>
                <p class="text-2xl font-bold text-green-600 mt-1">{{ $data['total_cours_suivis'] - $data['nombre_retards'] }}</p>
                <span class="text-xs text-gray-400 mt-2 block">Dans la marge de tolérance (±10%)</span>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900 shadow-sm">
                <span class="text-xs font-medium text-gray-500 uppercase">Alertes de retard</span>
                <p class="text-2xl font-bold text-red-600 mt-1">{{ $data['nombre_retards'] }}</p>
                <span class="text-xs text-gray-400 mt-2 block">Écart &gt; 10% vs calendrier</span>
            </div>
        </div>

        @if(!empty($data['alertes_retard']))
            <div class="rounded-xl border border-red-200 bg-red-50/50 p-6 dark:border-red-900/50 dark:bg-red-950/20">
                <h3 class="font-bold text-red-800 dark:text-red-400 flex items-center gap-2">
                    <svg class="w-5 h-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                    Alertes : cours nécessitant une attention pédagogique
                </h3>
                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($data['alertes_retard'] as $alerte)
                        <div class="rounded-lg bg-white p-4 shadow-sm border border-red-100 dark:bg-gray-900 dark:border-gray-800">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h4 class="font-bold text-gray-900 dark:text-white">{{ $alerte['matiere_nom'] }}</h4>
                                    <p class="text-xs text-gray-500 font-medium">{{ $alerte['classe_nom'] }}</p>
                                </div>
                                <span class="rounded bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700 dark:bg-red-950 dark:text-red-400">
                                    Retard : {{ $alerte['delta'] }}%
                                </span>
                            </div>
                            <div class="mt-2 text-xs text-gray-500">
                                Avancement actuel : <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $alerte['taux_simple'] }}%</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="rounded-xl border border-gray-200 bg-white overflow-hidden shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="p-6 border-b border-gray-100 dark:border-gray-800">
                <h3 class="font-bold text-gray-900 dark:text-white">État complet des progressions par classe</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-600 dark:text-gray-300">
                    <thead class="bg-gray-50 text-xs font-semibold uppercase text-gray-500 dark:bg-gray-800/50">
                        <tr>
                            <th class="px-6 py-3">Classe</th>
                            <th class="px-6 py-3">Matière</th>
                            <th class="px-6 py-3">Avancement réel</th>
                            <th class="px-6 py-3">Pondéré</th>
                            <th class="px-6 py-3">Écart calendrier</th>
                            <th class="px-6 py-3">Statut</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($data['syntheses'] as $ligne)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50">
                                <td class="px-6 py-4 font-semibold text-gray-900 dark:text-white">{{ $ligne['classe_nom'] }}</td>
                                <td class="px-6 py-4">{{ $ligne['matiere_nom'] }}</td>
                                <td class="px-6 py-4 font-medium">{{ $ligne['taux_simple'] }}%</td>
                                <td class="px-6 py-4">{{ $ligne['taux_pondere'] }}%</td>
                                <td class="px-6 py-4 font-semibold {{ $ligne['delta'] < -10 ? 'text-red-600' : 'text-green-600' }}">
                                    {{ $ligne['delta'] > 0 ? '+'.$ligne['delta'] : $ligne['delta'] }}%
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium
                                        @if($ligne['statut_ecart'] === 'conforme') bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-400
                                        @elseif($ligne['statut_ecart'] === 'en_retard') bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-400
                                        @else bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-400 @endif">
                                        {{ ucfirst(str_replace('_', ' ', $ligne['statut_ecart'])) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
