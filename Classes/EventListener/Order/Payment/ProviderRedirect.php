<?php

declare(strict_types=1);

namespace Imhlab\CartQuickpay\EventListener\Order\Payment;

use Extcode\Cart\Domain\Model\Cart;
use Extcode\Cart\Domain\Model\Order\Item as OrderItem;
use Extcode\Cart\Domain\Repository\CartRepository;
use Extcode\Cart\Event\Order\PaymentEvent;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use QuickPay\QuickPay;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

/**
 * Replaces the former signal slot Extcode\Cart\Utility\PaymentUtility::handlePayment.
 *
 * Creates a QuickPay payment + payment link and redirects the customer to QuickPay.
 */
class ProviderRedirect
{
    protected array $conf = [];

    protected array $cartConf = [];

    protected ?OrderItem $orderItem = null;

    public function __construct(
        protected readonly PersistenceManager $persistenceManager,
        protected readonly CartRepository $cartRepository,
        protected readonly SiteFinder $siteFinder,
        protected readonly TypoScriptService $typoScriptService,
        protected readonly LoggerInterface $logger,
    ) {}

    public function __invoke(PaymentEvent $event): void
    {
        $this->orderItem = $event->getOrderItem();

        $payment = $this->orderItem->getPayment();
        if ($payment === null) {
            return;
        }

        [$provider] = array_pad(array_map('trim', explode('-', (string)$payment->getProvider())), 3, '');

        if ($provider !== 'QUICKPAY') {
            return;
        }

        $this->cartConf = $event->getSettings();
        $this->conf = $this->getQuickpayConfiguration();

        $cart = new Cart();
        $cart->setOrderItem($this->orderItem);
        $cart->setCart($event->getCart());
        $cart->setPid((int)($this->cartConf['settings']['order']['pid'] ?? 0));

        $this->cartRepository->add($cart);
        $this->persistenceManager->persistAll();

        // Cart must not continue with its own FinishEvent – this is done after the payment in PaymentController
        $event->setPropagationStopped(true);

        $redirectUrl = $this->getUrl('cancel', $cart->getFHash());

        try {
            $apiUser = (string)($this->conf['settings']['api_user'] ?? '');
            $client = new QuickPay(':' . $apiUser);

            $billingAddress = $this->orderItem->getBillingAddress()->toArray();

            $quickpayPayment = $client->request->post('/payments', [
                'order_id' => $this->orderItem->getOrderNumber(),
                'currency' => 'DKK',
                // Umiddelbart bliver værdierne kun vist i Quickpay admin interfacet, men altså ikke på betalingssiden.
                'invoice_address[name]' => trim(($billingAddress['firstName'] ?? '') . ' ' . ($billingAddress['lastName'] ?? '')),
                'invoice_address[company_name]' => $billingAddress['company'] ?? '',
                'invoice_address[street]' => $billingAddress['street'] ?? '',
                'invoice_address[city]' => $billingAddress['city'] ?? '',
                'invoice_address[zip_code]' => $billingAddress['zip'] ?? '',
                'invoice_address[phone_number]' => $billingAddress['phone'] ?? '',
                'invoice_address[email]' => $billingAddress['email'] ?? '',
                'shopsystem[name]' => 'extcode/Cart',
            ]);

            $status = $quickpayPayment->httpStatus();

            if ($status === 201) {
                $paymentObject = $quickpayPayment->asObject();

                $link = $client->request->put(sprintf('/payments/%s/link', $paymentObject->id), [
                    'amount' => (int)round($this->orderItem->getTotalGross() * 100),
                    'continueurl' => $this->getUrl('success', $cart->getSHash()),
                    'cancel_url' => $this->getUrl('cancel', $cart->getFHash()),
                ]);

                if ($link->httpStatus() === 200) {
                    $redirectUrl = $link->asObject()->url;
                } else {
                    $this->logger->error('QuickPay: creating payment link failed', ['httpStatus' => $link->httpStatus()]);
                }
            } else {
                $this->logger->error('QuickPay: parameter error in API call', ['httpStatus' => $status]);
            }
        } catch (\Throwable $e) {
            $this->logger->error('QuickPay: call to API failed', ['exception' => $e]);
        }

        throw new PropagateResponseException(new RedirectResponse($redirectUrl, 303), 1730900000);
    }

    /**
     * Builds a return URL to the payment controller of this extension
     */
    protected function getUrl(string $action, string $hash): string
    {
        $pid = (int)($this->cartConf['settings']['cart']['pid'] ?? 0);

        $parameters = [
            'type' => (int)($this->conf['redirectTypeNum'] ?? 0),
            'tx_cartquickpay_cart' => [
                'controller' => 'Order\Payment',
                'order' => $this->orderItem->getUid(),
                'action' => $action,
                'hash' => $hash,
            ],
        ];

        $language = $this->getRequest()?->getAttribute('language');
        if ($language instanceof SiteLanguage) {
            $parameters['_language'] = $language;
        }

        $site = $this->siteFinder->getSiteByPageId($pid);

        return (string)$site->getRouter()->generateUri($pid, $parameters);
    }

    protected function getQuickpayConfiguration(): array
    {
        $frontendTypoScript = $this->getRequest()?->getAttribute('frontend.typoscript');
        if (!$frontendTypoScript instanceof FrontendTypoScript) {
            return [];
        }

        $setup = $frontendTypoScript->getSetupArray();

        return $this->typoScriptService->convertTypoScriptArrayToPlainArray(
            $setup['plugin.']['tx_cartquickpay.'] ?? []
        );
    }

    protected function getRequest(): ?ServerRequestInterface
    {
        return $GLOBALS['TYPO3_REQUEST'] ?? null;
    }
}
