<?php

defined('TYPO3') or die('Access denied.');

use Imhlab\CartQuickpay\Controller\Order\PaymentController;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

// configure plugins

ExtensionUtility::configurePlugin(
    'CartQuickpay',
    'Cart',
    [
        PaymentController::class => 'confirm, success, cancel',
    ],
    // non-cacheable actions
    [
        PaymentController::class => 'confirm, success, cancel',
    ]
);

// The former signal slot (Extcode\Cart\Utility\PaymentUtility::handlePayment) is replaced by the
// PSR-14 event listener Imhlab\CartQuickpay\EventListener\Order\Payment\ProviderRedirect
// registered in Configuration/Services.yaml

// exclude parameters from cHash

$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = 'TID';
$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = 'LANGUAGE';
$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = 'USER_FIELD';
$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = 'BRAND';
$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = 'ERRTEXT';
$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = 'EXTERNALSTATUS';
