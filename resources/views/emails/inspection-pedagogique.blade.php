<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Inspection Pédagogique</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f5f8; margin: 0; padding: 20px; color: #1e293b; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.06); }
        .header { background: linear-gradient(135deg, #1e1b4b 0%, #4338ca 100%); padding: 30px 20px; text-align: center; color: #ffffff; }
        .header h1 { margin: 0; font-size: 22px; }
        .header p { margin: 5px 0 0; font-size: 13px; color: #c7d2fe; letter-spacing: 1px; }
        .content { padding: 30px 25px; line-height: 1.6; }
        .info-box { background: #eef2ff; border: 1.5px dashed #a5b4fc; border-radius: 8px; padding: 15px 20px; margin: 20px 0; color: #312e81; }
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
            <h2>Bonjour {{ $inspection->enseignant->employe->nom_complet ?? 'Enseignant' }},</h2>
            <p>Une nouvelle rapport d'<strong>inspection pédagogique</strong> vous concernant a été enregistré dans le système.</p>
            
            <div class="info-box">
                <p><strong>Date d'inspection :</strong> {{ $inspection->date_inspection ? $inspection->date_inspection->format('d/m/Y') : date('d/m/Y') }}</p>
                <p><strong>Inspecteur :</strong> {{ $inspection->inspecteur->name ?? 'Direction Pédagogique' }}</p>
                @if($inspection->note)
                    <p><strong>Note attribuée :</strong> {{ $inspection->note }} / 20</p>
                @endif
                @if($inspection->observations)
                    <p><strong>Observations :</strong> {{ $inspection->observations }}</p>
                @endif
                @if($inspection->recommandations)
                    <p><strong>Recommandations :</strong> {{ $inspection->recommandations }}</p>
                @endif
            </div>

            <p>Vous pouvez consulter votre compte ou vous rapprocher de la direction pédagogique pour toute question.</p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Ambassadors Educational Complex. Tous droits réservés.
        </div>
    </div>
</body>
</html>
