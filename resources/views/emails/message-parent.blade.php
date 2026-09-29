<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Message aux Parents & Tuteurs</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f5f8; margin: 0; padding: 20px; color: #1e293b; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.06); }
        .header { background: linear-gradient(135deg, #07163f 0%, #1948bd 100%); padding: 30px 20px; text-align: center; color: #ffffff; }
        .header h1 { margin: 0; font-size: 22px; }
        .header p { margin: 5px 0 0; font-size: 13px; color: #f4cf67; letter-spacing: 1px; }
        .content { padding: 30px 25px; line-height: 1.6; }
        .msg-box { background: #f8fafc; border: 1.5px dashed #cbd5e1; border-radius: 8px; padding: 20px; margin: 20px 0; }
        .msg-box h3 { margin-top: 0; color: #0f172a; font-size: 17px; }
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
            <h2>Chers Parents & Tuteurs,</h2>
            <p>Vous avez reçu un nouveau message de la direction / de l'équipe pédagogique d'<strong>Ambassadors Educational Complex</strong>.</p>
            
            <div class="msg-box">
                <h3>Sujet : {{ $messageParent->sujet }}</h3>
                <p style="white-space: pre-line;">{{ $messageParent->contenu }}</p>
                <p style="font-size: 12px; color: #64748b; margin-bottom: 0;">
                    Expéditeur : {{ $messageParent->expediteur->name ?? 'L\'Établissement' }}
                </p>
            </div>

            <p>Nous restons à votre entière disposition pour tout renseignement complémentaire.</p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Ambassadors Educational Complex. Tous droits réservés.
        </div>
    </div>
</body>
</html>
