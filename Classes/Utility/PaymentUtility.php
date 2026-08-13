<?php

namespace Imhlab\CartQuickpay\Utility;

use Extcode\Cart\Domain\Repository\CartRepository;
use QuickPay\QuickPay;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Mvc\Web\Request;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

class PaymentUtility
{

    /**
     * @var ObjectManager
     */
    protected $objectManager;

    /**
     * @var PersistenceManager
     */
    protected $persistenceManager;

    /**
     * @var ConfigurationManager
     */
    protected $configurationManager;

    /**
     * @var array
     */
    protected $conf = [];

    /**
     * @var array
     */
    protected $cartConf = [];

    /**
     * @var array
     */
    protected $paymentQuery = [];

    /**
     * @var \Extcode\Cart\Domain\Model\Order\Item
     */
    protected $orderItem = null;

    /**
     * Intitialize
     */
    public function __construct()
    {
        $this->objectManager = GeneralUtility::makeInstance(
            ObjectManager::class
        );
        $this->persistenceManager = $this->objectManager->get(
            PersistenceManager::class
        );
        $this->configurationManager = $this->objectManager->get(
            ConfigurationManager::class
        );

        $this->conf = $this->configurationManager->getConfiguration(
            \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
            'CartQuickpay'
        );

        $this->cartConf = $this->configurationManager->getConfiguration(
            \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
            'Cart'
        );
    }



