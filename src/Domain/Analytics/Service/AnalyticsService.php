<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Analytics\Service;

use DateInterval;
use DateTimeImmutable;
use PhpList\Core\Domain\Analytics\Repository\UserMessageViewRepository;
use PhpList\Core\Domain\Analytics\Service\Manager\LinkTrackManager;
use PhpList\Core\Domain\Analytics\Service\Manager\UserMessageViewManager;
use PhpList\Core\Domain\Messaging\Model\Filter\MessageFilter;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;
use PhpList\Core\Domain\Messaging\Repository\UserMessageBounceRepository;
use PhpList\Core\Domain\Messaging\Repository\UserMessageForwardRepository;
use PhpList\Core\Domain\Messaging\Repository\UserMessageRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;

class AnalyticsService
{
    public function __construct(
        private readonly LinkTrackManager $linkTrackManager,
        private readonly UserMessageViewManager $userMessageViewManager,
        private readonly MessageRepository $messageRepository,
        private readonly UserMessageBounceRepository $messageBounceRepository,
        private readonly UserMessageForwardRepository $messageForwardRepository,
        private readonly SubscriberRepository $subscriberRepository,
        private readonly UserMessageRepository $userMessageRepository,
        private readonly UserMessageViewRepository $userMessageViewRepository
    ) {
    }

    /**
     * Get campaign statistics
     *
     * Returns statistics overview for campaigns including:
     * - Campaign (message) ID
     * - Date sent
     * - Sent count
     * - Bounces
     * - Forwards
     * - Unique views
     * - Total clicks
     * - Unique clicks
     *
     * @param int $limit Maximum number of campaigns to return
     * @param int $lastId Last seen campaign ID for pagination
     * @return array
     */
    public function getCampaignStatistics(int $limit = 50, int $lastId = 0): array
    {
        $messages = $this->messageRepository
            ->getFilteredAfterId((new MessageFilter())->setLastId($lastId)->setLimit($limit))
            ->getItems();

        $campaignStats = [];
        foreach ($messages as $message) {
            $views = $this->userMessageViewManager->countViewsByMessageId($message->getId());
            $linkTracks = $this->linkTrackManager->getLinkTracksByMessageId($message->getId());

            $totalClicks = 0;
            $uniqueClickers = [];

            foreach ($linkTracks as $linkTrack) {
                $totalClicks += $linkTrack->getClicked();
                $uniqueClickers[$linkTrack->getUserId()] = true;
            }

            $uniqueClicks = count($uniqueClickers);
            $bounces = $this->messageBounceRepository->getCountByMessageId($message->getId());
            $forwards = $this->messageForwardRepository->getCountByMessageId($message->getId());
            $sentDate = $message->getMetadata()->getSent();
            $sentCount = $message->getMetadata()->getBounceCount() + $views;

            $campaignStats[] = [
                'campaignId' => $message->getId(),
                'subject' => $message->getContent()->getSubject(),
                'dateSent' => $sentDate?->format('Y-m-d H:i:s'),
                'sent' => $sentCount,
                'bounces' => $bounces,
                'forwards' => $forwards,
                'uniqueViews' => $views,
                'totalClicks' => $totalClicks,
                'uniqueClicks' => $uniqueClicks,
            ];
        }

        return [
            'campaigns' => $campaignStats,
            'total' => count($campaignStats),
            'hasMore' => count($messages) === $limit,
            'lastId' => count($messages) > 0 ? $messages[count($messages) - 1]->getId() : $lastId,
        ];
    }

