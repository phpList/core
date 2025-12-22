<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use PhpList\Core\Domain\Common\Html2Text;
use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Messaging\Repository\TemplateRepository;
use PhpList\Core\Domain\Messaging\Service\Manager\TemplateImageManager;

class SystemMailConstructor
{
    public function __construct(
        private readonly Html2Text $html2Text,
        private readonly ConfigProvider $configProvider,
        private readonly TemplateRepository $templateRepository,
        private readonly TemplateImageManager $templateImageManager,
        private readonly bool $poweredByPhplist = false,
    ) {
    }

    public function __invoke($message, $subject = ''): array
    {
        $hasHTML = strip_tags($message) !== $message;

        if ($hasHTML) {
            $message = stripslashes($message);
            $textMessage = ($this->html2Text)($message);
            $htmlMessage = $message;
        } else {
            $textMessage = $message;
            $htmlMessage = $message;
            //  $htmlMessage = str_replace("\n\n","\n",$htmlMessage);
            $htmlMessage = nl2br($htmlMessage);
            //# make links clickable:
            $htmlMessage = preg_replace('~https?://[^\s<]+~i', '<a href="$0">$0</a>', $htmlMessage);
        }
        //# add li-s around the lists
        if (preg_match('/<ul>\s+(\*.*)<\/ul>/imsxU', $htmlMessage, $listsmatch)) {
            $lists = $listsmatch[1];
            $listsHTML = '';
            preg_match_all('/\*([^\*]+)/', $lists, $matches);
            for ($i = 0; $i < count($matches[0]); ++$i) {
                $listsHTML .= '<li>'.$matches[1][$i].'</li>';
            }
            $htmlMessage = str_replace($listsmatch[0], '<ul>'.$listsHTML.'</ul>', $htmlMessage);
        }

        $htmlContent = $htmlMessage;
        $textContent = $textMessage;
        $templateId = $this->configProvider->getValue(ConfigOption::SystemMessageTemplate);
        if ($templateId) {
            $template = $this->templateRepository->findOneById((int)$templateId);
            if ($template) {
                $htmlTemplate = stripslashes($template->getContent());
                $textTemplate = stripslashes($template->getText());
                $htmlContent = str_replace('[CONTENT]', $htmlMessage, $htmlTemplate);
                $htmlContent = str_replace('[SUBJECT]', $subject, $htmlContent);
                $htmlContent = str_replace('[FOOTER]', '', $htmlContent);
                if (!$this->poweredByPhplist) {
                    $phpListPowered = preg_replace(
                        '/src=".*power-phplist.png"/',
                        'src="powerphplist.png"',
                        $this->configProvider->getValue(ConfigOption::PoweredByImage),
                    );
                } else {
                    $phpListPowered = $this->configProvider->getValue(ConfigOption::PoweredByText);
                }
                if (str_contains($htmlContent, '[SIGNATURE]')) {
                    $htmlContent = str_replace('[SIGNATURE]', $phpListPowered, $htmlContent);
                } elseif (strpos($htmlContent, '</body>')) {
                    $htmlContent = str_replace('</body>', $phpListPowered.'</body>', $htmlContent);
                } else {
                    $htmlContent .= $phpListPowered;
                }
                $htmlContent = $this->templateImageManager->parseLogoPlaceholders($htmlContent);
                $textContent = str_replace('[CONTENT]', $textMessage, $textTemplate);
                $textContent = str_replace('[SUBJECT]', $subject, $textContent);
                $textContent = str_replace('[FOOTER]', '', $textContent);
                $phpListPowered = trim(($this->html2Text)($this->configProvider->getValue(ConfigOption::PoweredByText)));
                if (str_contains($textContent, '[SIGNATURE]')) {
                    $textContent = str_replace('[SIGNATURE]', $phpListPowered, $textContent);
                } else {
                    $textContent .= "\n\n" . $phpListPowered;
                }
            }
        }

        return [$htmlContent, $textContent];
    }
}
