<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Fiche Document - {{ $document->nom }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 13px;
            color: #333;
            line-height: 1.5;
            margin: 20px;
        }
        .header {
            border-bottom: 2px solid #2563eb;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .header h1 {
            color: #1e3a8a;
            font-size: 22px;
            margin: 0 0 5px 0;
        }
        .header p {
            color: #64748b;
            margin: 0;
            font-size: 12px;
        }
        .section {
            margin-bottom: 20px;
        }
        .table-info {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .table-info th, .table-info td {
            border: 1px solid #e2e8f0;
            padding: 8px 12px;
            text-align: left;
        }
        .table-info th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: 600;
            width: 30%;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-public { background-color: #dcfce7; color: #166534; }
        .badge-interne { background-color: #e0f2fe; color: #075985; }
        .badge-restreint { background-color: #fee2e2; color: #991b1b; }
        .footer {
            margin-top: 40px;
            border-top: 1px solid #e2e8f0;
            padding-top: 10px;
            font-size: 10px;
            color: #94a3b8;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Fiche Récapitulative du Document</h1>
        <p>Généré le {{ now()->format('d/m/Y H:i') }} - Ambassadors Systèmes</p>
    </div>

    <div class="section">
        <table class="table-info">
            <tr>
                <th>Nom du document</th>
                <td><strong>{{ $document->nom }}</strong></td>
            </tr>
            <tr>
                <th>Catégorie</th>
                <td>{{ $document->categorie }}</td>
            </tr>
            <tr>
                <th>Niveau de confidentialité</th>
                <td>
                    <span class="badge badge-{{ $document->niveau_confidentialite }}">
                        {{ strtoupper($document->niveau_confidentialite) }}
                    </span>
                </td>
            </tr>
            <tr>
                <th>Ajouté par</th>
                <td>{{ $document->creator->name ?? 'Système' }}</td>
            </tr>
            <tr>
                <th>Date de création</th>
                <td>{{ $document->created_at ? $document->created_at->format('d/m/Y à H:i') : '-' }}</td>
            </tr>
            <tr>
                <th>Date d'expiration</th>
                <td>{{ $document->date_expiration ? \Carbon\Carbon::parse($document->date_expiration)->format('d/m/Y') : 'Aucune' }}</td>
            </tr>
            <tr>
                <th>Emplacement du fichier</th>
                <td><code>{{ $document->fichier_path }}</code></td>
            </tr>
            <tr>
                <th>Taille</th>
                <td>{{ round(($document->taille ?? 0) / 1024, 2) }} Ko</td>
            </tr>
        </table>
    </div>

    <div class="footer">
        Document officiel généré automatiquement depuis le système d'information.
    </div>
</body>
</html>