    /**
     * @param array $params
     *
     * @return array
     */
    public function handlePayment(array $params): array
    {

        $this->orderItem = $params['orderItem'];
        
        list($provider, $type, $brand) = array_map('trim', explode('-', $this->orderItem->getPayment()->getProvider()));
        
        if ($provider === 'QUICKPAY') {
            $params['providerUsed'] = true;

            $cart = $this->objectManager->get(
                \Extcode\Cart\Domain\Model\Cart::class
            );
            $cart->setOrderItem($this->orderItem);
            $cart->setCart($params['cart']);
            $cart->setPid($this->cartConf['settings']['order']['pid']);

            $cartRepository = $this->objectManager->get(
                CartRepository::class
            );
            $cartRepository->add($cart);
            $this->persistenceManager->persistAll();

            try {
                //Initialize client
                $api_user = $this->conf['settings']['api_user'];
                
                $client = new QuickPay(":{$api_user}");

                $billingAddress = $this->orderItem->getBillingAddress()->toArray();

                //Create payment
                $payment = $client->request->post('/payments', [
                    'order_id' => $this->orderItem->getOrderNumber(),
                    'currency' => 'DKK',
                    // Umiddelbart bliver værdierne kun vist i Quickpay admin interfacet, men altså ikke på betalingssiden.
                    'invoice_address[name]' => $billingAddress['firstName'] .' '. $billingAddress['lastName'],
                    //'invoice_address[att]' => 'invoice.att',
                    'invoice_address[company_name]' => $billingAddress['company'],
                    'invoice_address[street]' => $billingAddress['street'],
                    //'invoice_address[house_number]' => $billingAddress['streetNumber'],
                    //'invoice_address[house_extension]' => 'invoice.house_extension',
                    'invoice_address[city]' => $billingAddress['city'],
                    'invoice_address[zip_code]' => $billingAddress['zip'],
                    //'invoice_address[region]' => 'invoice.region',
                    //'invoice_address[vat_no]' => 'invoice.vat_no',
                    'invoice_address[phone_number]' => $billingAddress['phone'],
                    //'invoice_address[mobile_number]' => 'invoice.mobile_number',
                    'invoice_address[email]' => $billingAddress['email'],
                    // 'shipping_address[name]' => $shippingAddress['firstName'] .' '. $shippingAddress['lastName'],
                    // 'shipping_address[att]' => 'shipping.att',
                    // 'shipping_address[company_name]' => $shippingAddress['company'],
                    // 'shipping_address[street]' => $shippingAddress['street'],
                    // 'shipping_address[house_number]' => 'shipping.house_number',
                    // 'shipping_address[house_extension]' => 'shipping.house_extension',
                    // 'shipping_address[city]' => $shippingAddress['city'],
                    // 'shipping_address[zip_code]' => $shippingAddress['zip'],
                    // 'shipping_address[region]' => 'shipping.region',
                    // 'shipping_address[vat_no]' => 'shipping.vat_no',
                    // 'shipping_address[phone_number]' => $shippingAddress['phone'],
                    // 'shipping_address[mobile_number]' => 'shipping.mobile_number',
                    // 'shipping_address[email]' => $shippingAddress['email'],
//                    'branding_id' => 'branding_id',
                    // 'basket[][qty]' => 'basket.qty',
//                    'basket[][item_no]' => 'basket.item_no',
//                    'basket[][item_name]' => 'basket.item_name',
//                    'basket[][item_price]' => 'basket.item_price',
//                    'basket[][vat_rate]' => 'basket.vat_rate',
                    // 'shipping[method]' => 'shipping.method',
//                    'shipping[company]' => 'shipping.company',
//                    'shipping[amount]' => 1,
//                    'shipping[vat_rate]' => 'shipping.vat_rate',
//                    'shipping[tracking_number]' => 'shipping.tracking_number',
//                    'shipping[tracking_url]' => 'shipping.tracking_url',
                    'shopsystem[name]' => 'extcode/Cart',
//                  'payment_methods' => 'dankort,mobilepay',
                    // 'shopsystem[version]' => 'shopsystem.version',
                    // 'variables' => 'variables',
                    // 'text_on_statement' => 'text_on_statement',

                ]);

                $status = $payment->httpStatus();

                //Determine if payment was created successfully
                if ($status === 201) {
                    $paymentObject = $payment->asObject();
            
                    //Construct url to create payment link
                    $endpoint = sprintf("/payments/%s/link", $paymentObject->id);
            
                    //Issue a put request to create payment link
                    $link = $client->request->put($endpoint, [
                        'amount' => $this->orderItem->getTotalGross()*100,
                        'continueurl' => $this->getUrl('success', $cart->getSHash()),
                        'cancel_url' => $this->getUrl('cancel', $cart->getFHash()),
//                      'payment_methods' => 'dankort,mobilepay',
//                        'callback_url' => $this->getUrl('confirm', $cart->getSHash())
                        ]);
            
                    //Determine if payment link was created succesfully
                    if ($link->httpStatus() === 200) {
                        //Get payment link url
                        $paymentPageURL = $link->asObject()->url;
                        header('Location: ' . $paymentPageURL);
                    }
                } else {
                    error_log('httpStatus: '. $status);
                    error_log('Quickpay parameterfejl i API ');
                    $failureURL = $this->getUrl('cancel', $cart->getFHash());
                    header('Location: ' . $failureURL);

//                    die("HTTPSTATUS: Error: call to API failed with status $status");
                }
            } catch (\Exception $e) {
                error_log('Exception catcher: ');
                error_log($e);
                die("EXCEPTION: Error: call to API failed with status $e");
            }
        }

        return [$params];
    }

    /**
     * Builds a return URL to Cart order controller action
     *
     * @param string $action
     * @param string $hash
     *
     * @return string
     */
    protected function getUrl($action, $hash): string
    {
        $pid = $this->cartConf['settings']['cart']['pid'];

        $arguments = [
            'tx_cartquickpay_cart' => [
                'controller' => 'Order\Payment',
                'order' => $this->orderItem->getUid(),
                'action' => $action,
                'hash' => $hash
            ]
        ];

        $uriBuilder = $this->getUriBuilder();

        return $uriBuilder->reset()
            ->setTargetPageUid($pid)
            ->setTargetPageType($this->conf['redirectTypeNum'])
            ->setCreateAbsoluteUri(true)
            ->setUseCacheHash(false)
            ->setArguments($arguments)
            ->build();
    }

    /**
     * @return UriBuilder
     */
    protected function getUriBuilder(): UriBuilder
    {
        $request = $this->objectManager->get(Request::class);
        $request->setRequestURI(GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL'));
        $request->setBaseURI(GeneralUtility::getIndpEnv('TYPO3_SITE_URL'));
        $uriBuilder = $this->objectManager->get(UriBuilder::class);
        $uriBuilder->setRequest($request);

        return $uriBuilder;
    }
}
