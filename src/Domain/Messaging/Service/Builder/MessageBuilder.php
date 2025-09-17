<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Builder;

use InvalidArgumentException;
use PhpList\Core\Domain\Messaging\Model\Dto\MessageContext;
use PhpList\Core\Domain\Messaging\Model\Dto\MessageDtoInterface;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Repository\TemplateRepository;

class MessageBuilder
{
    public function __construct(
        private readonly TemplateRepository $templateRepository,
        private readonly MessageFormatBuilder $messageFormatBuilder,
        private readonly MessageScheduleBuilder $messageScheduleBuilder,
        private readonly MessageContentBuilder $messageContentBuilder,
        private readonly MessageOptionsBuilder $messageOptionsBuilder,
    ) {
    }

    public function build(MessageDtoInterface $createMessageDto, object $context = null): Message
    {
        if (!$context instanceof MessageContext) {
            throw new InvalidArgumentException('Invalid context type');
        }

        $format = $this->messageFormatBuilder->build($createMessageDto->getFormat());
        $schedule = $this->messageScheduleBuilder->build($createMessageDto->getSchedule());
        $content = $this->messageContentBuilder->build($createMessageDto->getContent());
        $options = $this->messageOptionsBuilder->build($createMessageDto->getOptions());
        $template = null;
        if (isset($createMessageDto->templateId)) {
            $template = $this->templateRepository->find($createMessageDto->templateId);
        }

        if ($context->getExisting()) {
            $context->getExisting()->setFormat($format);
            $context->getExisting()->setSchedule($schedule);
            $context->getExisting()->setContent($content);
            $context->getExisting()->setOptions($options);
            $context->getExisting()->setTemplate($template);
            return $context->getExisting();
        }

        $metadata = new Message\MessageMetadata(Message\MessageStatus::Draft);

        return new Message($format, $schedule, $metadata, $content, $options, $context->getOwner(), $template);
    }
}
