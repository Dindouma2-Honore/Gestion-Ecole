<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Contrat de Travail - {{ $contrat->employe?->nom_complet }}</title>
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #14213d; font: 11px/1.5 DejaVu Sans, sans-serif; }
        .page { position: relative; min-height: 297mm; padding-bottom: 25mm; }
        .header { display: table; width: 100%; padding: 10mm 15mm 8mm; background: #102858; color: #fff; }
        .header-left, .header-right { display: table-cell; vertical-align: middle; }
        .header-right { width: 35%; text-align: right; }
        .brand strong { display: block; font-size: 18px; letter-spacing: 0.5px; }
        .brand small { color: #e6bd4c; font-size: 9px; letter-spacing: 1.2px; text-transform: uppercase; }
        .doc-type { font-size: 20px; font-weight: bold; letter-spacing: 1.5px; }
        .doc-number { margin-top: 4px; color: #e6bd4c; font-size: 10px; font-weight: bold; }
        .accent { height: 4px; background: #d9ad3d; }
        .content { padding: 8mm 15mm 0; }
        .section-title { margin-top: 5mm; margin-bottom: 3mm; padding-bottom: 3px; border-bottom: 1.5px solid #102858; color: #102858; font-size: 12px; font-weight: bold; text-transform: uppercase; }
        p { margin-top: 0; margin-bottom: 3mm; text-align: justify; }
        .box { padding: 10px 12px; border-left: 3px solid #d9ad3d; border-radius: 4px; background: #f5f7fb; margin-bottom: 4mm; }
        .label { color: #71809c; font-size: 8px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; }
        .value { margin-top: 3px; color: #102858; font-size: 12px; font-weight: bold; }
        .signatures { display: table; width: 100%; margin-top: 20mm; }
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
                <small>Direction des Ressources Humaines</small>
            </span>
        </div>
        <div class="header-right">
            <div class="doc-type">CONTRAT DE TRAVAIL</div>
            <div class="doc-number">Réf : CONTRAT-{{ $contrat->id }}</div>
        </div>
    </div>
    <div class="accent"></div>

    <div class="content">
        <div class="section-title">Entre les soussignés :</div>
        <div class="box">
            <div class="label">L'Employeur</div>
            <div class="value">AMBASSADORS EDUCATIONAL COMPLEX</div>
            <div>Représenté par la Direction Générale, sis à Emana, Bonne Fontaine, Yaoundé, Cameroun.</div>
        </div>

        <div class="box">
            <div class="label">L'Employé(e)</div>
            <div class="value">{{ $contrat->employe?->nom_complet }}</div>
            <div>Matricule : <strong>{{ $contrat->employe?->matricule ?? 'N/A' }}</strong></div>
            <div>Poste : {{ $contrat->employe?->poste ?? 'Personnel' }}</div>
            <div>Adresse email : {{ $contrat->employe?->email ?? 'N/A' }}</div>
        </div>

        <div class="section-title">Article 1 — Engagement & Type de Contrat</div>
        <p>
            L'Employeur engage l'Employé(e) sous contrat de travail de type <strong>{{ strtoupper($contrat->type ?? 'CDI') }}</strong>
            à compter du <strong>{{ $contrat->date_debut?->format('d/m/Y') ?? date('d/m/Y') }}</strong>
            @if ($contrat->date_fin) jusqu'au <strong>{{ $contrat->date_fin->format('d/m/Y') }}</strong> @endif.
        </p>

        <div class="section-title">Article 2 — Fonctions & Rémunération</div>
        <p>
            L'Employé(e) exercera les fonctions de <strong>{{ $contrat->employe?->poste ?? 'Agent' }}</strong>.
            En contrepartie de son travail, l'Employé(e) percevra un salaire mensuel de base fixe de :
            <strong style="color:#102858; font-size:13px;">{{ number_format((float) $contrat->salaire_base, 0, ',', ' ') }} FCFA</strong>.
        </p>

        @if ($contrat->periode_essai_fin)
        <div class="section-title">Article 3 — Période d'essai</div>
        <p>
            Le présent contrat comporte une période d'essai venant à échéance le <strong>{{ $contrat->periode_essai_fin->format('d/m/Y') }}</strong>.
        </p>
        @endif

        <div class="section-title">Article 4 — Obligation de Réserve & Discipline</div>
        <p>
            L'Employé(e) s'engage à respecter le règlement intérieur de l'établissement et les règles d'éthique professionnelle en vigueur.
        </p>

        <div class="signatures">
            <div class="signature">
                <div>Lu et approuvé — L'Employé(e)</div>
            </div>
            <div class="signature">
                <div>Pour l'Établissement — La Direction</div>
            </div>
        </div>
    </div>

    <div class="footer">
        <strong>Ambassadors Educational Complex</strong> — Yaoundé, Cameroun<br>
        Fait en deux exemplaires originaux à Yaoundé, le {{ date('d/m/Y') }}.
    </div>
</div>
</body>
</html>
