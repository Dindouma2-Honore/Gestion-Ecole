<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Compte Suspendu / Désactivé</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f5f8; margin: 0; padding: 20px; color: #1e293b; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.06); }
        .header { background: linear-gradient(135deg, #7f1d1d 0%, #dc2626 100%); padding: 30px 20px; text-align: center; color: #ffffff; }
        .header h1 { margin: 0; font-size: 22px; }
        .header p { margin: 5px 0 0; font-size: 13px; color: #fecaca; letter-spacing: 1px; }
        .content { padding: 30px 25px; line-height: 1.6; }
        .alert-box { background: #fef2f2; border-left: 4px solid #ef4444; border-radius: 6px; padding: 15px 20px; margin: 20px 0; color: #991b1b; }
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
            <p>Nous vous informons que le statut de votre compte utilisateur sur la plateforme d'<strong>Ambassadors Educational Complex</strong> a été modifié.</p>
            
            <div class="alert-box">
                <strong>Statut actuel de votre compte :</strong> {{ ucfirst($user->statut) }}<br>
                <p style="margin-top: 8px; margin-bottom: 0;">Conformément à la politique d'accès de l'établissement, vos accès à la plateforme et à votre espace utilisateur sont désormais temporairement ou définitivement restreints.</p>
            </div>

            <p>Si vous estimez qu'il s'agit d'une erreur ou si vous souhaitez obtenir des informations complémentaires, nous vous invitons à contacter la direction ou l'administration.</p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Ambassadors Educational Complex. Tous droits réservés.
        </div>
    </div>
</body>
</html>
