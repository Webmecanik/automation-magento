<?php
declare(strict_types=1);

namespace Webmecanik\Connector\Plugin;

use Magento\Framework\MessageQueue\PoisonPill\PoisonPillCompareInterface;
use Magento\Framework\MessageQueue\PoisonPill\PoisonPillReadInterface;
use Magento\MysqlMq\Model\QueueManagement;

class KillConsumerOnPoisonPill
{
    private string $poisonPillVersion;

    public function __construct(
        private readonly PoisonPillReadInterface $poisonPillRead,
        private readonly PoisonPillCompareInterface $poisonPillCompare
    ) {
        $this->poisonPillVersion = $this->poisonPillRead->getLatestVersion();
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function aroundReadMessages(
        QueueManagement $subject,
        callable $proceed,
        string $queue,
        int $maxMessagesNumber = null
    ): array {

        if ($queue === 'webmecanik_contact_updated'
            && !$this->poisonPillCompare->isLatestVersion($this->poisonPillVersion)
        ) {
            // phpcs:ignore Magento2.Security.LanguageConstruct.ExitUsage
            exit;
        }

        return $proceed($queue, $maxMessagesNumber);
    }
}