    /**
     * Get view opens statistics
     *
     * Returns statistics for view opens including:
     * - Available campaigns
     * - Sent count
     * - Unique Views
     * - Rate (percentage of views to sent)
     *
     * @param int $limit Maximum number of campaigns to return
     * @param int $lastId Last seen campaign ID for pagination
     * @return array
     */
    public function getViewOpensStatistics(int $limit = 50, int $lastId = 0): array
    {
        $messagesResult = $this->messageRepository
            ->getFilteredAfterId((new MessageFilter())->setLastId($lastId)->setLimit($limit));

        $viewStats = [];
        foreach ($messagesResult->getItems() as $message) {
            $views = $this->userMessageViewManager->countViewsByMessageId($message->getId());
            $sentCount = $message->getMetadata()->getBounceCount() + $views;

            $viewRate = $this->formatStat($views, $sentCount);

            $viewStats[] = [
                'campaignId' => $message->getId(),
                'subject' => $message->getContent()->getSubject(),
                'sent' => $sentCount,
                'uniqueViews' => $views,
                'rate' => $viewRate,
            ];
        }

        return [
            'campaigns' => $viewStats,
            'total' => $messagesResult->getTotal(),
            'hasMore' => count($messagesResult->getItems()) === $limit,
            'lastId' => $messagesResult->getLastId(),
        ];
    }

    /**
     * Get top domains with more than 5 subscribers
     *
     * Returns statistics for the top 50 domains with more than 5 subscribers:
     * - Domain name
     * - Number of subscribers
     *
     * @param int $limit Maximum number of domains to return (default: 50)
     * @param int $minSubscribers Minimum number of subscribers per domain (default: 5)
     * @return array
     */
    public function getTopDomains(int $limit = 50, int $minSubscribers = 5): array
    {
        $subscribers = $this->subscriberRepository->findAll();

        $domains = [];
        foreach ($subscribers as $subscriber) {
            $email = $subscriber->getEmail();
            $domain = substr(strrchr($email, '@'), 1) ?: '';

            if (!empty($domain)) {
                if (!isset($domains[$domain])) {
                    $domains[$domain] = 0;
                }
                $domains[$domain]++;
            }
        }

        $filteredDomains = array_filter($domains, function ($count) use ($minSubscribers) {
            return $count >= $minSubscribers;
        });

        arsort($filteredDomains);

        $result = [];
        $count = 0;
        foreach ($filteredDomains as $domain => $subscriberCount) {
            if ($count >= $limit) {
                break;
            }

            $result[] = [
                'domain' => $domain,
                'subscribers' => $subscriberCount,
            ];

            $count++;
        }

        return [
            'domains' => $result,
            'total' => count($result),
        ];
    }

    public function getSummaryStatistics(): array
    {
        $now = new DateTimeImmutable();
        $thisMonthStart = $now->modify('first day of this month 00:00:00');
        $lastMonthStart = $now->modify('first day of last month 00:00:00');
        $lastMonthEnd = $thisMonthStart->modify('-1 second');

        $totalSubscribers = $this->subscriberRepository->count([]);
        $subscribersThisMonth = $this->subscriberRepository->countCreatedBetween($thisMonthStart, $now);
        $subscribersLastMonth = $this->subscriberRepository->countCreatedBetween($lastMonthStart, $lastMonthEnd);

        $activeCampaigns = $this->messageRepository->countActiveBetween($thisMonthStart, $now);
        $activeCampaignsLastMonth = $this->messageRepository->countActiveBetween($lastMonthStart, $lastMonthEnd);

        $sentTotal = $this->userMessageRepository->countSentBetween($thisMonthStart, $now);
        $openTotal = $this->userMessageViewRepository->countBetween($thisMonthStart, $now);
        $bounceTotal = $this->messageBounceRepository->countBetween($thisMonthStart, $now);

        $sentTotalLastMonth = $this->userMessageRepository->countSentBetween($lastMonthStart, $lastMonthEnd);
        $openTotalLastMonth = $this->userMessageViewRepository->countBetween($lastMonthStart, $lastMonthEnd);
        $bounceTotalLastMonth = $this->messageBounceRepository->countBetween($lastMonthStart, $lastMonthEnd);

        $openRate = $this->calculateRate($openTotal, $sentTotal);
        $openRateLastMonth = $this->calculateRate($openTotalLastMonth, $sentTotalLastMonth);

        $bounceRate = $this->calculateRate($bounceTotal, $sentTotal);
        $bounceRateLastMonth = $this->calculateRate($bounceTotalLastMonth, $sentTotalLastMonth);

        return [
            'total_subscribers' => [
                'value' => $totalSubscribers,
                'change_vs_last_month' => $this->calculateChange($subscribersThisMonth, $subscribersLastMonth),
            ],
            'active_campaigns' => [
                'value' => $activeCampaigns,
                'change_vs_last_month' => $this->calculateChange($activeCampaigns, $activeCampaignsLastMonth),
            ],
            'open_rate' => [
                'value' => $openRate,
                'change_vs_last_month' => $this->calculateChange($openRate, $openRateLastMonth),
            ],
            'bounce_rate' => [
                'value' => $bounceRate,
                'change_vs_last_month' => $this->calculateChange($bounceRate, $bounceRateLastMonth),
            ],
        ];
    }

