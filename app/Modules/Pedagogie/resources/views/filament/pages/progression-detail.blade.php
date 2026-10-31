<div class="space-y-4">
    <p>{{ $programme->titre }} — Version {{ $programme->version }}</p>
    <p>{{ $mesure['termines'] }} éléments terminés sur {{ $mesure['total'] }}. Calcul : {{ $mesure['strategie'] === 'heures' ? 'pondération par heures prévues' : 'poids égaux' }}.</p>
    <p>Les éléments commencés ou poursuivis ne comptent pas encore comme terminés.</p>
    @foreach ($programme->chapitres as $element)
        <div><strong>{{ $element->titre }}</strong>
        @foreach (($details[$element->id] ?? []) as $progression)
            <p>{{ $progression->seance?->date_seance?->format('d/m/Y') }} — {{ $progression->etat_avancement ?? ($progression->chapitre_termine ? 'Terminée' : 'En cours') }} : {{ $progression->contenu_couvert }}</p>
        @endforeach
        </div>
    @endforeach
</div>
