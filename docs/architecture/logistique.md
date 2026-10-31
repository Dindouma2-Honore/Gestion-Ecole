# Logistique

## Objectif

Logistique gère les espaces, équipements, maintenance, bibliothèque, cantine, transport et événements matériels de l’établissement.

## Code

- **Modèles** : `Salle`, `Equipement`, `DemandeIntervention`, `PlanMaintenancePreventive`, `TravauxInfrastructure`, `Livre`, `ExemplaireLivre`, `Emprunt`, `MenuCantine`, `AbonnementCantine`, `Vehicule`, `CircuitTransport`, `ArretCircuit`, `InscriptionTransport`, `Evenement` et participants.
- **Services/contrats** : infrastructure, équipements, maintenance, bibliothèque, cantine, transport et événements.
- **Filament** : salles, équipements, travaux, maintenance, bibliothèque, cantine, transport et événements.

## Communications

Pédagogie utilise `InfrastructureServiceInterface` pour sélectionner les salles d’emploi du temps et prévenir les conflits. Scolarité peut fournir les élèves concernés par cantine ou transport. Le Socle fournit documents, audit et habilitations.
