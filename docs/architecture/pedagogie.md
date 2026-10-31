# Pédagogie

## Objectif

Pédagogie organise et exécute l’enseignement : offre annuelle, affectations, programmes, emplois du temps, séances, appels, évaluations, notes, résultats, bulletins et décisions pédagogiques.

## Code

- **Organisation** : `Matiere`, `CategorieMatiere`, `OffrePedagogique`, `AffectationPedagogique`, `OrganisationCycle` ; services `CatalogueMatiereService`, `OffrePedagogiqueService`, `AffectationPedagogiqueService`, `OrganisationCycleService` et `OrganisationPedagogiqueContext`.
- **Programmes/progression** : `Programme`, `ProgrammeChapitre`, `Progression`, `DevoirPedagogique` ; services d’accès, d’import, de structure, d’extraction, d’analyse, de calcul et de suivi ; job `AnalyseProgramme`.
- **Planification/exécution** : `CreneauHoraire`, `EmploiDuTemps`, `Seance`, `Presence`, `AnomalieAppel` ; services emploi du temps, séances, présences et détection d’anomalies.
- **Évaluation/résultats** : `Evaluation`, `Note`, `Bulletin`, `BulletinMatiere`, `ConseilClasse`, `DecisionConseil` ; services d’évaluation, notes et bulletins.
- **Filament** : tableau de bord, Organisation pédagogique, Programmes & progression, Emplois du temps, Séances & appels, Évaluations & notes, Résultats & bulletins. Les tables utilisent le socle visuel commun et les modales partagées.

## Communications

RH fournit l’identité et le statut des enseignants. Scolarité fournit classes et inscriptions. Socle fournit année, référentiels, calendrier, documents, audit et permissions. Logistique fournit les salles. Communication est notifiée lors de la publication de bulletins.

## Flux programme et progression

`Utilisateur → Employé RH → Enseignant → Affectation → Offre autorisée → import privé → job d’analyse → correction humaine → validation → séance réalisée → progression → calcul → suivi`.

Le fichier source est conservé en document privé. Chaque import crée une version. La progression est liée à une séance, une classe et un élément de programme ; seuls les éléments terminés alimentent le pourcentage. Les documents sans texte restent en échec d’analyse afin de permettre un OCR ultérieur.
