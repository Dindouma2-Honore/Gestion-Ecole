<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Demande de Validation de Tâche</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f5f8; margin: 0; padding: 20px; color: #1e293b; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.06); }
        .header { background: linear-gradient(135deg, #07163f 0%, #1948bd 100%); padding: 30px 20px; text-align: center; color: #ffffff; }
        .header h1 { margin: 0; font-size: 22px; }
        .header p { margin: 5px 0 0; font-size: 13px; color: #f4cf67; letter-spacing: 1px; }
        .content { padding: 30px 25px; line-height: 1.6; }
        .tache-box { background: #f8fafc; border: 1.5px dashed #cbd5e1; border-radius: 8px; padding: 15px 20px; margin: 20px 0; }
        .tache-box p { margin: 6px 0; font-size: 14px; }
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
            <h2>Bonjour {{ $validation->validateur->name ?? 'Responsable' }},</h2>
            <p>Une validation est en attente de votre décision pour la tâche suivante :</p>
            
            <div class="tache-box">
                <p><strong>Titre de la tâche :</strong> {{ $validation->tache->titre }}</p>
                <p><strong>Responsable exécutant :</strong> {{ $validation->tache->responsable->name ?? 'N/A' }}</p>
                <p><strong>Échéance :</strong> {{ $validation->tache->echeance ? $validation->tache->echeance->format('d/m/Y H:i') : 'Non définie' }}</p>
                <p><strong>Niveau de validation :</strong> Étape {{ $validation->niveau_validation }}</p>
                @if($validation->tache->description)
                    <p><strong>Description :</strong> {{ $validation->tache->description }}</p>
                @endif
            </div>

            <p>Veuillez vous connecter à la plateforme pour valider ou rejeter cette étape.</p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Ambassadors Educational Complex. Tous droits réservés.
        </div>
    </div>
</body>
</html>
