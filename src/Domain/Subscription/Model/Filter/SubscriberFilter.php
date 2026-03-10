<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Model\Filter;

use DateTimeImmutable;
use PhpList\Core\Domain\Common\Model\Filter\FilterRequestInterface;

/** @SuppressWarnings("ExcessiveParameterList") */
class SubscriberFilter implements FilterRequestInterface
{
    private ?int $listId;
    private ?DateTimeImmutable $subscribedDateFrom;
    private ?DateTimeImmutable $subscribedDateTo;
    private ?DateTimeImmutable $createdDateFrom;
    private ?DateTimeImmutable $createdDateTo;
    private ?DateTimeImmutable $updatedDateFrom;
    private ?DateTimeImmutable $updatedDateTo;
    private ?bool $isConfirmed;
    private ?bool $isBlacklisted;
    private array $columns;
    private ?string $findColumn;
    private ?string $findValue;

    public function __construct(
        ?int $listId = null,
        ?DateTimeImmutable $subscribedDateFrom = null,
        ?DateTimeImmutable $subscribedDateTo = null,
        ?DateTimeImmutable $createdDateFrom = null,
        ?DateTimeImmutable $createdDateTo = null,
        ?DateTimeImmutable $updatedDateFrom = null,
        ?DateTimeImmutable $updatedDateTo = null,
        ?bool $isConfirmed = null,
        ?bool $isBlacklisted = null,
        array $columns = [],
        ?string $findColumn = null,
        ?string $findValue = null,
    ) {
        $this->listId = $listId;
        $this->subscribedDateFrom = $subscribedDateFrom;
        $this->subscribedDateTo = $subscribedDateTo;
        $this->createdDateFrom = $createdDateFrom;
        $this->createdDateTo = $createdDateTo;
        $this->updatedDateFrom = $updatedDateFrom;
        $this->updatedDateTo = $updatedDateTo;
        $this->isConfirmed = $isConfirmed;
        $this->isBlacklisted = $isBlacklisted;
        $this->columns = $columns;
        $this->findColumn = $findColumn;
        $this->findValue = $findValue;
    }

    public function getListId(): ?int
    {
        return $this->listId;
    }

    public function getSubscribedDateFrom(): ?DateTimeImmutable
    {
        return $this->subscribedDateFrom;
    }

    public function getSubscribedDateTo(): ?DateTimeImmutable
    {
        return $this->subscribedDateTo;
    }

    public function getCreatedDateFrom(): ?DateTimeImmutable
    {
        return $this->createdDateFrom;
    }

    public function getCreatedDateTo(): ?DateTimeImmutable
    {
        return $this->createdDateTo;
    }

    public function getUpdatedDateFrom(): ?DateTimeImmutable
    {
        return $this->updatedDateFrom;
    }

    public function getUpdatedDateTo(): ?DateTimeImmutable
    {
        return $this->updatedDateTo;
    }

    public function getIsConfirmed(): ?bool
    {
        return $this->isConfirmed;
    }

    public function getIsBlacklisted(): ?bool
    {
        return $this->isBlacklisted;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getFindColumn(): ?string
    {
        return $this->findColumn;
    }

    public function getFindValue(): ?string
    {
        return $this->findValue;
    }
}
