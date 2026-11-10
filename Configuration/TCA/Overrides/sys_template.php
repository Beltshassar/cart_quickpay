<?php

defined('TYPO3') or die();

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile(
    'cart_quickpay',
    'Configuration/TypoScript',
    'Shopping Cart - QuickPay'
);
