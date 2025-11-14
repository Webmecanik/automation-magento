<?php
declare(strict_types=1);

namespace Webmecanik\Connector\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;

class RejectionMessage extends Column
{
    public function prepareDataSource(array $dataSource): array
    {
        $dataSource = parent::prepareDataSource($dataSource);

        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                if ($rejectionMessage = $item[$this->getData('name')]) {
                    $item[$this->getData('name')] = <<<HTML
<button type="button"
        class="action secondary"
        onclick="this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'none'
        ? 'block' : 'none'">Show/hide request</button><pre style="display:none">$rejectionMessage</pre>
HTML;
                }
            }
        }

        return $dataSource;
    }
}
