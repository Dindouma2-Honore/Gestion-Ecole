# Modales de consultation des tableaux — avancement local

Les ressources raccordées à `App\Filament\Support\RecordModal` ouvrent une consultation depuis la ligne du tableau. Les boutons Modifier et Supprimer réutilisent les actions déjà présentes dans ce tableau, avec leurs autorisations, validations et confirmations. Aucune nouvelle opération de suppression n'est introduite.

Le formulaire de modification conserve ses dimensions du socle. Les modèles WhatsApp passent par le même service que la page d'édition. La résolution d'une anomalie d'assiduité conserve l'auteur et la date de résolution.

## Reste à traiter

Le contrôle automatique a refusé les adaptations transversales suivantes. Elles ne sont pas appliquées :

- Scolarité : classes, catégories de frais, frais et grilles tarifaires. Il faut partager leurs traitements d'édition existants avec les modales, en testant les configurations liées aux classes et les appels au service de frais.
- Pédagogie : notes, emplois du temps, offres et affectations. Il faut conserver les contrôles des notes verrouillées, les vérifications des cours et la normalisation des affectations.
- Consultation des tableaux sans formulaire : assiduité journalière, pointages bruts, factures de préinscription, mouvements de caisse, relances, factures et paiements de Scolarité, anomalies d'appel, bulletins, présences et séances. Leur présentation précédente est conservée pour éviter une modale vide. Prévoir une fiche explicite en lecture seule.
- Les ressources dont les actions Modifier/Supprimer existent uniquement sur des pages dédiées nécessitent une adaptation explicite avant que ces boutons soient proposés dans le tableau.
- Les interfaces personnalisées du Socle/Administration et les écrans techniques ne sont pas modifiés par cette extension.

## Vérifications réalisées

- Test Livewire : consultation, modification et confirmation de suppression d'un véhicule.
- Test Livewire : résolution d'une anomalie et conservation de son auteur/date.
- Tests RH de modales conservés.
- Suites Logistique, Vie scolaire et Assiduité ; tests financiers d'allocation et de balance.
- Vérification de rendu des listes raccordées et ouverture des consultations lorsqu'un enregistrement local existe, sans sauvegarde.

Deux tests de la suite de cycle de vie WhatsApp échouent sur les restrictions des destinataires et le nombre de modèles attendus (33 attendus, 36 présents). Le service correspondant n'a pas été modifié par cette extension. Ces échecs restent à analyser séparément.
