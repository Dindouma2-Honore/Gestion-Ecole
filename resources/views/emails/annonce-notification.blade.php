<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Nouvelle Annonce</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f5f8; margin: 0; padding: 20px; color: #1e293b; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.06); }
        .header { background: linear-gradient(135deg, #07163f 0%, #1948bd 100%); padding: 30px 20px; text-align: center; color: #ffffff; }
        .header h1 { margin: 0; font-size: 22px; }
        .header p { margin: 5px 0 0; font-size: 13px; color: #f4cf67; letter-spacing: 1px; }
        .content { padding: 30px 25px; line-height: 1.6; }
        .annonce-box { background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 20px; margin: 20px 0; }
        .annonce-box h3 { margin-top: 0; color: #1e293b; font-size: 18px; }
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
            <h2>Chers membres de la communauté,</h2>
            <p>Une nouvelle annonce importante a été publiée sur la plateforme d'<strong>Ambassadors Educational Complex</strong>.</p>
            
            <div class="annonce-box">
                <h3>{{ $annonce->titre }}</h3>
                <p style="white-space: pre-line;">{{ $annonce->contenu }}</p>
                <p style="font-size: 12px; color: #64748b; margin-bottom: 0;">
                    Publiée par : {{ $annonce->auteur->name ?? 'La Direction' }} | Date : {{ $annonce->created_at ? $annonce->created_at->format('d/m/Y') : date('d/m/Y') }}
                </p>
            </div>

            <p>Nous vous invitons à consulter la plateforme pour plus de détails.</p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Ambassadors Educational Complex. Tous droits réservés.
        </div>
    </div>
</body>
</html>
