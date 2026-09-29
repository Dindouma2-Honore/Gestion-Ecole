<div class="space-y-3">
    <p class="text-sm text-gray-500">
        Aperçu de mise en page — les variables Blade seront remplacées lors de la génération réelle.
    </p>
    <iframe
        title="Aperçu du modèle {{ $template->code }} V{{ $template->version }}"
        srcdoc="{{ $template->contenu }}"
        class="h-[70vh] w-full rounded-lg border border-gray-200 bg-white"
        sandbox
    ></iframe>
</div>
