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
            'typo3' => '9.5.0-10.4.99',
            'cart' => '6.3.0-8.6.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
