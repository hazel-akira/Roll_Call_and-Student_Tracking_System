<?php

/**
 * Canonical tenant schools for the roll-call platform.
 * Seeded via SchoolAndClassSeeder; codes are stable identifiers for APIs and Dynamics mapping.
 */
return [
    'tenants' => [
        [
            'name' => 'Pioneer School',
            'code' => 'PS',
            'level' => 'senior',
            'is_junior' => false,
        ],
        [
            'name' => 'Pioneer Girls School',
            'code' => 'PGS',
            'level' => 'senior',
            'is_junior' => false,
        ],
        [
            'name' => 'Pioneer Junior Academy',
            'code' => 'PJA',
            'level' => 'junior',
            'is_junior' => true,
        ],
        [
            'name' => 'Pioneer Girls Junior Academy',
            'code' => 'PGJA',
            'level' => 'junior',
            'is_junior' => true,
        ],
        [
            'name' => 'St Paul Thomas Academy',
            'code' => 'SPTA',
            'level' => 'primary',
            'is_junior' => false,
        ],
    ],

    /*
     * Exact ses_schoolname values in Dataverse when they differ from the local school name.
     */
    'dynamics_names' => [
        'PS' => 'Pioneer School',
        'PGS' => 'Pioneer Girls School',
        'PJA' => 'Pioneer Junior Academy',
        'PGJA' => 'Pioneer Girls Junior Academy',
        'SPTA' => 'St. Paul Thomas Academy',
    ],

    /*
     * Allowed student email domains per school code (lowercase).
     * Students with a different domain are excluded when loading from Dataverse.
     * Records without an email are kept.
     */
    'email_domains' => [
        'PS' => ['pioneerschools.ac.ke'],
        'PGS' => ['pioneergirlsschool.co.ke'],
        'PJA' => ['pioneerjunioracademy.co.ke'],
        'PGJA' => ['pioneergirlsjunioracademy.ac.ke'],
        'SPTA' => ['stpaulthomasacademy.co.ke'],
    ],
];
