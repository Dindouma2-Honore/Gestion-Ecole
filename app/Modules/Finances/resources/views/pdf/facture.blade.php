<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Facture - {{ $facture->reference ?? 'FACT-'.$facture->id }}</title>
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #14213d; font: 11px/1.45 DejaVu Sans, sans-serif; }
        .page { position: relative; min-height: 297mm; padding-bottom: 25mm; }
        .header { display: table; width: 100%; padding: 10mm 15mm 8mm; background: #102858; color: #fff; }
        .header-left, .header-right { display: table-cell; vertical-align: middle; }
        .header-right { width: 35%; text-align: right; }
        .brand strong { display: block; font-size: 18px; letter-spacing: 0.5px; }
        .brand small { color: #e6bd4c; font-size: 9px; letter-spacing: 1.2px; text-transform: uppercase; }
        .doc-type { font-size: 22px; font-weight: bold; letter-spacing: 2px; }
        .doc-number { margin-top: 4px; color: #e6bd4c; font-size: 10px; font-weight: bold; }
        .accent { height: 4px; background: #d9ad3d; }
        .content { padding: 8mm 15mm 0; }
        .meta { display: table; width: 100%; margin-bottom: 6mm; }
        .meta-col { display: table-cell; width: 50%; vertical-align: top; }
        .box { min-height: 80px; padding: 10px 12px; border-left: 3px solid #d9ad3d; border-radius: 4px; background: #f5f7fb; }
        .label { color: #71809c; font-size: 8px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; }
        .value { margin-top: 3px; color: #102858; font-size: 12px; font-weight: bold; }
        .sub { margin-top: 2px; color: #56627a; font-size: 9.5px; }
        .info { display: table; width: 100%; margin-bottom: 6mm; padding: 8px 12px; border-radius: 5px; background: #f0f3f8; }
        .info-cell { display: table-cell; width: 33.33%; }
        .info-cell strong { display: block; margin-top: 2px; color: #102858; }
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 6mm; }
        .items th { padding: 8px 10px; background: #102858; color: #fff; font-size: 9px; letter-spacing: 0.5px; text-align: left; text-transform: uppercase; }
        .items th.num, .items td.num { text-align: right; }
        .items td { padding: 8px 10px; border-bottom: 1px solid #e1e6ef; }
        .items tbody tr:nth-child(even) { background: #fafbfd; }
        .totals { width: 42%; margin: 4mm 0 6mm auto; border-collapse: collapse; }
        .totals td { padding: 5px 8px; }
        .totals .amount { color: #102858; font-weight: bold; text-align: right; }
        .totals .grand td { padding-top: 8px; border-top: 2px solid #d9ad3d; font-size: 14px; font-weight: bold; }
        .signatures { display: table; width: 100%; margin-top: 15mm; }
        .signature { display: table-cell; width: 50%; padding: 0 10mm; text-align: center; }
        .signature div { padding-top: 5px; border-top: 1px solid #bcc5d5; color: #71809c; font-size: 8px; text-transform: uppercase; }
        .footer { position: absolute; right: 15mm; bottom: 8mm; left: 15mm; padding-top: 6px; border-top: 1px solid #dfe4ed; color: #8490a6; font-size: 8px; text-align: center; }
    </style>
</head>
<body>
<div class="page">
    <div class="header">
        <div class="header-left">
            <span class="brand">
                <strong>AMBASSADORS EDUCATIONAL COMPLEX</strong>
                <small>Service Financier & Comptabilité</small>
            </span>
        </div>
        <div class="header-right">
            <div class="doc-type">FACTURE</div>
            <div class="doc-number">N° {{ $facture->reference ?? 'FACT-'.$facture->id }}</div>
        </div>
    </div>
    <div class="accent"></div>

    <div class="content">
        <div class="meta">
            <div class="meta-col" style="padding-right:4mm">
                <div class="box">
                    <div class="label">Facturé à</div>
                    <div class="value">{{ $facture->client_nom ?? $facture->parent_nom ?? 'Client' }}</div>
                    <div class="sub">Élève concerné : {{ $facture->eleve_nom ?? 'N/A' }}</div>
                    <div class="sub">Contact : {{ $facture->client_email ?? $facture->parent_email ?? 'N/A' }}</div>
                </div>
            </div>
            <div class="meta-col" style="padding-left:4mm">
                <div class="box">
                    <div class="label">Établissement Émetteur</div>
                    <div class="value">Ambassadors Educational Complex</div>
                    <div class="sub">Emana, Bonne Fontaine — Yaoundé, Cameroun</div>
                    <div class="sub">finance@ambassadors-aec.com</div>
                </div>
            </div>
        </div>

        <div class="info">
            <div class="info-cell"><span class="label">Date d'émission</span><strong>{{ isset($facture->created_at) ? $facture->created_at->format('d/m/Y') : date('d/m/Y') }}</strong></div>
            <div class="info-cell"><span class="label">Statut</span><strong>{{ strtoupper($facture->statut ?? 'Payée') }}</strong></div>
            <div class="info-cell"><span class="label">Devise</span><strong>XAF (FCFA)</strong></div>
        </div>

        <table class="items">
            <thead>
                <tr>
                    <th>Désignation / Prestation</th>
                    <th class="num">Quantité</th>
                    <th class="num">Prix unitaire</th>
                    <th class="num">Montant Total</th>
                </tr>
            </thead>
            <tbody>
                @if (isset($facture->lignes) && count($facture->lignes) > 0)
                    @foreach($facture->lignes as $ligne)
                        <tr>
                            <td>{{ $ligne->libelle ?? $ligne->designation }}</td>
                            <td class="num">{{ $ligne->quantite ?? 1 }}</td>
                            <td class="num">{{ number_format((float) ($ligne->montant_unitaire ?? $ligne->montant), 0, ',', ' ') }} FCFA</td>
                            <td class="num"><strong>{{ number_format((float) ($ligne->montant_total ?? $ligne->montant), 0, ',', ' ') }} FCFA</strong></td>
                        </tr>
                    @endforeach
                @else
                    <tr>
                        <td>{{ $facture->libelle ?? 'Frais scolaires / prestations' }}</td>
                        <td class="num">1</td>
                        <td class="num">{{ number_format((float) ($facture->montant_total ?? 0), 0, ',', ' ') }} FCFA</td>
                        <td class="num"><strong>{{ number_format((float) ($facture->montant_total ?? 0), 0, ',', ' ') }} FCFA</strong></td>
                    </tr>
                @endif
            </tbody>
        </table>

        <table class="totals">
            <tr>
                <td>Montant Hors Taxe</td>
                <td class="amount">{{ number_format((float) ($facture->montant_total ?? 0), 0, ',', ' ') }} FCFA</td>
            </tr>
            <tr class="grand">
                <td>Total Général Net</td>
                <td class="amount">{{ number_format((float) ($facture->montant_total ?? 0), 0, ',', ' ') }} FCFA</td>
            </tr>
        </table>

        <div class="signatures">
            <div class="signature"><div>Signature du Client / Parent</div></div>
            <div class="signature"><div>Cachet du Service Financier</div></div>
        </div>
    </div>

    <div class="footer">
        <strong>Ambassadors Educational Complex</strong> — Yaoundé, Cameroun<br>
        Facture établie conformément aux tarifs scolaires en vigueur.
    </div>
</div>
</body>
</html>
