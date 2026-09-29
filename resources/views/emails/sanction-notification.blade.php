<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Notification de Sanction Disciplinaire</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f5f8; margin: 0; padding: 20px; color: #1e293b; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.06); }
        .header { background: linear-gradient(135deg, #854d0e 0%, #ca8a04 100%); padding: 30px 20px; text-align: center; color: #ffffff; }
        .header h1 { margin: 0; font-size: 22px; }
        .header p { margin: 5px 0 0; font-size: 13px; color: #fef08a; letter-spacing: 1px; }
        .content { padding: 30px 25px; line-height: 1.6; }
        .details-box { background: #fefce8; border: 1.5px dashed #fde047; border-radius: 8px; padding: 15px 20px; margin: 20px 0; color: #713f12; }
        .details-box p { margin: 6px 0; font-size: 14px; }
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
            <h2>Bonjour {{ $sanction->employe->nom_complet ?? 'Employé' }},</h2>
            <p>Une sanction disciplinaire a été enregistrée vous concernant au sein de l'établissement <strong>Ambassadors Educational Complex</strong>.</p>
            
            <div class="details-box">
                <p><strong>Type de sanction :</strong> {{ ucfirst($sanction->type) }}</p>
                <p><strong>Date d'effet :</strong> {{ $sanction->date_sanction ? $sanction->date_sanction->format('d/m/Y') : date('d/m/Y') }}</p>
                @if($sanction->duree_jours)
                    <p><strong>Durée :</strong> {{ $sanction->duree_jours }} jour(s)</p>
                @endif
                <p><strong>Motif :</strong> {{ $sanction->motif }}</p>
            </div>

            <p>Veuillez prendre les dispositions nécessaires. Pour toute contestation ou demande d'entretien, veuillez vous adresser à la direction des Ressources Humaines.</p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Ambassadors Educational Complex. Tous droits réservés.
        </div>
    </div>
</body>
</html>
