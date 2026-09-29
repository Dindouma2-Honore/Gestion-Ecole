<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Invitation Réunion</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f5f8; margin: 0; padding: 20px; color: #1e293b; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.06); }
        .header { background: linear-gradient(135deg, #07163f 0%, #1948bd 100%); padding: 25px 20px; text-align: center; color: #ffffff; }
        .header h1 { margin: 0; font-size: 20px; }
        .content { padding: 25px; line-height: 1.6; }
        .details-card { background: #eff6ff; border: 1px solid #dbeafe; border-radius: 8px; padding: 15px 20px; margin: 15px 0; }
        .details-card p { margin: 6px 0; font-size: 14px; }
        .btn { display: inline-block; background: #1948bd; color: #ffffff !important; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: bold; }
        .footer { background: #f1f5f9; padding: 15px; text-align: center; font-size: 12px; color: #64748b; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Invitation à une réunion</h1>
        </div>
        <div class="content">
            <h2>Bonjour {{ $participant->name }},</h2>
            <p>Vous êtes convié(e) à participer à la réunion suivante :</p>
            
            <div class="details-card">
                <p><strong>Titre :</strong> {{ $reunion->titre }}</p>
                <p><strong>Date & Heure :</strong> {{ \Carbon\Carbon::parse($reunion->date_heure)->format('d/m/Y à H:i') }}</p>
                @if($reunion->lieu)
                    <p><strong>Lieu :</strong> {{ $reunion->lieu }}</p>
                @endif
                @if($reunion->description)
                    <p><strong>Description :</strong> {{ $reunion->description }}</p>
                @endif
            </div>

            <p style="text-align: center;">
                <a href="{{ config('app.url') }}/admin/reunions/{{ $reunion->id }}" class="btn">Consulter la réunion</a>
            </p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Ambassadors Educational Complex.
        </div>
    </div>
</body>
</html>
