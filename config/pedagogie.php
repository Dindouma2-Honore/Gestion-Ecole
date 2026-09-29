<?php

return [
    'arrondi_decimales' => (int) env('PEDAGOGIE_ARRONDI_DECIMALES', 2),
    'mentions' => [
        16 => "Tableau d'honneur — Félicitations",
        14 => "Tableau d'honneur — Encouragements",
        12 => 'Satisfaisant',
        10 => 'Passable',
        0 => 'Avertissement travail',
    ],
    'appreciations' => [
        16 => 'Excellent',
        14 => 'Très bien',
        12 => 'Bien',
        10 => 'Assez bien',
        8 => 'Insuffisant',
        0 => 'Très insuffisant',
    ],
];
