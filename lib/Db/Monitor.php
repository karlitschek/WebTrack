<?php

declare(strict_types=1);

namespace OCA\WebTrack\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int         getId()
 * @method string      getUserId()
 * @method void        setUserId(string $userId)
 * @method string      getName()
 * @method void        setName(string $name)
 * @method string      getUrl()
 * @method void        setUrl(string $url)
 * @method string      getKeyword()
 * @method void        setKeyword(string $keyword)
 * @method int         getCheckInterval()
 * @method void        setCheckInterval(int $interval)
 * @method bool        getIsActive()
 * @method void        setIsActive(bool $active)
 * @method bool        getIsFeed()
 * @method void        setIsFeed(bool $feed)
 * @method bool        getUseRegex()
 * @method void        setUseRegex(bool $useRegex)
 * @method string|null getLastCheckAt()
 * @method void        setLastCheckAt(?string $ts)
 * @method string|null getLastFoundAt()
 * @method void        setLastFoundAt(?string $ts)
 * @method string|null getLastErrorAt()
 * @method void        setLastErrorAt(?string $ts)
 * @method string|null getLastErrorMsg()
 * @method void        setLastErrorMsg(?string $msg)
 * @method int         getConsecutiveErrors()
 * @method void        setConsecutiveErrors(int $count)
 * @method string|null getTalkRoomToken()
 * @method void        setTalkRoomToken(?string $token)
 * @method string      getStatus()
 * @method void        setStatus(string $status)
 * @method string      getCreatedAt()
 * @method void        setCreatedAt(string $ts)
 * @method string|null getLastFoundHash()
 * @method void        setLastFoundHash(?string $hash)
 * @method string      getSourceType()
 * @method void        setSourceType(string $type)
 * @method string      getSourceLanguage()
 * @method void        setSourceLanguage(string $lang)
 * @method int         getScoreThreshold()
 * @method void        setScoreThreshold(int $threshold)
 * @method string      getBoostKeywords()
 * @method void        setBoostKeywords(string $json)
 * @method string      getExcludePatterns()
 * @method void        setExcludePatterns(string $json)
 * @method int|null    getTablesTableId()
 * @method void        setTablesTableId(?int $id)
 * @method int|null    getTablesCampaignId()
 * @method void        setTablesCampaignId(?int $id)
 */
class Monitor extends Entity {
    // Existing fields
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

    // Source configuration (added in Version1003)
    protected string $sourceType = 'custom';
    protected string $sourceLanguage = 'en-US';

    // Relevance scoring (added in Version1003)
    protected int $scoreThreshold = 2;
    protected string $boostKeywords = '[]';
    protected string $excludePatterns = '["reddit","forum","stackoverflow"]';

    // Nextcloud Tables integration (added in Version1003)
    protected ?int $tablesTableId = null;
    protected ?int $tablesCampaignId = null;

    public function __construct() {
        $this->addType('id', 'integer');
        $this->addType('checkInterval', 'integer');
        $this->addType('isActive', 'boolean');
        $this->addType('isFeed', 'boolean');
        $this->addType('useRegex', 'boolean');
        $this->addType('consecutiveErrors', 'integer');
        $this->addType('scoreThreshold', 'integer');
        $this->addType('tablesTableId', 'integer');
        $this->addType('tablesCampaignId', 'integer');
    }

    /** Convenience: decode boostKeywords JSON into a plain array. */
    public function getBoostKeywordsArray(): array {
        return json_decode($this->boostKeywords, true) ?? [];
    }

    /** Convenience: decode excludePatterns JSON into a plain array. */
    public function getExcludePatternsArray(): array {
        return json_decode($this->excludePatterns, true) ?? [];
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
            // Source configuration
            'sourceType'       => $this->getSourceType(),
            'sourceLanguage'   => $this->getSourceLanguage(),
            // Relevance scoring
            'scoreThreshold'   => $this->getScoreThreshold(),
            'boostKeywords'    => $this->getBoostKeywordsArray(),
            'excludePatterns'  => $this->getExcludePatternsArray(),
            // Tables integration
            'tablesTableId'    => $this->getTablesTableId(),
            'tablesCampaignId' => $this->getTablesCampaignId(),
        ];
    }
}
