<?php
declare(strict_types=1);

namespace Webmecanik\Connector\Api\Data;

use Magento\Customer\Api\Data\CustomerInterface as BaseCustomerInterface;

interface CustomerInterface extends BaseCustomerInterface
{
    public const CUSTOMER_ID = 'customer_id';
    public const LAST_ACTIVE_DATE_CODE = 'last_active';
}
