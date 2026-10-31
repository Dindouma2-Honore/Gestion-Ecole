# Technique

## Objectif

Technique fournit la supervision opérationnelle de l’ERP : contrôle de disponibilité, mesures techniques et protection d’accès aux outils d’exploitation.

## Code

- **Modèle** : `TechnicalMeasurement`.
- **Service/contrat** : `MonitoringService` et `MonitoringServiceContract`.
- **Job** : `WorkerHeartbeat`, qui confirme l’activité des workers.
- **Accès** : `TechnicalAccess` limite l’accès à la supervision.

## Communications

Le module ne devient pas propriétaire des données métiers. Il lit les signaux nécessaires au contrôle de santé du système et s’appuie sur le Socle pour l’identité et les habilitations.
