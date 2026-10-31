# Compatibilité du référentiel scolaire après migration des consommateurs

Les consommateurs de production externes à Scolarité utilisent les Contracts publics.
Le port ReferentielScolaireReadContract, son service et son binding ont été supprimés.
ParametrageService conserve ses points d'entrée de compatibilité, mais retourne les DTO
Scolarité (y compris la liste indexée par ID), sans modèle Eloquent du référentiel.

Reunion, JourFerie et Employe conservent niveau_id. Leur propriété niveau est désormais
un accesseur retournant AcademicStructureItemDTO|null via AcademicStructureReadContract.
Ce n'est plus une relation Eloquent : ne pas appeler with/loadMissing/relationship sur
niveau. Les sélecteurs utilisent CatalogContract et les vues utilisent DTO.name.
Les autres relations Eloquent de ces modèles restent inchangées.

OrganisationPedagogiqueContext consomme CatalogContract et traduit localement les DTO
vers les tableaux scalaires attendus par ses composants Pédagogie existants. Aucun
modèle ne traverse cette frontière.

L'import FACILG utilise Catalog/Read/Management. Les opérations niveauByLegacyId et
importNiveau conservent l'identifiant facilg_legacy_id, la description, l'idempotence
et la transaction de simulation existants, sans accès externe à la table niveaux.

Les quatre wrappers Socle ont été supprimés après migration de LocalDevelopmentSeeder,
des fixtures et des tests vers les modèles Scolarité. L'ordre d'insertion et les données
du seeder restent inchangés. Les tests de frontière n'autorisent plus aucun wrapper.
Aucune violation temporaire du lot 13 ne subsiste. Les migrations historiques restent intactes.