    /**
     * Calculate rate as a percentage.
     *
     * @param int $numerator
     * @param int $denominator
     * @return float
     */
    private function calculateRate(int $numerator, int $denominator): float
    {
        if ($denominator === 0) {
            return 0.0;
        }

        return round(($numerator / $denominator) * 100, 2);
    }

    /**
     * Calculate percentage change between current and previous value.
     *
     * @param float|int $current
     * @param float|int $previous
     * @return float
     */
    private function calculateChange(float|int $current, float|int $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }

    /**
     * Get domains with most unconfirmed subscribers
     *
     * Returns statistics for domains showing:
     * - Domain name
     * - Confirmed subscribers count and percentage
     * - Unconfirmed subscribers count and percentage
     * - Blacklisted subscribers count and percentage
     * - Total subscribers count and percentage
     *
     * @param int $limit Maximum number of domains to return (default: 50)
     * @return array
     */
    public function getDomainConfirmationStatistics(int $limit = 50): array
    {
        $domains = [];

        $subscribers = $this->subscriberRepository->findAll();

        foreach ($subscribers as $subscriber) {
            $email = $subscriber->getEmail();
            $domain = substr(strrchr($email, '@'), 1) ?: '';

            if (!empty($domain)) {
                if (!isset($domains[$domain])) {
                    $domains[$domain] = [
                        'confirmed' => 0,
                        'unconfirmed' => 0,
                        'blacklisted' => 0,
                        'total' => 0,
                    ];
                }

                $domains[$domain]['total']++;

                if ($subscriber->isBlacklisted()) {
                    $domains[$domain]['blacklisted']++;
                } elseif ($subscriber->isConfirmed()) {
                    $domains[$domain]['confirmed']++;
                } else {
                    $domains[$domain]['unconfirmed']++;
                }
            }
        }

        uasort($domains, function ($domain1, $domain2) {
            return $domain2['unconfirmed'] <=> $domain1['unconfirmed'];
        });

        $result = [];
        $count = 0;
        foreach ($domains as $domain => $stats) {
            if ($count >= $limit) {
                break;
            }

            $domainTotal = $stats['total'];

            $result[] = [
                'domain' => $domain,
                'confirmed' => [
                    'count' => $stats['confirmed'],
                    'percentage' => $this->formatStat($stats['confirmed'], $domainTotal)
                ],
                'unconfirmed' => [
                    'count' => $stats['unconfirmed'],
                    'percentage' => $this->formatStat($stats['unconfirmed'], $domainTotal)
                ],
                'blacklisted' => [
                    'count' => $stats['blacklisted'],
                    'percentage' => $this->formatStat($stats['blacklisted'], $domainTotal)
                ],
                'total' => [
                    'count' => $stats['total'],
                    'percentage' => $this->formatStat($stats['total'], $domainTotal)
                ],
            ];

            $count++;
        }

        return [
            'domains' => $result,
            'total' => count($result),
        ];
    }

