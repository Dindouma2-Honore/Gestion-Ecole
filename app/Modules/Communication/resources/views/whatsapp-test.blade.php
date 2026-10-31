<x-filament-panels::page>
    <p>Phase TEST META. Seuls les destinataires configurés sont autorisés. Le test utilise le modèle Meta hello_world.</p>
    <label>Destinataire autorisé
        <select wire:model="recipient"><option value="">Sélectionner</option>@foreach(config('whatsapp.allowlist', []) as $number)<option value="{{ $number }}">{{ $number }}</option>@endforeach</select>
    </label>
    @error('recipient') <p>{{ $message }}</p> @enderror
    <div>
        @can('whatsapp.connect')<x-filament::button wire:click="connection" wire:loading.attr="disabled">Tester la connexion</x-filament::button>@endcan
        @can('whatsapp.simulate')<x-filament::button wire:click="send(true)" wire:loading.attr="disabled">Simuler</x-filament::button>@endcan
        @can('whatsapp.send')<x-filament::button wire:click="send(false)" wire:confirm="Envoyer hello_world à ce destinataire Meta de test ?" wire:loading.attr="disabled">Envoyer le test</x-filament::button><x-filament::button wire:click="send(false, true)" wire:confirm="Envoyer le PDF fictif au destinataire Meta de test ?" wire:loading.attr="disabled">PDF fictif</x-filament::button>@endcan
    </div>
    <p role="status">{{ $result }}</p>
    <table><thead><tr><th>Message</th><th>Prévu</th><th>Effectif</th><th>Statut</th><th>Motif</th></tr></thead><tbody>
    @foreach($this->messages() as $message)<tr><td>{{ $message->id }}</td><td>{{ $message->intended_recipient }}</td><td>{{ $message->actual_recipient }}</td><td>{{ $message->status }}</td><td>{{ $message->reason }}</td></tr>@endforeach
    </tbody></table>
<h2>Templates ERP</h2><p>Les contenus se gèrent dans le catalogue de templates du Socle. LOCAL_ONLY ne signifie pas approuvé par Meta.</p>
    <table><thead><tr><th>Code</th><th>État Meta</th></tr></thead><tbody>@foreach($this->templates() as $template)<tr><td>{{ $template->code }}</td><td>{{ $template->status }}</td></tr>@endforeach</tbody></table>
</x-filament-panels::page>
