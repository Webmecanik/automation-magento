<?php

namespace Webmecanik\Connector\Api\Data;

use Magento\Sales\Api\Data\OrderInterface as BaseOrderInterface;

interface OrderInterface extends BaseOrderInterface
{
    public const ID = 'id';
}
