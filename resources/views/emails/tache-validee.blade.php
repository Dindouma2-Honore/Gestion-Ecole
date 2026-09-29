<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Tâche Validée</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f5f8; margin: 0; padding: 20px; color: #1e293b; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.06); }
        .header { background: linear-gradient(135deg, #059669 0%, #10b981 100%); padding: 25px 20px; text-align: center; color: #ffffff; }
        .header h1 { margin: 0; font-size: 20px; }
        .content { padding: 25px; line-height: 1.6; }
        .success-card { background: #ecfdf5; border-left: 4px solid #10b981; border-radius: 6px; padding: 15px 20px; margin: 15px 0; }
        .success-card p { margin: 6px 0; font-size: 14px; }
        .btn { display: inline-block; background: #059669; color: #ffffff !important; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: bold; }
        .footer { background: #f1f5f9; padding: 15px; text-align: center; font-size: 12px; color: #64748b; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Tâche Validée avec succès</h1>
        </div>
        <div class="content">
            <h2>Félicitations {{ $responsable->name }},</h2>
            <p>La tâche suivante a été définitivement validée :</p>
            
            <div class="success-card">
                <p><strong>Titre :</strong> {{ $tache->titre }}</p>
                <p><strong>Statut :</strong> <strong style="color: #059669;">Validée</strong></p>
                <p><strong>Date de validation :</strong> {{ now()->format('d/m/Y à H:i') }}</p>
            </div>

            <p style="text-align: center;">
                <a href="{{ config('app.url') }}/admin/taches/{{ $tache->id }}" class="btn">Consulter la tâche</a>
            </p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Ambassadors Educational Complex.
        </div>
    </div>
</body>
</html>
