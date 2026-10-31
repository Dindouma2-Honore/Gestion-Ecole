<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Emploi du temps — {{ $classe }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 11px; }
        h1 { margin: 0 0 5px; font-size: 19px; }
        .meta { color: #4b5563; margin-bottom: 18px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 7px 8px; text-align: left; }
        th { background: #f3f4f6; font-size: 10px; }
        tr:nth-child(even) td { background: #f9fafb; }
    </style>
</head>
<body>
    <h1>Emploi du temps — {{ $classe }}</h1>
    <div class="meta">Année scolaire : {{ $annee }} · État : {{ $statut }}</div>
    <table>
        <thead><tr><th>Jour</th><th>Horaire</th><th>Matière / activité</th><th>Enseignant</th></tr></thead>
        <tbody>
            @forelse($emplois as $emploi)
                <tr>
                    <td>{{ $emploi->jour }}</td>
                    <td>{{ substr((string) $emploi->heure_debut, 0, 5) }}–{{ substr((string) $emploi->heure_fin, 0, 5) }}</td>
                    <td>{{ $emploi->matiere }}</td>
                    <td>{{ $emploi->enseignant }}</td>
                </tr>
            @empty
                <tr><td colspan="4">Aucun cours enregistré.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
