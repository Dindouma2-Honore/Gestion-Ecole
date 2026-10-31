<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Reçu {{ $paiement->numero_recu }}</title>
    <style>
        @page {
            margin: 0;
            size: A4 portrait;
        }
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            color: #14213d;
            font: 10.5px/1.4 'DejaVu Sans', sans-serif;
            background: #fff;
        }
        .page {
            position: relative;
            min-height: 297mm;
            padding-bottom: 25mm;
        }
        .header {
            display: table;
            width: 100%;
            padding: 9mm 14mm 7mm;
            background: #102858;
            color: #fff;
        }
        .header-left, .header-right {
            display: table-cell;
            vertical-align: middle;
        }
        .header-right {
            width: 42%;
            text-align: right;
        }
        .logo {
            width: 46px;
            height: 46px;
            margin-right: 10px;
            vertical-align: middle;
        }
        .brand {
            display: inline-block;
            vertical-align: middle;
        }
        .brand strong {
            display: block;
            font-size: 17px;
            letter-spacing: .5px;
        }
        .brand small {
            color: #e6bd4c;
            font-size: 8.5px;
            letter-spacing: 1.2px;
            text-transform: uppercase;
        }
        .doc-type {
            font-size: 19px;
            font-weight: bold;
            letter-spacing: 1.5px;
        }
        .doc-number {
            color: #e6bd4c;
            font-size: 11px;
            font-weight: bold;
            margin-top: 2px;
            font-family: monospace;
        }
        .confirmed {
            padding: 6px;
            border-top: 3px solid #d9ad3d;
            border-bottom: 1px solid #afd9bd;
            background: #e9f7ee;
            color: #19703a;
            font-weight: bold;
            font-size: 10px;
            letter-spacing: .5px;
            text-align: center;
        }
        .content {
            padding: 6mm 14mm 0;
        }
        .meta {
            display: table;
            width: 100%;
            margin-bottom: 5mm;
        }
        .meta-col {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }
        .box {
            min-height: 70px;
            padding: 8px 11px;
            border-left: 3px solid #269452;
            border-radius: 4px;
            background: #f6f8fb;
        }
        .box-gold {
            border-left-color: #d9ad3d;
        }
        .label {
            color: #71809c;
            font-size: 8px;
            font-weight: bold;
            letter-spacing: .8px;
            text-transform: uppercase;
        }
        .value {
            margin-top: 2px;
            color: #102858;
            font-size: 12.5px;
            font-weight: bold;
        }
        .sub {
            margin-top: 3px;
            color: #4b5563;
            font-size: 9px;
            line-height: 1.35;
        }
        .info-bar {
            display: table;
            width: 100%;
            margin-bottom: 5mm;
            padding: 7px 10px;
            border-radius: 4px;
            background: #edf2f7;
            border: 1px solid #e2e8f0;
        }
        .info-cell {
            display: table-cell;
            width: 25%;
            vertical-align: middle;
        }
        .info-cell strong {
            display: block;
            margin-top: 2px;
            color: #102858;
            font-size: 9.5px;
        }
        .section-title {
            color: #102858;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: .6px;
            margin: 4mm 0 2mm;
            padding-bottom: 2px;
            border-bottom: 1px solid #e2e8f0;
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4mm;
        }
        .items th {
            padding: 6px 9px;
            background: #102858;
            color: #fff;
            font-size: 8px;
            letter-spacing: .5px;
            text-align: left;
            text-transform: uppercase;
        }
        .items th.num, .items td.num {
            text-align: right;
        }
        .items td {
            padding: 6px 9px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 9.5px;
        }
        .items tbody tr:nth-child(even) {
            background: #fafbfd;
        }
        .items tfoot tr {
            background: #eef3fb;
            font-weight: bold;
            border-top: 2px solid #cbd5e1;
        }
        .items tfoot td {
            padding: 6px 9px;
            color: #102858;
        }
        .totals-table {
            width: 55%;
            margin-left: auto;
            border-collapse: collapse;
            margin-bottom: 4mm;
            background: #fafcff;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
        }
        .totals-table td {
            padding: 5px 9px;
            font-size: 9.5px;
            border-bottom: 1px solid #edf2f7;
        }
        .totals-table td.amount {
            text-align: right;
            font-weight: bold;
            color: #102858;
        }
        .totals-table tr.highlight {
            background: #e9f7ee;
        }
        .totals-table tr.highlight td {
            color: #19703a;
            font-weight: bold;
        }
        .totals-table tr.balance {
            border-top: 2px solid #102858;
            background: #f1f5f9;
        }
        .totals-table tr.balance td {
            font-size: 11px;
            font-weight: bold;
            color: #102858;
        }
        .transaction {
            padding: 7px 10px;
            border-radius: 4px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            margin-bottom: 4mm;
        }
        .transaction table {
            width: 100%;
        }
        .transaction td {
            padding: 2px 0;
            font-size: 9px;
        }
        .transaction td:first-child {
            width: 38%;
            color: #64748b;
        }
        .signatures {
            display: table;
            width: 100%;
            margin-top: 8mm;
        }
        .signature {
            display: table-cell;
            width: 50%;
            padding: 0 10mm;
            text-align: center;
        }
        .signature div {
            padding-top: 45px;
            border-top: 1px dashed #94a3b8;
            color: #64748b;
            font-size: 8.5px;
            text-transform: uppercase;
            font-weight: bold;
        }
        .footer {
            position: absolute;
            right: 14mm;
            bottom: 7mm;
            left: 14mm;
            padding-top: 6px;
            border-top: 1px solid #e2e8f0;
            color: #64748b;
            font-size: 8px;
            text-align: center;
        }
        .footer strong {
            color: #102858;
        }
    </style>
