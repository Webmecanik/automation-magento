<?php
declare(strict_types=1);

namespace Webmecanik\Connector\Api;

interface SubscriptionUpdateInterface
{
    /**
     * Update subscription
     *
     * @return bool
     * @api
     */
    public function execute(): bool;
}
