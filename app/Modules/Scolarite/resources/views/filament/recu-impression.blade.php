<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">

    <title>Reçu {{ $paiement->numero_recu }}</title>

    <style>
        @page {
            size: A5 portrait;
            margin: 14mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #111827;
            margin: 0;
            font-size: 13px;
        }

        .header {
            text-align: center;
            margin-bottom: 16px;
        }

        .header h1 {
            margin: 0 0 5px;
            font-size: 18px;
            text-transform: uppercase;
        }

        .separator {
            border-top: 2px solid #111827;
            margin: 10px 0;
        }

        .recu-titre {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .recu-titre h2 {
            margin: 0;
            font-size: 15px;
            text-transform: uppercase;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        td {
            border: 1px solid #d1d5db;
            padding: 7px 8px;
            vertical-align: middle;
        }

        td.label {
            width: 40%;
            background: #f3f4f6;
            font-weight: bold;
        }

        .montant-verse {
            text-align: right;
            font-size: 12px;
        }

        .montant-verse strong {
            font-size: 17px;
            display: inline-block;
            margin-left: 10px;
        }

        .footer {
            margin-top: 24px;
            color: #6b7280;
            font-size: 10px;
            text-align: center;
        }
    </style>
</head>

<body>

<div class="header">
    <h1>Ambassadors Educational Complex</h1>
    <div class="separator"></div>
</div>

<div class="recu-titre">
    <h2>Reçu de versement</h2>
    <span>N° {{ $paiement->numero_recu }}</span>
</div>

<table>
    <tr>
        <td class="label">Élève</td>
        <td>{{ $paiement->inscription?->eleve?->prenom }} {{ $paiement->inscription?->eleve?->nom }}</td>
    </tr>
    <tr>
        <td class="label">Classe</td>
        <td>{{ $paiement->inscription?->classe?->nom ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">Mode de versement</td>
        <td>
            {{ match($paiement->mode) {
                'especes' => 'Espèces',
                'bancaire' => 'Virement bancaire',
                'mobile_money' => 'Mobile Money'.($paiement->reference_mobile_money ? " (réf. {$paiement->reference_mobile_money})" : ''),
                default => $paiement->mode,
            } }}
        </td>
    </tr>
    <tr>
        <td class="label">Date</td>
        <td>{{ $paiement->created_at?->format('d/m/Y à H:i') }}</td>
    </tr>
</table>

<div class="montant-verse">
    Montant versé :
    <strong>{{ number_format((float) $paiement->montant, 0, ',', ' ') }} XAF</strong>
</div>

<div class="footer">
    Document généré le {{ now()->format('d/m/Y à H:i') }} — Ambassadors Educational Complex
</div>

</body>
</html>
