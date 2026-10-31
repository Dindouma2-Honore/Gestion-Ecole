<x-filament-panels::page>
    @php($t = $this->getRecord())
    <x-filament::section heading="Informations">
        <dl class="grid gap-3 sm:grid-cols-2">
            @foreach(['name'=>'Nom', 'code'=>'Code interne', 'provider_template_name'=>'Nom Meta', 'audience'=>'Audience', 'module'=>'Module', 'trigger_event'=>'Événement', 'language'=>'Langue', 'provider_category_requested'=>'Catégorie demandée', 'provider_category_effective'=>'Catégorie effective'] as $key=>$label)
                <div><dt class="font-semibold">{{ $label }}</dt><dd>{{ $t->$key ?: '—' }}</dd></div>
            @endforeach
        </dl>
    </x-filament::section>
    <x-filament::section heading="Contenu">
        <p>En-tête : {{ $t->header_type }} · Exemple média : {{ $t->header_handle ? 'Configuré' : 'Non configuré' }}</p>
        <p class="whitespace-pre-wrap">{{ $t->header_text }}</p>
        <p class="whitespace-pre-wrap">{{ $t->body }}</p><p>{{ $t->footer }}</p>
        @foreach($t->buttons ?? [] as $button)<p>{{ $button['type'] }} : {{ $button['text'] }}</p>@endforeach
    </x-filament::section>
    <x-filament::section heading="Variables">
        @foreach($t->variables_schema ?? [] as $variable)
            <p>{{ $variable['component'] ?? 'BODY' }} · {{ $variable['position'] }} · {{ $variable['key'] }} : {{ $variable['example'] }}</p>
        @endforeach
    </x-filament::section>
    <x-filament::section heading="Meta">
        <p>Statut local : {{ \App\Modules\Communication\Filament\Resources\CommunicationTemplateResource::LOCAL[$t->local_status] ?? $t->local_status }}</p>
        <p>Statut Meta : {{ \App\Modules\Communication\Filament\Resources\CommunicationTemplateResource::META[$t->meta_status] ?? $t->meta_status }}</p>
        <p>Identifiant : {{ $t->provider_template_id ?: 'Non soumis' }}</p>
        <p>Soumis le : {{ $t->submitted_at ?: '—' }} · Approuvé le : {{ $t->approved_at ?: '—' }}</p>
        <p>Dernière synchronisation : {{ $t->last_synced_at ?: 'Jamais / à actualiser' }}</p>
        <p>Qualité : {{ ['GREEN'=>'Bonne','YELLOW'=>'Moyenne','RED'=>'Faible'][$t->quality_rating] ?? 'Inconnue' }}</p>
        @if($t->rejection_reason)<p>Raison du rejet : {{ $t->rejection_reason }}</p>@endif
        @if($t->provider_feedback)<p>{{ $t->provider_feedback }}</p>@endif
        @if($t->provider_error_code)<p>Code erreur : {{ $t->provider_error_code }}</p>@endif
    </x-filament::section>
    <x-filament::section heading="Messages de test récents">
        @forelse(\App\Modules\Communication\Models\CommunicationMessage::where('communication_template_id',$t->id)->latest()->limit(20)->get() as $message)
            <p>#{{ $message->id }} · {{ $message->created_at }} · {{ ['queued'=>'En file','sending'=>'En cours','accepted'=>'Accepté par Meta','sent'=>'Envoyé','delivered'=>'Livré','read'=>'Lu','failed'=>'Échec','unknown'=>'Résultat incertain','blocked'=>'Bloqué'][$message->status] ?? $message->status }} · {{ $message->provider_message_id }}</p>
        @empty<p>Aucun message de test.</p>@endforelse
    </x-filament::section>
    <x-filament::section heading="Historique">
        @foreach($t->history()->limit(50)->get() as $event)
            <details class="py-2"><summary>{{ $event->created_at }} · {{ $event->event }} · {{ $event->user_id ? 'Utilisateur #'.$event->user_id : 'Système' }}</summary>
                <pre class="whitespace-pre-wrap text-sm">{{ json_encode(['avant'=>$event->old_values,'après'=>$event->new_values,'résultat'=>$event->result], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>
            </details>
        @endforeach
    </x-filament::section>
</x-filament-panels::page>