    private function formatStat(int $count, int $total): int|float
    {
        $percentage = $total > 0 ? ($count / $total) * 100 : 0;
        $percentage = round($percentage, 1);

        return ($percentage == floor($percentage)) ? (int) $percentage : $percentage;
    }

    /**
     * Get top local-parts of email addresses
     *
     * Returns statistics for the top 25 local-parts of email addresses:
     * - Local-part
     * - Count and percentage
     *
     * @param int $limit Maximum number of local-parts to return (default: 25)
     * @return array
     */
    public function getTopLocalParts(int $limit = 25): array
    {
        $localParts = [];

        $subscribers = $this->subscriberRepository->findAll();

        foreach ($subscribers as $subscriber) {
            $email = $subscriber->getEmail();
            $atPosition = strpos($email, '@');

            if ($atPosition !== false) {
                $localPart = substr($email, 0, $atPosition);

                if (!isset($localParts[$localPart])) {
                    $localParts[$localPart] = 0;
                }

                $localParts[$localPart]++;
            }
        }

        arsort($localParts);

        $result = [];
        $count = 0;
        $totalSubscribers = array_sum($localParts);
        foreach ($localParts as $localPart => $subscriberCount) {
            if ($count >= $limit) {
                break;
            }

            $result[] = [
                'localPart' => $localPart,
                'count' => $subscriberCount,
                'percentage' => $this->formatStat($subscriberCount, $totalSubscribers),
            ];

            $count++;
        }

        return [
            'localParts' => $result,
            'total' => count($result),
        ];
    }

    public function getCampaignPerformance(): array
    {
        $performance = [];
        $endDate = new DateTimeImmutable('today 23:59:59');
        $startDate = $endDate->sub(new DateInterval('P29D'))->modify('00:00:00');

        for ($index = 0; $index < 30; $index++) {
            $dayStart = $startDate->add(new DateInterval('P' . $index . 'D'));
            $dayEnd = $dayStart->modify('23:59:59');

            $performance[] = [
                'date' => $dayStart->format('Y-m-d'),
                'opens' => $this->userMessageViewManager->countViewsBetween($dayStart, $dayEnd),
                'clicks' => $this->linkTrackManager->countClicksBetween($dayStart, $dayEnd),
            ];
        }

        return $performance;
    }

    /**
     * Get recent campaigns with their performance rates
     *
     * @param int $limit
     * @return array
     */
    public function getRecentCampaigns(int $limit = 5): array
    {
        $messages = $this->messageRepository
            ->getFilteredAfterId((new MessageFilter())->setLastId(0)->setLimit($limit))
            ->getItems();
        $recentCampaigns = [];
        foreach ($messages as $message) {
            $views = $this->userMessageViewManager->countViewsByMessageId($message->getId());
            $linkTracks = $this->linkTrackManager->getLinkTracksByMessageId($message->getId());

            $uniqueClickers = [];
            foreach ($linkTracks as $linkTrack) {
                $uniqueClickers[$linkTrack->getUserId()] = true;
            }
            $uniqueClicks = count($uniqueClickers);

            $sentCount = $message->getMetadata()->getViews() + $message->getMetadata()->getBounceCount();

            $openRate = $sentCount > 0 ? ($views / $sentCount) * 100 : 0;
            $clickRate = $sentCount > 0 ? ($uniqueClicks / $sentCount) * 100 : 0;

            $recentCampaigns[] = [
                'name' => $message->getContent()->getSubject(),
                'status' => $message->getMetadata()->getStatus()?->value,
                'date' => $message->getMetadata()->getSent()?->format('Y-m-d'),
                'open_rate' => round($openRate, 2) . '%',
                'click_rate' => round($clickRate, 2) . '%',
            ];
        }

        return $recentCampaigns;
    }
}
