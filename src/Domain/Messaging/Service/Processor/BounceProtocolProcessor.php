<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Processor;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

interface BounceProtocolProcessor
{
    /**
     * Processes bounces for a specific protocol based on console input options.
     * Should throw an exception on configuration or connection errors.
     *
     * @return string A textual report (reserved for future use)
     */
    public function process(InputInterface $input, SymfonyStyle $inputOutput): string;

    /**
     * Returns a protocol name handled by this processor (e.g. "pop", "mbox").
     */
    public function getProtocol(): string;
}
