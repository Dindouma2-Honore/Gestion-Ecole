<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">

    <title>Facture {{ $facture->numero }}</title>

    <style>
        @page {
            size: A4 portrait;
            margin: 18mm;
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
            margin-bottom: 20px;
        }

        .header h1 {
            margin: 0 0 5px;
            font-size: 22px;
            text-transform: uppercase;
        }

        .header p {
            margin: 3px 0;
            color: #6b7280;
        }

        .separator {
            border-top: 2px solid #111827;
            margin: 14px 0;
        }

        .facture-titre {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .facture-titre h2 {
            margin: 0;
            font-size: 18px;
            text-transform: uppercase;
        }

        .facture-titre .numero {
            font-size: 14px;
            color: #374151;
        }

        .infos {
            display: flex;
            gap: 20px;
            margin-bottom: 24px;
        }

        .infos-bloc {
            flex: 1;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 12px 14px;
        }

        .infos-bloc h3 {
            margin: 0 0 8px;
            font-size: 11px;
            text-transform: uppercase;
            color: #6b7280;
        }

        .infos-bloc p {
            margin: 3px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th {
            background: #f3f4f6;
            border: 1px solid #9ca3af;
            padding: 9px 8px;
            text-align: left;
            font-weight: bold;
        }

        td {
            border: 1px solid #d1d5db;
            padding: 9px 8px;
            vertical-align: middle;
        }

        .montant-total {
            text-align: right;
            font-size: 12px;
        }

        .montant-total strong {
            font-size: 18px;
            display: inline-block;
            margin-left: 10px;
        }

        .statut-envoi {
            margin-top: 20px;
            border: 1px dashed #9ca3af;
            border-radius: 6px;
            padding: 10px 14px;
            font-size: 11px;
            color: #6b7280;
        }

        .footer {
            margin-top: 30px;
            display: flex;
            justify-content: space-between;
            color: #6b7280;
            font-size: 10px;
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
    <div class="separator"></div>
</div>

<div class="facture-titre">
    <h2>Facture {{ $facture->type === 'definitive' ? 'définitive' : 'provisoire' }} d'inscription</h2>
    <span class="numero">N° {{ $facture->numero }}</span>
</div>

<div class="infos">
    <div class="infos-bloc">
        <h3>Élève</h3>
        <p><strong>{{ $facture->inscription?->eleve?->prenom }} {{ $facture->inscription?->eleve?->nom }}</strong></p>
        <p>Matricule : {{ $facture->inscription?->eleve?->matricule_permanent ?? '—' }}</p>
        <p>Classe : {{ $facture->inscription?->classe?->nom ?? '—' }}</p>
    </div>

    <div class="infos-bloc">
        <h3>Destinataire</h3>
        <p><strong>{{ $facture->nom_destinataire ?? '—' }}</strong></p>
        <p>Téléphone : {{ $facture->telephone_destinataire ?? '—' }}</p>
        <p>Date d'émission : {{ $facture->date_emission?->format('d/m/Y') }}</p>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>Désignation</th>
            <th style="text-align: right;">Montant</th>
        </tr>
    </thead>
    <tbody>
        @forelse($detailFrais ?? [] as $ligne)
            <tr>
                <td>{{ $ligne->frais?->nom ?? 'Frais' }}</td>
                <td style="text-align: right;">{{ number_format((float) $ligne->montant, 0, ',', ' ') }} XAF</td>
            </tr>
        @empty
            <tr>
                <td>Frais de scolarité — {{ $facture->inscription?->classe?->nom ?? 'Classe' }}</td>
                <td style="text-align: right;">{{ number_format((float) $facture->montant, 0, ',', ' ') }} XAF</td>
            </tr>
        @endforelse
    </tbody>
</table>

<div class="montant-total">
    Total à payer :
    <strong>{{ number_format((float) $facture->montant, 0, ',', ' ') }} XAF</strong>
</div>

<div class="statut-envoi">
    Statut d'envoi au parent :
    {{ match($facture->statut_envoi) {
        'envoyee' => 'Envoyée le '.optional($facture->envoyee_le)->format('d/m/Y à H:i'),
        'echec' => 'Échec d\'envoi — '.($facture->echec_raison ?? 'raison non précisée'),
        default => 'En attente (envoi WhatsApp à venir)',
    } }}
</div>

<div class="footer">
    <span>Document généré le {{ now()->format('d/m/Y à H:i') }}</span>
    <span>Ambassadors Educational Complex</span>
</div>

<script>
    window.addEventListener('load', function () {
        window.print();
    });
</script>

</body>
</html>
