<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Information Congé</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f5f8; margin: 0; padding: 20px; color: #1e293b; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.06); }
        .header { background: linear-gradient(135deg, #0369a1 0%, #0284c7 100%); padding: 30px 20px; text-align: center; color: #ffffff; }
        .header h1 { margin: 0; font-size: 22px; }
        .header p { margin: 5px 0 0; font-size: 13px; color: #bae6fd; letter-spacing: 1px; }
        .content { padding: 30px 25px; line-height: 1.6; }
        .info-box { background: #f0f9ff; border: 1.5px dashed #7dd3fc; border-radius: 8px; padding: 15px 20px; margin: 20px 0; color: #0c4a6e; }
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
            <h2>Bonjour {{ $conge->employe->nom_complet ?? 'Employé' }},</h2>
            <p>Un enregistrement relatif à votre <strong>demande de congé</strong> a été créé ou mis à jour au sein d'<strong>Ambassadors Educational Complex</strong>.</p>
            
            <div class="info-box">
                <p><strong>Type de congé :</strong> {{ ucfirst($conge->type ?? 'Congé payé') }}</p>
                <p><strong>Date de début :</strong> {{ $conge->date_debut ? $conge->date_debut->format('d/m/Y') : date('d/m/Y') }}</p>
                <p><strong>Date de fin :</strong> {{ $conge->date_fin ? $conge->date_fin->format('d/m/Y') : date('d/m/Y') }}</p>
                <p><strong>Nombre de jours :</strong> {{ $conge->nombre_jours }} jour(s)</p>
                <p><strong>Statut actuel :</strong> {{ ucfirst($conge->statut ?? 'En attente') }}</p>
            </div>

            <p>Vous serez notifié(e) de toute mise à jour ultérieure concernant la validation de ce congé.</p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Ambassadors Educational Complex. Tous droits réservés.
        </div>
    </div>
</body>
</html>
