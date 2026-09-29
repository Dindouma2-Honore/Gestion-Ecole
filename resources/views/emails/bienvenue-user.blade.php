<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Bienvenue sur la plateforme Ambassadors</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f5f8; margin: 0; padding: 20px; color: #1e293b; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.06); }
        .header { background: linear-gradient(135deg, #07163f 0%, #1948bd 100%); padding: 30px 20px; text-align: center; color: #ffffff; }
        .header h1 { margin: 0; font-size: 22px; }
        .header p { margin: 5px 0 0; font-size: 13px; color: #f4cf67; letter-spacing: 1px; }
        .content { padding: 30px 25px; line-height: 1.6; }
        .credentials-box { background: #f8fafc; border: 1.5px dashed #cbd5e1; border-radius: 8px; padding: 15px 20px; margin: 20px 0; }
        .credentials-box p { margin: 6px 0; font-size: 14px; }
        .alert-badge { background: #eff6ff; color: #1e40af; border-left: 4px solid #3b82f6; padding: 12px 15px; border-radius: 4px; font-size: 13px; margin-top: 15px; }
        .btn { display: inline-block; background: #1948bd; color: #ffffff !important; padding: 12px 25px; border-radius: 6px; text-decoration: none; font-weight: bold; margin-top: 20px; }
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
            <h2>Bonjour {{ $user->name }},</h2>
            <p>Votre compte a été créé avec succès sur la plateforme de gestion d'<strong>Ambassadors Educational Complex</strong>.</p>
            
            <p>Voici votre identifiant d'accès :</p>
            <div class="credentials-box">
                <p><strong>Identifiant (Email) :</strong> {{ $user->email }}</p>
            </div>

            <div class="alert-badge">
                <strong>Information de connexion :</strong> Pour des raisons de sécurité, votre mot de passe de connexion vous sera transmis directement par l'administration ou par téléphone.
            </div>

            <p style="text-align: center;">
                <a href="{{ config('app.url') }}/admin/login" class="btn">Accéder à la plateforme</a>
            </p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Ambassadors Educational Complex. Tous droits réservés.
        </div>
    </div>
</body>
</html>
