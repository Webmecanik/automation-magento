<?php
declare(strict_types=1);

namespace Webmecanik\Connector\Plugin;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\MysqlMq\Model\QueueManagement;

class PreventMessageReading
{
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
    ) {
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function aroundReadMessages(
        QueueManagement $subject,
        callable $proceed,
        string $queue,
        int $maxMessagesNumber = null
    ): array {

        if ($queue === 'webmecanik_contact_updated'
            && $this->scopeConfig->isSetFlag('webmecanik_connector/general/enable_export') === false
        ) {
            return [];
        }

        return $proceed($queue, $maxMessagesNumber);
    }
}
