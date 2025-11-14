<?php
declare(strict_types=1);

namespace Webmecanik\Connector\Model;

use Exception;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Newsletter\Model\SubscriptionManagerInterface;
use Psr\Log\LoggerInterface;
use Webmecanik\Connector\Api\SubscriptionUpdateInterface;

class SubscriptionWebhook implements SubscriptionUpdateInterface
{
    private const CUSTOMER_STORE_ID = 1;
    private const CUSTOMER_WEBSITE_ID = 1;

    public function __construct(
        private readonly RequestInterface $request,
        private readonly SerializerInterface $serializer,
        private readonly SubscriptionManagerInterface $subscriptionManager,
        private readonly SubscriberFactory $subscriberFactory,
        private readonly MutableScopeConfigInterface $mutableScopeConfig,
        private readonly LoggerInterface $logger,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly CustomerRepositoryInterface $customerRepository
    ) {
    }

    /**
     * @throws LocalizedException
     */
    public function execute(): bool
    {
        if (!($this->request instanceof Http)) {
            return false;
        }

        $this->disableTemporarilyModule();

        $content = $this->request->getContent();

        $this->log('Webhook received : ' . $content);
        $this->checkSignature($content);
        $this->log('Webhook signature is valid');

        $data = $this->serializer->unserialize($content);
        $subscription = $data['mautic.lead_channel_subscription_changed'][0] ?? [];
        $channel = $subscription['channel'] ?? null;
        $newStatus = $subscription['new_status'] ?? null;
        $email = $subscription['contact']['fields']['core']['email']['value'] ?? null;

        if ($channel === 'email') {
            switch ($newStatus) {
                case 'unsubscribed':
                case 'manual':
                    $this->log('Unsubscribing subscriber : ' . $email);

                    $subscriber = $this->subscriberFactory->create()->loadBySubscriberEmail($email, 1);
                    if ($subscriber->getId() === null) {
                        $this->log('Subscriber not found : ' . $email);
                        return false;
                    }

                    try {
                        $subscriber->unsubscribe();
                        $this->log('Subscriber unsubscribed successfully : ' . $email);
                        return true;
                    } catch (LocalizedException $e) {
                        $this->log('Subscriber unsubscribed ERROR : ' . $e->getMessage());
                        return false;
                    }
                case 'contactable':
                    $this->log('Subscribing subscriber : ' . $email);
                    $currentCustomerId = $this->getCustomerId($email);

                    if ($currentCustomerId) {
                        $this->subscriptionManager->subscribeCustomer($currentCustomerId, self::CUSTOMER_STORE_ID);
                    } else {
                        $this->subscriptionManager->subscribe($email, self::CUSTOMER_STORE_ID);
                    }

                    $this->log('Subscriber subscribed successfully : ' . $email);
                    return true;
                default:
                    $this->log('Unknown status : ' . $newStatus);
                    return false;
            }
        }

        $this->log('Missing channel : ' . $channel);

        return false;
    }

    private function disableTemporarilyModule(): void
    {
        $this->mutableScopeConfig->setValue('webmecanik_connector/general/enable_publisher', false);
    }

    /**
     * @throws LocalizedException
     */
    private function checkSignature(string $payload): void
    {
        if ($this->request instanceof Http) {
            $signature = $this->request->getHeader('webhook-signature');
            $secretKey = $this->mutableScopeConfig->getValue('webmecanik_connector/general/webhook_secret_key');
            $computedSignature = base64_encode(hash_hmac('sha256', $payload, $secretKey, true));

            if ($signature !== $computedSignature) {
                $this->log('Invalid signature : ' . $signature);
                $this->log('Computed signature : ' . $computedSignature);
                throw new LocalizedException(__('Invalid signature'));
            }
        }
    }

    private function getCustomerId(string $email): ?int
    {
        try {
            $customer = $this->customerRepository->get($email, self::CUSTOMER_WEBSITE_ID);
            return (int)$customer->getId();
        } catch (Exception) {
            return null;
        }
    }

    private function log(string $message): void
    {
        if ($this->scopeConfig->isSetFlag('webmecanik_connector/general/enable_debug') === true) {
            $this->logger->info($message);
        }
    }
}
