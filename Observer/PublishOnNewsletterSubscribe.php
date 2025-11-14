<?php
declare(strict_types=1);

namespace Webmecanik\Connector\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Newsletter\Model\Subscriber;
use Webmecanik\Connector\Model\PublisherContact;

class PublishOnNewsletterSubscribe implements ObserverInterface
{
    public function __construct(
        private readonly PublisherContact $publisher
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var Subscriber $subscriber */
        $subscriber = $observer->getEvent()->getData('subscriber');

        $this->publisher->publish($subscriber->getEmail());
    }
}
