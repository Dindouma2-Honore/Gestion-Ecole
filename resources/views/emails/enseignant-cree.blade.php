<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Bienvenue dans le Corps Enseignant</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f5f8; margin: 0; padding: 20px; color: #1e293b; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.06); }
        .header { background: linear-gradient(135deg, #0f766e 0%, #0d9488 100%); padding: 30px 20px; text-align: center; color: #ffffff; }
        .header h1 { margin: 0; font-size: 22px; }
        .header p { margin: 5px 0 0; font-size: 13px; color: #99f6e4; letter-spacing: 1px; }
        .content { padding: 30px 25px; line-height: 1.6; }
        .info-box { background: #f0fdf4; border: 1.5px dashed #86efac; border-radius: 8px; padding: 15px 20px; margin: 20px 0; color: #166534; }
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
            <h2>Bonjour {{ $enseignant->employe->nom_complet ?? 'Cher Enseignant' }},</h2>
            <p>Nous avons le plaisir de vous souhaiter la bienvenue au sein du <strong>corps enseignant</strong> d'<strong>Ambassadors Educational Complex</strong>.</p>
            
            <div class="info-box">
                <p><strong>Spécialité :</strong> {{ $enseignant->specialite ?? 'Générale' }}</p>
                <p><strong>Statut contractuel :</strong> {{ ucfirst($enseignant->statut_contractuel ?? 'Titulaire') }}</p>
                @if($enseignant->charge_horaire_hebdo)
                    <p><strong>Charge horaire hebdomadaire :</strong> {{ $enseignant->charge_horaire_hebdo }} heure(s)</p>
                @endif
            </div>

            <p>Nous vous remercions de votre engagement au service de l'excellence académique de nos élèves.</p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Ambassadors Educational Complex. Tous droits réservés.
        </div>
    </div>
</body>
</html>
