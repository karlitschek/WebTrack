<?php

declare(strict_types=1);

namespace OCA\WebTrack\Dashboard;

use OCA\WebTrack\Db\HistoryLogMapper;
use OCP\Dashboard\IAPIWidgetV2;
use OCP\Dashboard\Model\WidgetItem;
use OCP\Dashboard\Model\WidgetItems;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Util;

class RecentFindsWidget implements IAPIWidgetV2 {

    public function __construct(
        private IL10N $l,
        private IURLGenerator $urlGenerator,
        private HistoryLogMapper $historyMapper,
    ) {}

    public function getId(): string {
        return 'webtrack-recent-finds';
    }

    public function getTitle(): string {
        return $this->l->t('WebTrack – Recent Keyword Finds');
    }

    public function getOrder(): int {
        return 50;
    }

    public function getIconClass(): string {
        return 'icon-webtrack';
    }

    public function getUrl(): ?string {
        return $this->urlGenerator->linkToRouteAbsolute('webtrack.page.index');
    }

    public function load(): void {
        Util::addScript('webtrack', 'webtrack-dashboard');
    }

    public function getItemsV2(string $userId, ?string $since = null, int $limit = 7): WidgetItems {
        $rows = $this->historyMapper->findLatestFoundByUser($userId, $limit);
        $appUrl = $this->urlGenerator->linkToRouteAbsolute('webtrack.page.index');
        $iconUrl = $this->urlGenerator->getAbsoluteURL(
            $this->urlGenerator->imagePath('webtrack', 'app.svg')
        );

        $items = array_map(static function (array $row) use ($appUrl, $iconUrl): WidgetItem {
            $parts = [];
            if (!empty($row['snippet'])) {
                $parts[] = mb_substr($row['snippet'], 0, 80);
            }
            $parts[] = (new \DateTimeImmutable($row['created_at']))->format('M j, H:i');

            return new WidgetItem(
                title: $row['monitor_name'] . ' — ' . $row['keyword'],
                subtitle: implode(' · ', $parts),
                link: $appUrl,
                iconUrl: $iconUrl,
                sinceId: $row['created_at'],
            );
        }, $rows);

        return new WidgetItems(
            $items,
            $this->l->t('No keyword matches found yet'),
        );
    }
}
