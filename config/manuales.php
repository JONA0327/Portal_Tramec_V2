<?php

// Manuales y catálogos: los mismos enlaces de https://tramec.mx/descargas (cada uno descarga el PDF).
// Estructura: sección => [ grupo => [ ['titulo' => ..., 'tamano' => ..., 'slug' => ...], ... ] ].
return [
    'base_url' => 'https://tramec.mx/descargas/',

    'secciones' => [
        'Delta Electronics' => [
            'Variadores de frecuencia EL' => [
                ['Catálogo general', '2.6 MB', 'delta-el-catalogo'],
                ['Manual técnico', '10.5 MB', 'delta-el-manual'],
                ['Dimensiones', '301 KB', 'delta-el-dimensiones'],
            ],
            'Variadores de frecuencia MS300' => [
                ['Catálogo general', '8.4 MB', 'delta-ms300-catalogo'],
                ['Manual técnico', '30.8 MB', 'delta-ms300-manual'],
                ['Dimensiones', '827 KB', 'delta-ms300-dimensiones'],
                ['Resistencias de frenado', '651 KB', 'delta-ms300-resistencias'],
            ],
            'Variadores de frecuencia C2000 Plus' => [
                ['Catálogo general', '7.5 MB', 'delta-c2000-catalogo'],
                ['Manual técnico', '90.4 MB', 'delta-c2000-manual'],
                ['Dimensiones', '10.2 MB', 'delta-c2000-dimensiones'],
                ['Resistencias de frenado', '815 KB', 'delta-c2000-resistencias'],
            ],
            'HMI DOP-100' => [
                ['Catálogo general DOP-100', '3.4 MB', 'delta-hmi-catalogo'],
                ['Manual DOPSoft', '59.9 MB', 'delta-hmi-dopsoft'],
            ],
            'PLC DVP' => [
                ['Catálogo general', '4.3 MB', 'delta-plc-dvp-catalogo'],
                ['Manual de módulos', '44.4 MB', 'delta-plc-dvp-modulos'],
                ['Manual de programación', '22.4 MB', 'delta-plc-dvp-programacion'],
                ['Manual ISPSoft', '30.7 MB', 'delta-plc-ispsoft'],
            ],
            'PLC AS' => [
                ['Catálogo general', '10.3 MB', 'delta-plc-as-catalogo'],
                ['Manual de operación', '34.7 MB', 'delta-plc-as-operacion'],
                ['Manual de programación', '22.6 MB', 'delta-plc-as-programacion'],
                ['Manual de módulos', '33.4 MB', 'delta-plc-as-modulos'],
            ],
            'Servos ASD-A2' => [
                ['Catálogo general', '9.7 MB', 'delta-servo-catalogo'],
                ['Manual de usuario', '19.8 MB', 'delta-servo-manual'],
            ],
        ],

        'Transmisión de Potencia' => [
            'Motorreductores STM Riduttori' => [
                ['Reductores sinfín corona', '3.0 MB', 'stm-sinfin-corona'],
                ['Motores eléctricos Vela', '427 KB', 'stm-motores-vela'],
            ],
            'Motores OMEC' => [
                ['Catálogo general', '4.1 MB', 'omec-motores-catalogo'],
            ],
        ],
    ],
];
