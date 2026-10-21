<?php

/**
 * Where a context keeps what is found by convention. Everything is a namespace: the directories follow from PSR-4.
 */
return [
    'namespace' => 'App\\Providers\\BoundedContexts',

    'layout' => [
        'commands' => 'Application\\Command',
        'queries' => 'Infrastructure\\Query',
        'processors' => 'Infrastructure\\Processors',
        'projectors' => 'Infrastructure\\Projection',
        'contracts' => 'Application\\Integration',
        'consumed' => 'Infrastructure\\Consumed',
    ],
];
