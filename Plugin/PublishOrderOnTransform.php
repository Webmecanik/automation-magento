<?php
declare(strict_types=1);

namespace Webmecanik\Connector\Plugin;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Webmecanik\Connector\Model\PublisherContact;

class PublishOrderOnTransform
{
    public function __construct(
        private readonly PublisherContact $publisher
    ) {
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function aroundSave(
        OrderResourceModel $orderResourceModel,
        callable $proceed,
        Order $order
    ): OrderResourceModel {
        $origOrderStatus = $order->getOrigData(OrderInterface::STATUS);
        $result = $proceed($order);
        $newOrderStatus = $order->getData(OrderInterface::STATUS);

        if ($origOrderStatus != Order::STATE_PROCESSING && $newOrderStatus == Order::STATE_PROCESSING) {
            $this->publisher->publish((string)$order->getCustomerEmail());
        }

        return $result;
    }
}
