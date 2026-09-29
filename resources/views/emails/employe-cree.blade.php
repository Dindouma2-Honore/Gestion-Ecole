<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Bienvenue au Personnel / Employé</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f5f8; margin: 0; padding: 20px; color: #1e293b; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.06); }
        .header { background: linear-gradient(135deg, #07163f 0%, #1948bd 100%); padding: 30px 20px; text-align: center; color: #ffffff; }
        .header h1 { margin: 0; font-size: 22px; }
        .header p { margin: 5px 0 0; font-size: 13px; color: #f4cf67; letter-spacing: 1px; }
        .content { padding: 30px 25px; line-height: 1.6; }
        .info-box { background: #f8fafc; border: 1.5px dashed #cbd5e1; border-radius: 8px; padding: 15px 20px; margin: 20px 0; }
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
            <h2>Bonjour {{ $employe->nom_complet }},</h2>
            <p>Nous avons le plaisir de vous informer que votre profil en tant que <strong>personnel administratif / employé</strong> a été créé avec succès dans le système d'<strong>Ambassadors Educational Complex</strong>.</p>
            
            <div class="info-box">
                <p><strong>Matricule :</strong> {{ $employe->matricule ?? 'Non attribué' }}</p>
                <p><strong>Poste :</strong> {{ $employe->poste ?? 'Non spécifié' }}</p>
                <p><strong>Département :</strong> {{ $employe->departement ?? 'Général' }}</p>
                <p><strong>Date d'embauche :</strong> {{ $employe->date_embauche ? $employe->date_embauche->format('d/m/Y') : date('d/m/Y') }}</p>
            </div>

            <p>Bienvenue au sein de notre équipe !</p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Ambassadors Educational Complex. Tous droits réservés.
        </div>
    </div>
</body>
</html>