</head>
<body>
@php
    $argent = static fn ($m): string => number_format((float) ($m ?? 0), 0, ',', ' ').' FCFA';
    $lignes = collect($contexte['repartition'] ?? [['libelle' => 'Paiement scolaire', 'montant' => (float) $paiement->montant]]);
    $montantVerse = (float) $paiement->montant;
    $totalAnnuel = (float) ($contexte['montant_total'] ?? $montantVerse);
    $totalPayeCumule = (float) ($contexte['total_paye'] ?? $montantVerse);
    $soldeRestant = (float) ($contexte['solde_restant'] ?? max(0, $totalAnnuel - $totalPayeCumule));
    $estSolde = (bool) ($contexte['est_solde'] ?? ($soldeRestant <= 0));

    $nomEleve = trim(($eleve['nom'] ?? '').' '.($eleve['prenom'] ?? ''));
    if ($nomEleve === '') {
        $nomEleve = trim(($eleve['prenom'] ?? '').' '.($eleve['nom'] ?? ''));
    }
    $matricule = $contexte['matricule'] ?? ($eleve['matricule_permanent'] ?? ($eleve['matricule'] ?? '—'));
    $classeNom = $contexte['classe_nom'] ?? ($eleve['classe_nom'] ?? ($paiement->inscription?->classe?->nom ?? '—'));
    $anneeLibelle = $contexte['annee_libelle'] ?? ($annee->libelle ?? '—');
    $parentNom = $contexte['parent_nom'] ?? ($eleve['parent_nom'] ?? ($paiement->inscription?->parent_principal_nom ?? 'Non renseigné'));
    $parentTel = $contexte['parent_telephone'] ?? ($eleve['parent_telephone'] ?? ($paiement->inscription?->parent_principal_telephone ?? null));

    $modeLibelle = match ($paiement->mode) {
        'especes' => 'Espèces (Caisse)',
        'bancaire' => 'Virement bancaire',
        'mobile_money' => 'Mobile Money'.($paiement->reference_mobile_money ? " ({$paiement->reference_mobile_money})" : ''),
        default => ucfirst(str_replace('_', ' ', (string) $paiement->mode)),
    };
@endphp

