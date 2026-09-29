<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Périodes scolaires</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-gray-50 p-4 text-gray-950 dark:bg-gray-950 dark:text-gray-100 sm:p-8">
    <main class="mx-auto max-w-4xl rounded-2xl bg-white p-6 shadow-sm dark:bg-gray-900 sm:p-10">
        <a href="{{ url('/accueil') }}" class="text-sm font-semibold text-primary-600">← Retour à l’accueil</a>
        <h1 class="mt-5 text-3xl font-bold">Périodes scolaires</h1>
        <p class="mt-2 text-gray-600 dark:text-gray-300">
            {{ $annee ? 'Calendrier commun aux trois niveaux pour l’année '.$annee->libelle.'.' : 'Aucune année scolaire active.' }}
        </p>

        <div class="mt-8 grid gap-4 sm:grid-cols-2">
            @forelse ($periodes as $periode)
                <article class="rounded-xl border border-gray-200 p-5 dark:border-gray-700">
                    <h2 class="font-bold">{{ $periode['libelle'] ?? $periode['nom'] }}</h2>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                        Du {{ \Illuminate\Support\Carbon::parse($periode['date_debut'])->format('d/m/Y') }}
                        au {{ \Illuminate\Support\Carbon::parse($periode['date_fin'])->format('d/m/Y') }}
                    </p>
                </article>
            @empty
                <p class="text-gray-500">Aucune période n’est encore publiée.</p>
            @endforelse
        </div>
    </main>
</body>
</html>
