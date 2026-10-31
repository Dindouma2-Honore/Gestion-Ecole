@php
    $type = $record->type === 'definitive' ? 'Définitive' : 'Provisoire';
    $statut = match ($record->statut_envoi) { 'envoyee' => 'Envoyée', 'echec' => 'Échec d’envoi', default => 'En attente' };
    $eleve = trim(($record->inscription?->eleve?->prenom ?? '').' '.($record->inscription?->eleve?->nom ?? '')) ?: 'Non renseigné';
@endphp
<x-filament::section heading="Facture" icon="heroicon-o-receipt-percent">
    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10"><table class="w-full table-fixed text-left text-sm">
        <thead class="bg-gray-50 text-xs font-semibold uppercase text-gray-500 dark:bg-white/5"><tr><th class="px-4 py-3">Information</th><th class="px-4 py-3">Valeur</th><th class="px-4 py-3">Information</th><th class="px-4 py-3">Valeur</th></tr></thead>
        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
            @foreach([['N° facture', $record->numero, 'Type', $type], ['Élève', $eleve, 'Classe', $record->inscription?->classe?->nom ?? 'Non renseignée'], ['Montant', number_format((float) $record->montant, 0, ',', ' ').' FCFA', 'Date d’émission', $record->date_emission?->format('d/m/Y') ?? 'Non renseignée'], ['Destinataire', $record->nom_destinataire ?: '—', 'Téléphone', $record->telephone_destinataire ?: '—'], ['Envoi au parent', $statut, 'Statut', ucfirst((string) $record->statut)]] as $ligne)
                <tr class="hover:bg-blue-50/60 dark:hover:bg-white/5"><th class="px-4 py-3 font-medium text-gray-500">{{ $ligne[0] }}</th><td class="px-4 py-3 font-semibold">{{ $ligne[1] }}</td><th class="px-4 py-3 font-medium text-gray-500">{{ $ligne[2] }}</th><td class="px-4 py-3 font-semibold">{{ $ligne[3] }}</td></tr>
            @endforeach
        </tbody>
    </table></div>
</x-filament::section>
