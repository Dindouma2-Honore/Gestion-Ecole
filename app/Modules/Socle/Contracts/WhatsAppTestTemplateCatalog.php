<?php

namespace App\Modules\Socle\Contracts;

class WhatsAppTestTemplateCatalog
{
    public const CODES = [
        'inscription_validee', 'preinscription_recue', 'inscription_active',
        'paiement_recu', 'rappel_paiement', 'facture_disponible',
        'bulletin_publie', 'absence_eleve', 'retard_eleve',
        'nouveau_message_etablissement', 'rendez_vous_demande', 'rendez_vous_confirme',
        'rendez_vous_annule', 'annonce_etablissement', 'evenement_scolaire',
        'convocation_parent', 'incident_vie_scolaire', 'visite_infirmerie', 'sortie_eleve',
        'candidate_application_accepted', 'candidate_application_rejected', 'staff_account_created',
    ];
}
