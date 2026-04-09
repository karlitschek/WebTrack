<?php

declare(strict_types=1);

namespace OCA\WebTrack\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int    getId()
 * @method string getUserId()
 * @method void   setUserId(string $userId)
 * @method string getName()
 * @method void   setName(string $name)
 * @method string getUrl()
 * @method void   setUrl(string $url)
 * @method string getKeyword()
 * @method void   setKeyword(string $keyword)
 * @method int    getCheckInterval()
 * @method void   setCheckInterval(int $interval)
 * @method bool   getIsActive()
 * @method void   setIsActive(bool $active)
 * @method bool   getIsFeed()
 * @method void   setIsFeed(bool $feed)
 * @method bool   getUseRegex()
 * @method void   setUseRegex(bool $useRegex)
 * @method string|null getLastCheckAt()
 * @method void   setLastCheckAt(?string $ts)
 * @method string|null getLastFoundAt()
 * @method void   setLastFoundAt(?string $ts)
 * @method string|null getLastErrorAt()
 * @method void   setLastErrorAt(?string $ts)
 * @method string|null getLastErrorMsg()
 * @method void   setLastErrorMsg(?string $msg)
 * @method int    getConsecutiveErrors()
 * @method void   setConsecutiveErrors(int $count)
 * @method string|null getTalkRoomToken()
 * @method void   setTalkRoomToken(?string $token)
 * @method string getStatus()
 * @method void   setStatus(string $status)
 * @method string getCreatedAt()
 * @method void   setCreatedAt(string $ts)
 * @method string|null getLastFoundHash()
 * @method void   setLastFoundHash(?string $hash)
 */
class Monitor extends Entity {
    protected string $userId = '';
    protected string $name = '';
    protected string $url = '';
    protected string $keyword = '';
    protected int $checkInterval = 60;
    protected bool $isActive = true;
    protected bool $isFeed = false;
    protected bool $useRegex = false;
    protected ?string $lastCheckAt = null;
    protected ?string $lastFoundAt = null;
    protected ?string $lastErrorAt = null;
    protected ?string $lastErrorMsg = null;
    protected int $consecutiveErrors = 0;
    protected ?string $talkRoomToken = null;
    protected string $status = 'ok';
    protected string $createdAt = '';
    protected ?string $lastFoundHash = null;

    public function __construct() {
        $this->addType('id', 'integer');
        $this->addType('checkInterval', 'integer');
        $this->addType('isActive', 'boolean');
        $this->addType('isFeed', 'boolean');
        $this->addType('useRegex', 'boolean');
        $this->addType('consecutiveErrors', 'integer');
    }

    public function jsonSerialize(): array {
        return [
            'id'               => $this->getId(),
            'userId'           => $this->getUserId(),
            'name'             => $this->getName(),
            'url'              => $this->getUrl(),
            'keyword'          => $this->getKeyword(),
            'checkInterval'    => $this->getCheckInterval(),
            'isActive'         => $this->getIsActive(),
            'isFeed'           => $this->getIsFeed(),
            'useRegex'         => $this->getUseRegex(),
            'lastCheckAt'      => $this->getLastCheckAt(),
            'lastFoundAt'      => $this->getLastFoundAt(),
            'lastErrorAt'      => $this->getLastErrorAt(),
            'lastErrorMsg'     => $this->getLastErrorMsg(),
            'consecutiveErrors'=> $this->getConsecutiveErrors(),
            'talkRoomToken'    => $this->getTalkRoomToken(),
            'status'           => $this->getStatus(),
            'createdAt'        => $this->getCreatedAt(),
            'lastFoundHash'    => $this->getLastFoundHash(),
        ];
    }
}
