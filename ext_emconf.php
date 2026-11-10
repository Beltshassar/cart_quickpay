<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Cart - QuickPay',
    'description' => 'Shopping Cart(s) for TYPO3 - QuickPay Payment Provider',
    'category' => 'services',
    'author' => 'Daniel Damm & Martin Kristensen',
    'author_email' => 'dad@imh.dk',
    'author_company' => 'Indre Mission',
    'shy' => '',
    'priority' => '',
    'module' => '',
    'state' => 'beta',
    'internal' => '',
    'uploadfolder' => '0',
    'createDirs' => '',
    'modify_tables' => '',
    'clearCacheOnLoad' => 0,
    'lockType' => '',
    'version' => '0.0.1',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.4.99',
            'cart' => '11.0.0-11.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
