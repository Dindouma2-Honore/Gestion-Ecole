<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">

    <title>Liste des inscriptions</title>

    <style>
        @page {
            size: A4 landscape;
            margin: 12mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #111827;
            margin: 0;
            font-size: 11px;
        }

        .header {
            text-align: center;
            margin-bottom: 18px;
        }

        .header h1 {
            margin: 0 0 5px;
            font-size: 20px;
            text-transform: uppercase;
        }

        .header p {
            margin: 3px 0;
            color: #6b7280;
        }

        .separator {
            border-top: 2px solid #111827;
            margin: 12px 0;
        }

        .summary {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }

        .summary-card {
            flex: 1;
            border: 1px solid #d1d5db;
            padding: 8px;
            text-align: center;
            border-radius: 5px;
        }

        .summary-card strong {
            display: block;
            font-size: 16px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f3f4f6;
            border: 1px solid #9ca3af;
            padding: 7px 5px;
            text-align: left;
            font-weight: bold;
        }

        td {
            border: 1px solid #d1d5db;
            padding: 6px 5px;
            vertical-align: middle;
        }

        tbody tr:nth-child(even) {
            background: #f9fafb;
        }

        .footer {
            margin-top: 18px;
            display: flex;
            justify-content: space-between;
            color: #6b7280;
            font-size: 10px;
        }

        .badge {
            display: inline-block;
            padding: 3px 7px;
            border-radius: 10px;
            background: #f3f4f6;
        }

        @media print {
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>

<body>

<div class="header">
    <h1>Ambassadors Educational Complex</h1>

    <p>
        <strong>Liste des inscriptions</strong>
    </p>

    @if($anneeScolaire)
        <p>
            Année scolaire : <strong>{{ $anneeScolaire }}</strong>
        </p>
    @endif

    <div class="separator"></div>
</div>

<div class="summary">

    <div class="summary-card">
        <strong>{{ $total }}</strong>
        Total inscriptions
    </div>

    <div class="summary-card">
        <strong>{{ $filles }}</strong>
        Filles
    </div>

    <div class="summary-card">
        <strong>{{ $garcons }}</strong>
        Garçons
    </div>

</div>

<table>
    <thead>
        <tr>

            @if(in_array('matricule', $colonnes))
                <th>Matricule</th>
            @endif

            @if(in_array('eleve', $colonnes))
                <th>Élève</th>
            @endif

            @if(in_array('sexe', $colonnes))
                <th>Sexe</th>
            @endif

            @if(in_array('classe', $colonnes))
                <th>Classe</th>
            @endif

            @if(in_array('annee', $colonnes))
                <th>Année scolaire</th>
            @endif

            @if(in_array('type', $colonnes))
                <th>Type</th>
            @endif

            @if(in_array('date', $colonnes))
                <th>Date</th>
            @endif

            @if(in_array('statut', $colonnes))
                <th>Statut</th>
            @endif

            @if(in_array('paiement', $colonnes))
                <th>Statut de paiement</th>
            @endif

        </tr>
    </thead>

    <tbody>

        @foreach($inscriptions as $inscription)

            <tr>

                @if(in_array('matricule', $colonnes))
                    <td>
                        {{ $inscription->eleve?->matricule_permanent ?? '—' }}
                    </td>
                @endif

                @if(in_array('eleve', $colonnes))
                    <td>
                        <strong>
                            {{ $inscription->eleve?->nom }}
                            {{ $inscription->eleve?->prenom }}
                        </strong>
                    </td>
                @endif

                @if(in_array('sexe', $colonnes))
                    <td>
                        {{ $inscription->eleve?->sexe === 'F' ? 'Fille' : 'Garçon' }}
                    </td>
                @endif

                @if(in_array('classe', $colonnes))
                    <td>
                        {{ $inscription->classe?->nom ?? '—' }}
                    </td>
                @endif

                @if(in_array('annee', $colonnes))
                    <td>
                        {{ $anneeScolaire ?? $inscription->annee_scolaire_id }}
                    </td>
                @endif

                @if(in_array('type', $colonnes))
                    <td>
                        {{ $inscription->type ?? '—' }}
                    </td>
                @endif

                @if(in_array('date', $colonnes))
                    <td>
                        {{ $inscription->date_inscription?->format('d/m/Y') ?? '—' }}
                    </td>
                @endif

                @if(in_array('statut', $colonnes))
                    <td>
                        {{ match($inscription->statut) {
                            'en_attente_versement' => 'En attente de versement',
                            'active' => 'Active',
                            'annulee' => 'Annulée',
                            default => $inscription->statut,
                        } }}
                    </td>
                @endif

                @if(in_array('paiement', $colonnes))
                    <td>
                        <span class="badge">
                            {{ match($inscription->statut) {
                                'active' => 'Payé',
                                'en_attente_versement' => 'En attente de paiement',
                                'annulee' => 'Annulé',
                                default => 'Non renseigné',
                            } }}
                        </span>
                    </td>
                @endif

            </tr>

        @endforeach

    </tbody>
</table>

<div class="footer">
    <span>
        Document généré le {{ now()->format('d/m/Y à H:i') }}
    </span>

    <span>
        Ambassadors Educational Complex
    </span>
</div>

<script>
    window.addEventListener('load', function () {
        window.print();
    });
</script>

</body>
</html>