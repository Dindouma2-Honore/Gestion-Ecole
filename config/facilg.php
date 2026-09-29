<?php

return [
    /* Mapping volontairement explicite et éditable. Le premier motif correspondant gagne. */
    'niveaux_classes' => [
        'Maternelle' => ['DAY CARE', 'CRECHE', 'PRENURSERY', 'PRE-NURSERY', 'PRE-MATERNELLE', 'NURSERY', 'MOYENNE SECTION', 'GRANDE SECTION'],
        'Primaire' => ['SIL', 'COURS PREPARATOIRE', 'COURS ELEMENTAIRE', 'COURS MOYEN', 'CP', 'CE1', 'CE 1', 'CE2', 'CE 2', 'CM1', 'CM 1', 'CM2', 'CM 2', 'CLASS 1', 'CLASS 2', 'CLASS 3', 'CLASS 4', 'CLASS 5', 'CLASS 6'],
        'Secondaire' => ['6E', '5E', '4E', '3E', '2NDE', 'SECONDE', '1ERE', 'PREMIERE', 'TERMINALE', 'FORM 1', 'FORM 2', 'FORM 3', 'FORM 4', 'FORM 5', 'LOWER SIXTH', 'UPPER SIXTH'],
    ],
    'roles_utilisateurs' => [
        'ADMIN' => 'Fondateur',
        'DIR' => 'Directeur',
        'TEACH' => 'Enseignant',
    ],
    'codes_postes_a_reclasser' => ['ADASS', 'SEC'],
    'tables_exclues' => [
        'imag', 'initialisations', 'transactions', 'version', 'etsconservatif',
        'interfaces', 'userinterface', 'messagein', 'messageout', 'messageout1',
        'messageout2', 'messagelog', 'mail', 'confifportsms', 'parametremail',
    ],
];
