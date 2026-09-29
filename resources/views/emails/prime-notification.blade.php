<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Prime & Gratification</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f5f8; margin: 0; padding: 20px; color: #1e293b; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.06); }
        .header { background: linear-gradient(135deg, #15803d 0%, #16a34a 100%); padding: 30px 20px; text-align: center; color: #ffffff; }
        .header h1 { margin: 0; font-size: 22px; }
        .header p { margin: 5px 0 0; font-size: 13px; color: #bbf7d0; letter-spacing: 1px; }
        .content { padding: 30px 25px; line-height: 1.6; }
        .info-box { background: #f0fdf4; border: 1.5px dashed #4ade80; border-radius: 8px; padding: 15px 20px; margin: 20px 0; color: #14532d; }
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
            <h2>Félicitations {{ $prime->employe->nom_complet ?? 'Employé' }},</h2>
            <p>Nous avons le plaisir de vous informer qu'une <strong>prime / gratification</strong> a été enregistrée en votre faveur par la direction d'<strong>Ambassadors Educational Complex</strong>.</p>
            
            <div class="info-box">
                <p><strong>Type de prime :</strong> {{ $prime->typePrime->libelle ?? 'Prime exceptionnelle' }}</p>
                <p><strong>Montant :</strong> {{ number_format((float)$prime->montant, 0, ',', ' ') }} FCFA</p>
                <p><strong>Période :</strong> {{ $prime->mois }}/{{ $prime->annee }}</p>
                @if($prime->justification)
                    <p><strong>Justification :</strong> {{ $prime->justification }}</p>
                @endif
            </div>

            <p>La direction vous remercie pour vos efforts et votre dévouement au sein de l'établissement.</p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Ambassadors Educational Complex. Tous droits réservés.
        </div>
    </div>
</body>
</html>
