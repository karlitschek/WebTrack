<?php

declare(strict_types=1);

namespace OCA\WebTrack\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int    getId()
 * @method int    getMonitorId()
 * @method void   setMonitorId(int $id)
 * @method string getUserId()
 * @method void   setUserId(string $uid)
 * @method string getEvent()
 * @method void   setEvent(string $event)
 * @method string|null getSnippet()
 * @method void   setSnippet(?string $snippet)
 * @method string|null getErrorMsg()
 * @method void   setErrorMsg(?string $msg)
 * @method string getCreatedAt()
 * @method void   setCreatedAt(string $ts)
 */
class HistoryLog extends Entity {
    protected int $monitorId = 0;
    protected string $userId = '';
    protected string $event = '';
    protected ?string $snippet = null;
    protected ?string $errorMsg = null;
    protected string $createdAt = '';

    public function __construct() {
        $this->addType('id', 'integer');
        $this->addType('monitorId', 'integer');
    }

    public function jsonSerialize(): array {
        return [
            'id'        => $this->getId(),
            'monitorId' => $this->getMonitorId(),
            'userId'    => $this->getUserId(),
            'event'     => $this->getEvent(),
            'snippet'   => $this->getSnippet(),
            'errorMsg'  => $this->getErrorMsg(),
            'createdAt' => $this->getCreatedAt(),
        ];
    }
}
