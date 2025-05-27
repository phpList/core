<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Analytics\Service;

use PhpList\Core\Domain\Analytics\Service\Manager\LinkTrackManager;
use PhpList\Core\Domain\Analytics\Service\Manager\UserMessageViewManager;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;
use PhpList\Core\Domain\Messaging\Repository\UserMessageBounceRepository;
use PhpList\Core\Domain\Messaging\Repository\UserMessageForwardRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;

class AnalyticsService
{
    private LinkTrackManager $linkTrackManager;
    private UserMessageViewManager $userMessageViewManager;
    private MessageRepository $messageRepository;
    private UserMessageBounceRepository $messageBounceRepository;
    private UserMessageForwardRepository $messageForwardRepository;
    private SubscriberRepository $subscriberRepository;

    public function __construct(
        LinkTrackManager $linkTrackManager,
        UserMessageViewManager $userMessageViewManager,
        MessageRepository $messageRepository,
        UserMessageBounceRepository $messageBounceRepository,
        UserMessageForwardRepository $messageForwardRepository,
        SubscriberRepository $subscriberRepository
    ) {
        $this->linkTrackManager = $linkTrackManager;
        $this->userMessageViewManager = $userMessageViewManager;
        $this->messageRepository = $messageRepository;
        $this->messageBounceRepository = $messageBounceRepository;
        $this->messageForwardRepository = $messageForwardRepository;
        $this->subscriberRepository = $subscriberRepository;
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
        $messages = $this->messageRepository->getFilteredAfterId($lastId, $limit);

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
        $messages = $this->messageRepository->getFilteredAfterId($lastId, $limit);

        $viewStats = [];

        foreach ($messages as $message) {
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
            'total' => count($viewStats),
            'hasMore' => count($messages) === $limit,
            'lastId' => count($messages) > 0 ? $messages[count($messages) - 1]->getId() : $lastId,
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
        $domains = [];

        $subscribers = $this->subscriberRepository->findAll();

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
}
