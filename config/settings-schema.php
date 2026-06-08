<?php

return [
    'reseau' => [
        'dns_suffix' => [
            'type' => 'text',
            'label' => 'Suffixe DNS',
            'default' => ''
        ]
    ],
    'msm' => [
        'debug_mode' => [
            'type' => 'checkbox',
            'label' => 'Mode debug',
            'default' => 'false'
        ]
    ],
    'inventaire' => [
        'target_types' => [
            'type' => 'textarea',
            'label' => 'Types de cibles',
            'default' => "linux=Linux\nwindows=Windows\nproxmox=Proxmox\nsynology=Synology\ndocker=Docker\nwebsite=Site web\nnetwork=Equipement reseau\nother=Autre"
        ],
        'environments' => [
            'type' => 'textarea',
            'label' => 'Environnements',
            'default' => "production=Production\nhomelab=Homelab\nstaging=Staging\ndevelopment=Developpement\ntest=Test\nother=Autre"
        ],
        'criticalities' => [
            'type' => 'textarea',
            'label' => 'Criticites',
            'default' => "low=Basse\nmedium=Moyenne\nhigh=Haute\ncritical=Critique"
        ],
        'collection_methods' => [
            'type' => 'textarea',
            'label' => 'Methodes de collecte',
            'default' => "ssh=SSH\nping=Ping uniquement\napi=API\nwinrm=WinRM\nmanual=Manuelle\nnone=Aucune"
        ]
    ],
    'patch_management' => [
        'check_interval_hours' => [
            'type' => 'number',
            'label' => 'Intervalle des checks patch management (heures)',
            'default' => '6'
        ]
    ],
    'os_lifecycle' => [
        'check_interval_hours' => [
            'type' => 'number',
            'label' => 'Intervalle des checks cycle de vie OS (heures)',
            'default' => '168'
        ]
    ],
    'security' => [
        'check_interval_hours' => [
            'type' => 'number',
            'label' => 'Intervalle des checks securite (heures)',
            'default' => '24'
        ]
    ],
    'alerting' => [
        'check_interval_minutes' => [
            'type' => 'number',
            'label' => 'Intervalle evaluation alerting (minutes)',
            'default' => '5'
        ]
    ]
];
