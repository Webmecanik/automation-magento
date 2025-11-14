<?php
declare(strict_types=1);

namespace Webmecanik\Connector\Ui\Component\Listing\Column;

use Magento\MysqlMq\Model\QueueManagement;
use Magento\Ui\Component\Listing\Columns\Column;

class QueueActions extends Column
{
    public const ELIGIBLE_STATUSES_FOR_RETRY = [
        QueueManagement::MESSAGE_STATUS_IN_PROGRESS,
        QueueManagement::MESSAGE_STATUS_ERROR
    ];

    public function prepareDataSource(array $dataSource): array
    {
        $dataSource = parent::prepareDataSource($dataSource);

        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                if (in_array($item[QueueManagement::MESSAGE_STATUS], self::ELIGIBLE_STATUSES_FOR_RETRY)) {
                    $item[$this->getData('name')]['retry'] = [
                        'href' => $this->getContext()->getUrl('webmecanik/queue/retry', ['id' => $item['id']]),
                        'label' => __('Retry')
                    ];
                }
            }
        }

        return $dataSource;
    }
}
