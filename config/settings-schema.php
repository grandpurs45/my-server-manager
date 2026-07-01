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
        'browser_title' => [
            'type' => 'text',
            'label' => 'Titre onglet navigateur',
            'default' => 'My Server Manager'
        ],
        'date_display_format' => [
            'type' => 'text',
            'label' => 'Format affichage dates',
            'default' => 'd/m/Y H:i:s'
        ],
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
            'default' => "linux=Linux\nwindows=Windows\nproxmox=Proxmox\nhome_assistant=Home Assistant\nsynology=Synology\ndocker=Docker\nwebsite=Site web\nnetwork=Equipement reseau\nother=Autre"
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
        ],
        'external_products' => [
            'type' => 'textarea',
            'label' => 'Familles synchronisables endoflife.date',
            'default' => "alpine=alpine\nubuntu=ubuntu\ndebian=debian\nrocky=rocky-linux"
        ]
    ],
    'security' => [
        'check_interval_hours' => [
            'type' => 'number',
            'label' => 'Intervalle des checks securite (heures)',
            'default' => '24'
        ]
    ],
    'hardware_health' => [
        'check_interval_minutes' => [
            'type' => 'number',
            'label' => 'Intervalle des checks sante materielle (minutes)',
            'default' => '15'
        ]
    ],
    'home_assistant' => [
        'check_interval_minutes' => [
            'type' => 'number',
            'label' => 'Intervalle des checks Home Assistant (minutes)',
            'default' => '15'
        ]
    ],
    'alerting' => [
        'check_interval_minutes' => [
            'type' => 'number',
            'label' => 'Intervalle evaluation alerting (minutes)',
            'default' => '5'
        ]
    ],
    'auth' => [
        'session_timeout_minutes' => [
            'type' => 'number',
            'label' => 'Duree de session inactive (minutes, 0 = pas d\'expiration)',
            'default' => '60'
        ],
        'password_min_length' => [
            'type' => 'number',
            'label' => 'Longueur minimale des mots de passe',
            'default' => '12'
        ],
        'password_require_uppercase' => [
            'type' => 'checkbox',
            'label' => 'Exiger une majuscule',
            'default' => 'true'
        ],
        'password_require_lowercase' => [
            'type' => 'checkbox',
            'label' => 'Exiger une minuscule',
            'default' => 'true'
        ],
        'password_require_digit' => [
            'type' => 'checkbox',
            'label' => 'Exiger un chiffre',
            'default' => 'true'
        ],
        'password_require_special' => [
            'type' => 'checkbox',
            'label' => 'Exiger un caractere special',
            'default' => 'true'
        ],
        'password_generator_length' => [
            'type' => 'number',
            'label' => 'Longueur du generateur de mot de passe',
            'default' => '18'
        ]
    ]
];
