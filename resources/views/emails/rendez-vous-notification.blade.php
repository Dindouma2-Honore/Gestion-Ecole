<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Informations de Rendez-vous</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f5f8; margin: 0; padding: 20px; color: #1e293b; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.06); }
        .header { background: linear-gradient(135deg, #07163f 0%, #1948bd 100%); padding: 30px 20px; text-align: center; color: #ffffff; }
        .header h1 { margin: 0; font-size: 22px; }
        .header p { margin: 5px 0 0; font-size: 13px; color: #f4cf67; letter-spacing: 1px; }
        .content { padding: 30px 25px; line-height: 1.6; }
        .rdv-box { background: #f8fafc; border: 1.5px dashed #cbd5e1; border-radius: 8px; padding: 15px 20px; margin: 20px 0; }
        .rdv-box p { margin: 6px 0; font-size: 14px; }
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
            <h2>Bonjour {{ $rendezVous->parent->nom ?? '' }} {{ $rendezVous->parent->prenom ?? 'Parent/Tuteur' }},</h2>
            <p>Un <strong>rendez-vous</strong> a été programmé / mis à jour vous concernant au sein d'<strong>Ambassadors Educational Complex</strong>.</p>
            
            <div class="rdv-box">
                <p><strong>Motif du RDV :</strong> {{ $rendezVous->motif }}</p>
                <p><strong>Date & Heure demandée :</strong> {{ $rendezVous->date_heure_demandee ? $rendezVous->date_heure_demandee->format('d/m/Y à H:i') : 'Non renseignée' }}</p>
                @if($rendezVous->date_heure_confirmee)
                    <p><strong>Date & Heure confirmée :</strong> <span style="color: #16a34a; font-weight: bold;">{{ $rendezVous->date_heure_confirmee->format('d/m/Y à H:i') }}</span></p>
                @endif
                <p><strong>Statut :</strong> {{ ucfirst($rendezVous->statut ?? 'En demande') }}</p>
                @if($rendezVous->responsable)
                    <p><strong>Responsable référent :</strong> {{ $rendezVous->responsable->name }}</p>
                @endif
            </div>

            <p>Nous vous prions d'être ponctuel(le). En cas d'empêchement, merci de prévenir le secrétariat au plus tôt.</p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Ambassadors Educational Complex. Tous droits réservés.
        </div>
    </div>
</body>
</html>
