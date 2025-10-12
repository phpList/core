<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service\Manager;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Common\ClientIpResolver;
use PhpList\Core\Domain\Common\SystemInfoCollector;
use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Subscription\Model\Dto\ChangeSetDto;
use PhpList\Core\Domain\Subscription\Model\Filter\SubscriberHistoryFilter;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Model\SubscriberHistory;
use PhpList\Core\Domain\Subscription\Repository\SubscriberHistoryRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

class SubscriberHistoryManager
{
    private SubscriberHistoryRepository $repository;
    private ClientIpResolver $clientIpResolver;
    private SystemInfoCollector $systemInfoCollector;
    private TranslatorInterface $translator;
    private EntityManagerInterface $entityManager;

    public function __construct(
        SubscriberHistoryRepository $repository,
        ClientIpResolver $clientIpResolver,
        SystemInfoCollector $systemInfoCollector,
        TranslatorInterface $translator,
        EntityManagerInterface $entityManager,
    ) {
        $this->repository = $repository;
        $this->clientIpResolver = $clientIpResolver;
        $this->systemInfoCollector = $systemInfoCollector;
        $this->translator = $translator;
        $this->entityManager = $entityManager;
    }

    public function getHistory(int $lastId, int $limit, SubscriberHistoryFilter $filter): array
    {
        return $this->repository->getFilteredAfterId($lastId, $limit, $filter);
    }

    public function addHistory(Subscriber $subscriber, string $message, ?string $details = null): SubscriberHistory
    {
        $subscriberHistory = new SubscriberHistory($subscriber);
        $subscriberHistory->setSummary($message);
        $subscriberHistory->setDetail($details ?? $message);
        $subscriberHistory->setSystemInfo($this->systemInfoCollector->collectAsString());
        $subscriberHistory->setIp($this->clientIpResolver->resolve());

        $this->entityManager->persist($subscriberHistory);

        return $subscriberHistory;
    }

    public function addHistoryFromChangeSet(
        Subscriber $subscriber,
        string $message,
        ChangeSetDto $changeSet,
    ): SubscriberHistory {
        $details = '';
        foreach ($changeSet->getChanges() as $attribute => [$old, $new]) {
            if (in_array($attribute, ChangeSetDto::IGNORED_ATTRIBUTES, true) || $new === null) {
                continue;
            }
            $details .= $this->translator->trans(
                "%attribute% = %new_value% \n changed from %old_value%",
                [
                    '%attribute%' => $attribute,
                    '%new_value%' => $new,
                    '%old_value%' => $old ?? $this->translator->trans('(no data)'),
                ]
            ) . PHP_EOL;
        }

        if ($details === '') {
            $details .= $this->translator->trans('No data changed') . PHP_EOL;
        }

        return $this->addHistory($subscriber, $message, $details);
    }

    public function addHistoryFromImport(
        Subscriber $subscriber,
        array $listLines,
        ChangeSetDto $changeSetDto,
        ?Administrator $admin = null,
    ): void {
        $headerLine = sprintf('API-v2-import - %s: %s%s', $admin ? 'Admin' : 'CLI', $admin?->getId(), "\n\n");

        $lines = $this->getHistoryLines($changeSetDto, $listLines);

        $this->addHistory(
            subscriber: $subscriber,
            message: 'Import by ' . $admin?->getLoginName(),
            details: $headerLine . implode(PHP_EOL, $lines) . PHP_EOL
        );
    }

    public function addHistoryFromApi(
        Subscriber $subscriber,
        array $listLines,
        ChangeSetDto $updatedData,
        ?Administrator $admin = null,
    ): void {
        $lines = $this->getHistoryLines($updatedData, $listLines);

        $this->addHistory(
            subscriber: $subscriber,
            message: $this->translator->trans('Update by %admin%', ['%admin%' => $admin->getLoginName()]),
            details: implode(PHP_EOL, $lines) . PHP_EOL
        );
    }

    private function getHistoryLines(ChangeSetDto $updatedData, array $listLines): array
    {
        $lines = [];
        if (!$updatedData->hasChanges() && empty($listLines)) {
            $lines[] = $this->translator->trans('No user details changed');
        } else {
            foreach ($updatedData->getChanges() as $field => [$old, $new]) {
                if (in_array($field, ChangeSetDto::IGNORED_ATTRIBUTES, true)) {
                    continue;
                }
                $lines[] = $this->translator->trans(
                    '%field% = %new% *changed* from %old%',
                    [
                        '%field' => $field,
                        '%new%' => json_encode($new),
                        '%old%' => json_encode($old)
                    ],
                );
            }
            foreach ($listLines as $line) {
                $lines[] = $line;
            }
        }

        return $lines;
    }
}
