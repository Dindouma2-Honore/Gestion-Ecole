<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Nouvelle tâche assignée</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f5f8; margin: 0; padding: 20px; color: #1e293b; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.06); }
        .header { background: linear-gradient(135deg, #07163f 0%, #1948bd 100%); padding: 25px 20px; text-align: center; color: #ffffff; }
        .header h1 { margin: 0; font-size: 20px; }
        .content { padding: 25px; line-height: 1.6; }
        .task-card { background: #f8fafc; border-left: 4px solid #3b82f6; border-radius: 6px; padding: 15px 20px; margin: 15px 0; }
        .task-card p { margin: 6px 0; font-size: 14px; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 99px; font-size: 12px; font-weight: bold; background: #e2e8f0; color: #334155; }
        .btn { display: inline-block; background: #1948bd; color: #ffffff !important; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: bold; }
        .footer { background: #f1f5f9; padding: 15px; text-align: center; font-size: 12px; color: #64748b; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Nouvelle tâche attribuée</h1>
        </div>
        <div class="content">
            <h2>Bonjour {{ $responsable->name }},</h2>
            <p>Une nouvelle tâche vous a été attribuée sur la plateforme :</p>
            
            <div class="task-card">
                <p><strong>Titre :</strong> {{ $tache->titre }}</p>
                <p><strong>Échéance :</strong> {{ \Carbon\Carbon::parse($tache->echeance)->format('d/m/Y') }}</p>
                <p><strong>Priorité :</strong> <span class="badge">{{ ucfirst($tache->priorite) }}</span></p>
                @if($tache->description)
                    <p><strong>Description :</strong> {{ $tache->description }}</p>
                @endif
            </div>

            <p style="text-align: center;">
                <a href="{{ config('app.url') }}/admin/taches/{{ $tache->id }}" class="btn">Voir ma tâche</a>
            </p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Ambassadors Educational Complex.
        </div>
    </div>
</body>
</html>
