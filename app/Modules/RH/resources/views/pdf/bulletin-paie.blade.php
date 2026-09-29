<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Bulletin de Paie - {{ $bulletin->employe?->nom_complet }}</title>
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #14213d; font: 11px/1.4 DejaVu Sans, sans-serif; }
        .page { position: relative; min-height: 297mm; padding-bottom: 25mm; }
        .header { display: table; width: 100%; padding: 10mm 15mm 8mm; background: #102858; color: #fff; }
        .header-left, .header-right { display: table-cell; vertical-align: middle; }
        .header-right { width: 40%; text-align: right; }
        .brand strong { display: block; font-size: 18px; letter-spacing: 0.5px; }
        .brand small { color: #e6bd4c; font-size: 9px; letter-spacing: 1.2px; text-transform: uppercase; }
        .doc-type { font-size: 20px; font-weight: bold; letter-spacing: 1.5px; }
        .doc-number { margin-top: 4px; color: #e6bd4c; font-size: 10px; font-weight: bold; }
        .accent { height: 4px; background: #d9ad3d; }
        .content { padding: 8mm 15mm 0; }
        .meta { display: table; width: 100%; margin-bottom: 6mm; }
        .meta-col { display: table-cell; width: 50%; vertical-align: top; }
        .box { min-height: 85px; padding: 10px 12px; border-left: 3px solid #d9ad3d; border-radius: 4px; background: #f5f7fb; }
        .label { color: #71809c; font-size: 8px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; }
        .value { margin-top: 3px; color: #102858; font-size: 13px; font-weight: bold; }
        .sub { margin-top: 2px; color: #56627a; font-size: 9.5px; }
        .info { display: table; width: 100%; margin-bottom: 6mm; padding: 8px 12px; border-radius: 5px; background: #f0f3f8; }
        .info-cell { display: table-cell; width: 25%; }
        .info-cell strong { display: block; margin-top: 2px; color: #102858; font-size: 11px; }
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 6mm; }
        .items th { padding: 8px 10px; background: #102858; color: #fff; font-size: 9px; letter-spacing: 0.5px; text-align: left; text-transform: uppercase; }
        .items th.num, .items td.num { text-align: right; }
        .items td { padding: 8px 10px; border-bottom: 1px solid #e1e6ef; }
        .items tbody tr:nth-child(even) { background: #fafbfd; }
        .totals { width: 45%; margin: 4mm 0 6mm auto; border-collapse: collapse; }
        .totals td { padding: 5px 8px; }
        .totals .amount { color: #102858; font-weight: bold; text-align: right; }
        .totals .grand td { padding-top: 8px; border-top: 2px solid #d9ad3d; font-size: 14px; font-weight: bold; }
        .status-badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 9px; font-weight: bold; text-transform: uppercase; }
        .status-paye { background: #d1fae5; color: #065f46; }
        .status-valide { background: #dbeafe; color: #1e40af; }
        .status-calcule { background: #fef3c7; color: #92400e; }
        .signatures { display: table; width: 100%; margin-top: 15mm; }
        .signature { display: table-cell; width: 50%; padding: 0 10mm; text-align: center; }
        .signature div { padding-top: 5px; border-top: 1px solid #bcc5d5; color: #71809c; font-size: 8px; text-transform: uppercase; }
        .footer { position: absolute; right: 15mm; bottom: 8mm; left: 15mm; padding-top: 6px; border-top: 1px solid #dfe4ed; color: #8490a6; font-size: 8px; text-align: center; }
    </style>
</head>
<body>
@php
    $listeBulletins = isset($bulletins) ? $bulletins : collect([$bulletin]);
@endphp
@foreach ($listeBulletins as $bulletin)
@php
    $moisNom = match((int) $bulletin->mois) {
        1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
        5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
        9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
        default => 'Mois #'.$bulletin->mois
    };
@endphp
<div class="page" @if (! $loop->last) style="page-break-after: always;" @endif>
    <div class="header">
        <div class="header-left">
            <span class="brand">
                <strong>AMBASSADORS EDUCATIONAL COMPLEX</strong>
                <small>Direction des Ressources Humaines & Paie</small>
            </span>
        </div>
        <div class="header-right">
            <div class="doc-type">BULLETIN DE PAIE</div>
            <div class="doc-number">Période : {{ $moisNom }} {{ $bulletin->annee }}</div>
        </div>
    </div>
    <div class="accent"></div>

    <div class="content">
        <div class="meta">
            <div class="meta-col" style="padding-right:4mm">
                <div class="box">
                    <div class="label">Informations de l'Employé</div>
                    <div class="value">{{ $bulletin->employe?->nom_complet }}</div>
                    <div class="sub">Matricule : <strong>{{ $bulletin->employe?->matricule ?? 'N/A' }}</strong></div>
                    <div class="sub">Poste : {{ $bulletin->employe?->poste ?? 'Personnel' }}</div>
                    <div class="sub">Département : {{ $bulletin->employe?->departement ?? 'Général' }}</div>
                </div>
            </div>
            <div class="meta-col" style="padding-left:4mm">
                <div class="box">
                    <div class="label">Établissement & Contrat</div>
                    <div class="value">Ambassadors Educational Complex</div>
                    <div class="sub">Contrat #{{ $bulletin->contrat_id }} ({{ strtoupper($bulletin->contrat?->type ?? 'CDI') }})</div>
                    <div class="sub">Date d'embauche : {{ $bulletin->employe?->date_embauche?->format('d/m/Y') ?? 'N/A' }}</div>
                    <div class="sub">Statut bulletin : <span class="status-badge status-{{ $bulletin->statut }}">{{ strtoupper($bulletin->statut) }}</span></div>
                </div>
            </div>
        </div>

        <div class="info">
            <div class="info-cell"><span class="label">Salaire de Base</span><strong>{{ number_format((float) $bulletin->salaire_base, 0, ',', ' ') }} FCFA</strong></div>
            <div class="info-cell"><span class="label">Total Primes</span><strong>+ {{ number_format((float) $bulletin->total_primes, 0, ',', ' ') }} FCFA</strong></div>
            <div class="info-cell"><span class="label">Total Retenues</span><strong>- {{ number_format((float) $bulletin->total_retenues, 0, ',', ' ') }} FCFA</strong></div>
            <div class="info-cell"><span class="label">Date de Paiement</span><strong>{{ $bulletin->date_paiement?->format('d/m/Y') ?? 'Non réglé' }}</strong></div>
        </div>

        <table class="items">
            <thead>
                <tr>
                    <th>Rubrique de Paie</th>
                    <th>Type</th>
                    <th class="num">Gains / Primes</th>
                    <th class="num">Retenues / Cotisations</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Salaire de base contractuel</td>
                    <td>Gain de base</td>
                    <td class="num">{{ number_format((float) $bulletin->salaire_base, 0, ',', ' ') }} FCFA</td>
                    <td class="num">-</td>
                </tr>
                @if ($bulletin->total_primes > 0)
                <tr>
                    <td>Primes & Indemnités accordées</td>
                    <td>Prime / Avantage</td>
                    <td class="num">{{ number_format((float) $bulletin->total_primes, 0, ',', ' ') }} FCFA</td>
                    <td class="num">-</td>
                </tr>
                @endif
                @if ($bulletin->total_cotisations > 0)
                <tr>
                    <td>Cotisations sociales & fiscales (CNPS / IRPP)</td>
                    <td>Cotisation obligatoire</td>
                    <td class="num">-</td>
                    <td class="num">{{ number_format((float) $bulletin->total_cotisations, 0, ',', ' ') }} FCFA</td>
                </tr>
                @endif
                @if ($bulletin->avances_deduites > 0)
                <tr>
                    <td>Déduction d'avances sur salaire</td>
                    <td>Avance / Acompte</td>
                    <td class="num">-</td>
                    <td class="num">{{ number_format((float) $bulletin->avances_deduites, 0, ',', ' ') }} FCFA</td>
                </tr>
                @endif
                @if ($bulletin->total_retenues > 0 && $bulletin->total_retenues > ($bulletin->total_cotisations + $bulletin->avances_deduites))
                <tr>
                    <td>Autres retenues sur salaire</td>
                    <td>Retenue divers</td>
                    <td class="num">-</td>
                    <td class="num">{{ number_format((float) ($bulletin->total_retenues - $bulletin->total_cotisations - $bulletin->avances_deduites), 0, ',', ' ') }} FCFA</td>
                </tr>
                @endif
            </tbody>
        </table>

        <table class="totals">
            <tr>
                <td>Total Brut (Base + Primes)</td>
                <td class="amount">{{ number_format((float) ($bulletin->salaire_base + $bulletin->total_primes), 0, ',', ' ') }} FCFA</td>
            </tr>
            <tr>
                <td>Total Retenues</td>
                <td class="amount">- {{ number_format((float) $bulletin->total_retenues, 0, ',', ' ') }} FCFA</td>
            </tr>
            <tr class="grand">
                <td>Net à Payer</td>
                <td class="amount">{{ number_format((float) $bulletin->net_a_payer, 0, ',', ' ') }} FCFA</td>
            </tr>
        </table>

        <div class="signatures">
            <div class="signature"><div>Signature de l'Employé</div></div>
            <div class="signature"><div>Visa & Cachet de la Direction</div></div>
        </div>
    </div>

    <div class="footer">
        <strong>Ambassadors Educational Complex</strong> — Emana, Bonne Fontaine, Yaoundé, Cameroun<br>
        Ce bulletin de paie tenant lieu de décompte individuel est conservé sans limitation de durée.
    </div>
</div>
@endforeach
</body>
</html>
