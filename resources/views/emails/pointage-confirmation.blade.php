<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Notification de Pointage</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f5f8; margin: 0; padding: 20px; color: #1e293b; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.06); }
        .header { background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); padding: 30px 20px; text-align: center; color: #ffffff; }
        .header h1 { margin: 0; font-size: 22px; }
        .header p { margin: 5px 0 0; font-size: 13px; color: #bae6fd; letter-spacing: 1px; }
        .content { padding: 30px 25px; line-height: 1.6; }
        .info-box { background: #f0f9ff; border: 1.5px dashed #7dd3fc; border-radius: 8px; padding: 15px 20px; margin: 20px 0; color: #0c4a6e; }
        .info-box p { margin: 6px 0; font-size: 14px; }
        .footer { background: #f1f5f9; padding: 15px; text-align: center; font-size: 12px; color: #64748b; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Ambassadors Educational Complex</h1>
            <p>FOI · VISION · DISCIPLINE</p>
        </div>
        <div class="content">
            <h2>Notification Direction & Administration,</h2>
            <p>Un nouveau pointage d'assiduité a été enregistré dans le système.</p>
            
            <div class="info-box">
                <p><strong>Employé :</strong> {{ $pointage->employe->nom_complet ?? 'Employé' }} ({{ $pointage->employe->matricule ?? 'N/A' }})</p>
                <p><strong>Date :</strong> {{ $pointage->date_pointage ? $pointage->date_pointage->format('d/m/Y') : date('d/m/Y') }}</p>
                <p><strong>Heure d'arrivée :</strong> {{ $pointage->heure_arrivee ?? 'Non renseignée' }}</p>
                <p><strong>Heure de départ :</strong> {{ $pointage->heure_depart ?? 'En cours' }}</p>
                <p><strong>Mode de pointage :</strong> {{ ucfirst($pointage->mode_pointage ?? 'Manuel') }}</p>
                @if($pointage->correction_manuelle)
                    <p><strong>Correction manuelle :</strong> Oui (Motif: {{ $pointage->motif_correction ?? 'N/A' }})</p>
                @endif
            </div>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Ambassadors Educational Complex. Tous droits réservés.
        </div>
    </div>
</body>
</html>
