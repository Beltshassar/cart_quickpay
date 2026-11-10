<?php

declare(strict_types=1);

namespace Imhlab\CartQuickpay\Controller\Order;

use Extcode\Cart\Domain\Model\Cart;
use Extcode\Cart\Domain\Repository\CartRepository;
use Extcode\Cart\Domain\Repository\Order\PaymentRepository;
use Extcode\Cart\Event\Order\FinishEvent;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class PaymentController extends ActionController
{
    protected ?Cart $cart = null;

    protected array $cartPluginSettings = [];

    public function __construct(
        protected readonly PersistenceManager $persistenceManager,
        protected readonly CartRepository $cartRepository,
        protected readonly PaymentRepository $paymentRepository,
    ) {}

    protected function initializeAction(): void
    {
        $this->cartPluginSettings = $this->configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
            'Cart'
        );
    }

    public function confirmAction(): ResponseInterface
    {
        return $this->handleSuccess('confirm');
    }

    public function successAction(): ResponseInterface
    {
        return $this->handleSuccess('success');
    }

    public function cancelAction(): ResponseInterface
    {
        $hash = $this->getHashArgument();
        if ($hash === '') {
            $this->addErrorMessage('cancel', 'access_denied');
            return $this->htmlResponse();
        }

        $this->cart = $this->findCartByHash('fHash', $hash);
        if ($this->cart === null) {
            $this->addErrorMessage('cancel', 'error_occured');
            return $this->htmlResponse();
        }

        $orderItem = $this->cart->getOrderItem();
        $payment = $orderItem->getPayment();

        $payment->setStatus('canceled');

        $this->paymentRepository->update($payment);
        $this->persistenceManager->persistAll();

        $this->addFlashMessage(
            $this->translate('tx_cartquickpay.controller.order.payment.action.cancel.successfully_canceled'),
            '',
            ContextualFeedbackSeverity::OK
        );

        return $this->redirect('show', 'Cart\Cart', 'Cart', null, $this->getCartPid());
    }

    protected function handleSuccess(string $action): ResponseInterface
    {
        $hash = $this->getHashArgument();
        if ($hash === '') {
            $this->addErrorMessage($action, 'access_denied');
            return $this->htmlResponse();
        }

        $this->cart = $this->findCartByHash('sHash', $hash);
        if ($this->cart === null) {
            $this->addErrorMessage($action, 'error_occured');
            return $this->htmlResponse();
        }

        $orderItem = $this->cart->getOrderItem();
        $payment = $orderItem->getPayment();

        if ($payment->getStatus() !== 'paid') {
            $payment->setStatus('paid');

            $this->paymentRepository->update($payment);
            $this->persistenceManager->persistAll();

            // Replaces the former "finishers.order.success" of this extension:
            // EXT:cart sends mails, clears the cart etc. in listeners of the FinishEvent,
            // EXT:cart_products_usergroup assigns user groups in a listener of the same event.
            $cart = $this->cart->getCart();
            if ($cart !== null) {
                $finishEvent = new FinishEvent($cart, $orderItem, $this->cartPluginSettings);
                $this->eventDispatcher->dispatch($finishEvent);
            }
        }

        return $this->redirect('show', 'Cart\Order', 'Cart', ['orderItem' => $orderItem], $this->getCartPid());
    }

    protected function findCartByHash(string $property, string $hash): ?Cart
    {
        $querySettings = GeneralUtility::makeInstance(Typo3QuerySettings::class);
        $querySettings->setStoragePageIds([(int)($this->cartPluginSettings['settings']['order']['pid'] ?? 0)]);
        $this->cartRepository->setDefaultQuerySettings($querySettings);

        $cart = $this->cartRepository->findOneBy([$property => $hash]);

        return $cart instanceof Cart ? $cart : null;
    }

    protected function getHashArgument(): string
    {
        if (!$this->request->hasArgument('hash')) {
            return '';
        }
        return (string)$this->request->getArgument('hash');
    }

    protected function getCartPid(): ?int
    {
        $pid = (int)($this->cartPluginSettings['settings']['cart']['pid'] ?? 0);
        return $pid > 0 ? $pid : null;
    }

    protected function addErrorMessage(string $action, string $reason): void
    {
        $this->addFlashMessage(
            $this->translate('tx_cartquickpay.controller.order.payment.action.' . $action . '.' . $reason),
            '',
            ContextualFeedbackSeverity::ERROR
        );
    }

    protected function translate(string $key): string
    {
        return LocalizationUtility::translate($key, 'CartQuickpay') ?? $key;
    }
}
