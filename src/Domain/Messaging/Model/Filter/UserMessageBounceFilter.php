<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Model\Filter;

use DateTimeImmutable;
use PhpList\Core\Domain\Common\Model\Filter\FilterRequestInterface;
use PhpList\Core\Domain\Common\Model\Filter\PaginatedFilter;

class UserMessageBounceFilter extends PaginatedFilter implements FilterRequestInterface
{
    private ?int $userId;
    private ?int $messageId;
    private ?int $bounceId;
    private ?DateTimeImmutable $dateFrom;

    public function __construct(
        ?int $userId = null,
        ?int $messageId = null,
        ?int $bounceId = null,
        ?DateTimeImmutable $dateFrom = null,
        int $lastId = 0,
        int $limit = 50,
    ) {
        $this->userId = $userId;
        $this->messageId = $messageId;
        $this->bounceId = $bounceId;
        $this->dateFrom = $dateFrom;
        $this->setLastId($lastId);
        $this->setLimit($limit);
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function getMessageId(): ?int
    {
        return $this->messageId;
    }

    public function getBounceId(): ?int
    {
        return $this->bounceId;
    }

    public function getDateFrom(): ?DateTimeImmutable
    {
        return $this->dateFrom;
    }
}