<div class="page">
    <div class="header">
        <div class="header-left">
            <img class="logo" src="{{ public_path('images/logo.png') }}" alt="Logo">
            <span class="brand">
                <strong>AMBASSADORS SCHOOL</strong>
                <small>Excellence · Foi · Discipline</small>
            </span>
        </div>
        <div class="header-right">
            <div class="doc-type">REÇU DE PAIEMENT SCOLAIRE</div>
            <div class="doc-number">N° {{ $paiement->numero_recu }}</div>
        </div>
    </div>

    <div class="confirmed">
        ✓ QUITTANCE VALIDÉE &bull; DOSSIER SCOLAIRE ACTIF &bull; ANNÉE {{ $anneeLibelle }}
    </div>

    <div class="content">
        {{-- BLOC 1 : IDENTITÉ ÉLÈVE & PARENT RESPONSABLE (HOMOGÈNE AVEC LA FICHE D'INSCRIPTION) --}}
        <div class="meta">
            <div class="meta-col" style="padding-right: 4mm;">
                <div class="box">
                    <div class="label">Élève inscrit(e) & Affectation</div>
                    <div class="value">{{ $nomEleve }}</div>
                    <div class="sub">
                        <strong>Matricule :</strong> {{ $matricule }}<br>
                        <strong>Classe :</strong> {{ $classeNom }} &bull; <strong>Année :</strong> {{ $anneeLibelle }}
                    </div>
                </div>
            </div>
            <div class="meta-col" style="padding-left: 4mm;">
                <div class="box box-gold">
                    <div class="label">Payeur / Responsable légal</div>
                    <div class="value">{{ $parentNom }}</div>
                    <div class="sub">
                        <strong>Contact :</strong> {{ $parentTel ?: 'Non renseigné' }}<br>
                        <strong>Facture liée :</strong> {{ $contexte['facture_reference'] ?? 'Paiement direct en caisse' }}
                    </div>
                </div>
            </div>
        </div>

        {{-- BLOC 2 : INFOS CLÉS DU RÈGLEMENT --}}
        <div class="info-bar">
            <div class="info-cell">
                <span class="label">Date de versement</span>
                <strong>{{ $paiement->created_at->format('d/m/Y à H:i') }}</strong>
            </div>
            <div class="info-cell">
                <span class="label">Classe & Niveau</span>
                <strong>{{ $classeNom }}</strong>
            </div>
            <div class="info-cell">
                <span class="label">Mode de règlement</span>
                <strong>{{ $modeLibelle }}</strong>
            </div>
            <div class="info-cell">
                <span class="label">Encaissé par</span>
                <strong>{{ $paiement->encaisseur?->name ?? 'Comptabilité' }}</strong>
            </div>
        </div>

        {{-- BLOC 3 : DÉTAIL DE L'AFFECTATION DU PRÉSENT VERSEMENT --}}
        <div class="section-title">Détail de l'affectation du versement (Présente quittance)</div>
        <table class="items">
            <thead>
                <tr>
                    <th>Prestation / Rubrique affectée</th>
                    <th class="num">Montant affecté</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lignes as $ligne)
                    <tr>
                        <td><strong>{{ $ligne['libelle'] }}</strong></td>
                        <td class="num"><strong>{{ $argent($ligne['montant']) }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td>Total du présent versement réglé</td>
                    <td class="num">{{ $argent($montantVerse) }}</td>
                </tr>
            </tfoot>
        </table>

        {{-- BLOC 4 : SITUATION FINANCIÈRE ANNUELLE (STRICTEMENT HOMOGÈNE AVEC LES 3 KPIS DE LA FICHE) --}}
        <div class="section-title">Situation financière globale du dossier (Année {{ $anneeLibelle }})</div>
        <table class="totals-table">
            <tr>
                <td>Total annuel dû (Scolarité + Inscription + Frais divers)</td>
                <td class="amount">{{ $argent($totalAnnuel) }}</td>
            </tr>
            <tr class="highlight">
                <td>Montant réglé sur cette quittance</td>
                <td class="amount">{{ $argent($montantVerse) }}</td>
            </tr>
            <tr>
                <td>Total cumulé déjà réglé à ce jour</td>
                <td class="amount">{{ $argent($totalPayeCumule) }}</td>
            </tr>
            <tr class="balance">
                <td>
                    Solde restant annuel à régulariser
                    @if($estSolde)
                        <span style="color: #19703a; font-size: 9px; margin-left: 6px;">(🟢 Soldé)</span>
                    @endif
                </td>
                <td class="amount" style="{{ $estSolde ? 'color: #19703a;' : 'color: #b45309;' }}">
                    {{ $estSolde ? '0 FCFA' : $argent($soldeRestant) }}
                </td>
            </tr>
        </table>

        {{-- BLOC 5 : RÉFÉRENCE DE LA TRANSACTION & STATUT --}}
        <div class="transaction">
            <div class="label">Sécurité & Traçabilité du règlement</div>
            <table>
                <tr>
                    <td>N° Quittance / Reçu :</td>
                    <td><strong>{{ $paiement->numero_recu }}</strong></td>
                </tr>
                <tr>
                    <td>Réf. transactionnelle :</td>
                    <td><strong>{{ $paiement->reference_mobile_money ?: ($contexte['reference_transaction'] ?? 'Caisse locale') }}</strong></td>
                </tr>
                <tr>
                    <td>Statut du dossier d'inscription :</td>
                    <td><strong>{{ $contexte['statut_inscription'] ?? 'Active' }}</strong></td>
                </tr>
            </table>
        </div>

        {{-- BLOC 6 : SIGNATURES ET CACHETS --}}
        <div class="signatures">
            <div class="signature">
                <div>Signature du Parent / Payeur</div>
            </div>
            <div class="signature">
                <div>Cachet & Signature — Caisse / Comptabilité</div>
            </div>
        </div>
    </div>

    <div class="footer">
        <strong>Ambassadors Educational Complex</strong> &bull; Emana, Bonne Fontaine, Yaoundé, Cameroun<br>
        Ce reçu officiel fait foi de paiement. Document certifié conforme émis par le système de gestion scolaire.
    </div>
</div>
</body>
</html>
