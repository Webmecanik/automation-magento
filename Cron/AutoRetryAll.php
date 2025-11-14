<?php

namespace Webmecanik\Connector\Cron;

use Exception;
use Webmecanik\Connector\Controller\Adminhtml\Queue\RetryAll as RetryAllController;
use Magento\Framework\App\Config\ScopeConfigInterface;

class AutoRetryAll
{
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly RetryAllController $retryAllController
    ) {
    }

    /**
     * @throws Exception
     */
    public function execute(): void
    {
        $enableExport = $this->scopeConfig->isSetFlag('webmecanik_connector/general/enable_export');
        $enableRetryAll = $this->scopeConfig->isSetFlag('webmecanik_connector/general/enable_retry_all');

        if ($enableRetryAll === true && $enableExport === false) {
            $this->retryAllController->retryAll();
        }
    }
}
