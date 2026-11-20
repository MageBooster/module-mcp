<?php
declare(strict_types=1);

namespace MageBooster\Mcp\Model\Tool;

use Magento\Cron\Model\ResourceModel\Schedule\CollectionFactory as ScheduleCollectionFactory;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\MaintenanceMode;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\State;
use Magento\Framework\Indexer\ConfigInterface as IndexerConfig;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Indexer\StateInterface;
use MageBooster\Mcp\Api\ToolInterface;
use MageBooster\Mcp\Model\Role;

class MaintenanceStatus implements ToolInterface
{
    public function __construct(
        private TypeListInterface $cacheTypeList,
        private IndexerConfig $indexerConfig,
        private IndexerRegistry $indexerRegistry,
        private ProductMetadataInterface $productMetadata,
        private State $appState,
        private MaintenanceMode $maintenanceMode,
        private ScheduleCollectionFactory $scheduleCollectionFactory,
        private ScopeConfigInterface $scopeConfig,
        private Period $period
    ) {
    }

    public function getName(): string
    {
        return 'maintenance_status';
    }

    public function getDescription(): string
    {
        return 'Health check: cache types (on/off, needs refresh), indexers (status, mode, backlog), '
            . 'Magento/Mage-OS and PHP version, deploy mode, maintenance mode, search engine and cron (last run, errors, missed jobs in 24 h).';
    }

    public function getInputSchema(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass(), 'additionalProperties' => false];
    }

    public function getMinRole(): string
    {
        return Role::READ;
    }

    public function execute(array $args): array
    {
        $invalidated = array_keys($this->cacheTypeList->getInvalidated());
        $caches = [];
        foreach ($this->cacheTypeList->getTypes() as $id => $type) {
            $caches[] = [
                'id' => $id,
                'label' => (string)$type->getCacheType(),
                'enabled' => (bool)$type->getStatus(),
                'needs_refresh' => in_array($id, $invalidated, true),
            ];
        }

        $indexers = [];
        foreach (array_keys($this->indexerConfig->getIndexers()) as $indexerId) {
            try {
                $indexer = $this->indexerRegistry->get($indexerId);
                $backlog = null;
                if ($indexer->isScheduled()) {
                    try {
                        $view = $indexer->getView();
                        $backlog = max(0, (int)$view->getChangelog()->getVersion() - (int)$view->getState()->getVersionId());
                    } catch (\Throwable) {
                        $backlog = null;
                    }
                }
                $indexers[] = [
                    'id' => $indexerId,
                    'title' => (string)$indexer->getTitle(),
                    'status' => $indexer->getStatus(),
                    'mode' => $indexer->isScheduled() ? 'schedule' : 'realtime',
                    'backlog' => $backlog,
                    'updated' => $this->period->local($indexer->getLatestUpdated() ?: null),
                ];
            } catch (\Throwable $e) {
                $indexers[] = ['id' => $indexerId, 'status' => 'unknown'];
            }
        }
        $invalidIndexers = count(array_filter($indexers, static fn($i) => ($i['status'] ?? '') === StateInterface::STATUS_INVALID));

        $metadata = $this->productMetadata;
        return [
            'platform' => [
                'name' => method_exists($metadata, 'getDistributionName') ? $metadata->getDistributionName() : $metadata->getName(),
                'version' => method_exists($metadata, 'getDistributionVersion') ? $metadata->getDistributionVersion() : $metadata->getVersion(),
                'magento_version' => $metadata->getVersion(),
                'edition' => $metadata->getEdition(),
            ],
            'php' => PHP_VERSION,
            'deploy_mode' => $this->appState->getMode(),
            'maintenance_mode' => $this->maintenanceMode->isOn(),
            'search_engine' => $this->scopeConfig->getValue('catalog/search/engine'),
            'cron' => $this->cron(),
            'caches' => $caches,
            'caches_need_refresh' => count($invalidated),
            'caches_disabled' => count(array_filter($caches, static fn($c) => !$c['enabled'])),
            'indexers' => $indexers,
            'indexers_invalid' => $invalidIndexers,
        ];
    }

    private function cron(): array
    {
        $last = $this->scheduleCollectionFactory->create()
            ->addFieldToFilter('executed_at', ['notnull' => true])
            ->setOrder('executed_at', 'DESC')->setPageSize(1)->getFirstItem();
        $since = (new \DateTimeImmutable('-24 hours', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $count = function (string $status) use ($since): int {
            return $this->scheduleCollectionFactory->create()
                ->addFieldToFilter('status', $status)
                ->addFieldToFilter('scheduled_at', ['gteq' => $since])
                ->getSize();
        };
        $lastRun = $last->getId() ? (string)$last->getData('executed_at') : null;
        $minutesAgo = $lastRun
            ? (int)floor((time() - (new \DateTimeImmutable($lastRun, new \DateTimeZone('UTC')))->getTimestamp()) / 60)
            : null;
        return [
            'last_run' => $this->period->local($lastRun),
            'last_job' => $last->getId() ? $last->getData('job_code') : null,
            'minutes_ago' => $minutesAgo,
            'running' => $minutesAgo !== null && $minutesAgo <= 15,
            'errors_24h' => $count('error'),
            'missed_24h' => $count('missed'),
            'pending' => $count('pending'),
        ];
    }
}
