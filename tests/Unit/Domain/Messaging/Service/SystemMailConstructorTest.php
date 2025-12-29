<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service;

use PhpList\Core\Domain\Common\Html2Text;
use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Messaging\Model\Template;
use PhpList\Core\Domain\Messaging\Repository\TemplateRepository;
use PhpList\Core\Domain\Messaging\Service\Manager\TemplateImageManager;
use PhpList\Core\Domain\Messaging\Service\SystemMailConstructor;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SystemMailConstructorTest extends TestCase
{
    private Html2Text&MockObject $html2Text;
    private ConfigProvider&MockObject $configProvider;
    private TemplateRepository&MockObject $templateRepository;
    private TemplateImageManager&MockObject $templateImageManager;

    protected function setUp(): void
    {
        $this->html2Text = $this->getMockBuilder(Html2Text::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__invoke'])
            ->getMock();
        $this->configProvider = $this->createMock(ConfigProvider::class);
        $this->templateRepository = $this->createMock(TemplateRepository::class);
        $this->templateImageManager = $this->getMockBuilder(TemplateImageManager::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['parseLogoPlaceholders'])
            ->getMock();
    }

    private function createConstructor(bool $poweredByPhplist = false): SystemMailConstructor
    {
        // Defaults needed by constructor
        $this->configProvider->method('getValue')->willReturnMap([
            [ConfigOption::PoweredByText, '<b>Powered</b> by phpList'],
            [ConfigOption::SystemMessageTemplate, null],
        ]);

        return new SystemMailConstructor(
            html2Text: $this->html2Text,
            configProvider: $this->configProvider,
            templateRepository: $this->templateRepository,
            templateImageManager: $this->templateImageManager,
            poweredByPhplist: $poweredByPhplist,
        );
    }

    public function testPlainTextWithoutTemplateLinkifiedAndNl2br(): void
    {
        $constructor = $this->createConstructor();

        // Html2Text is not used when source is plain text
        $this->html2Text->expects($this->never())->method('__invoke');

        [$html, $text] = $constructor('Line1' . "\n" . 'Visit http://example.com', 'Subject');

        $this->assertSame("Line1\nVisit http://example.com", $text);
        $this->assertStringContainsString('Line1<br', $html);
        $this->assertStringContainsString('<a href="http://example.com">http://example.com</a>', $html);
    }

    public function testHtmlSourceWithoutTemplateUsesHtml2Text(): void
    {
        $constructor = $this->createConstructor();

        $this->html2Text->expects($this->once())
            ->method('__invoke')
            ->with('<p><strong>Hello</strong></p>')
            ->willReturn('Hello');

        [$html, $text] = $constructor('<p><strong>Hello</strong></p>', 'Subject');

        $this->assertSame('<p><strong>Hello</strong></p>', $html);
        $this->assertSame('Hello', $text);
    }

    public function testTemplateWithSignaturePlaceholderUsesPoweredByImageWhenFlagFalse(): void
    {
        // Configure template usage
        $this->configProvider->method('getValue')->willReturnMap([
            [ConfigOption::PoweredByText, '<b>Powered</b>'],
            [ConfigOption::SystemMessageTemplate, '10'],
            [ConfigOption::PoweredByImage, '<img alt="" src="/assets/power-phplist.png" />'],
        ]);

        $template = new Template('sys-template');
        $template->setContent('<html><body>[SUBJECT]: [CONTENT] [SIGNATURE]</body></html>');
        $template->setText("SUBJ: [SUBJECT]\n[BODY]\n[CONTENT]\n[SIGNATURE]");

        $this->templateRepository->method('findOneById')->with(10)->willReturn($template);

        $this->templateImageManager->expects($this->once())
            ->method('parseLogoPlaceholders')
            ->with($this->callback(fn ($html) => is_string($html)))
            ->willReturnArgument(0);

        // Plain text input so Html2Text is called only for powered by text when building text part
        $this->html2Text->expects($this->once())
            ->method('__invoke')
            ->with('<b>Powered</b>')
            ->willReturn('Powered');

        $constructor = new SystemMailConstructor(
            html2Text: $this->html2Text,
            configProvider: $this->configProvider,
            templateRepository: $this->templateRepository,
            templateImageManager: $this->templateImageManager,
            poweredByPhplist: false,
        );

        [$html, $text] = $constructor('Body', 'Subject');

        // HTML should contain processed powered-by image (src rewritten to powerphplist.png) in place of [SIGNATURE]
        $this->assertStringContainsString('Subject: Body', $html);
        $this->assertStringContainsString('src="powerphplist.png"', $html);

        // Text should include powered by text substituted into [SIGNATURE]
        $this->assertStringContainsString("SUBJ: Subject\n[BODY]\nBody\nPowered", $text);
    }

    public function testTemplateWithoutSignatureAppendsPoweredByTextAndBeforeBodyEndWhenHtml(): void
    {
        // Configure template usage with poweredByPhplist=true (use text snippet instead of image)
        $this->configProvider->method('getValue')->willReturnMap([
            [ConfigOption::PoweredByText, '<i>PB</i>'],
            [ConfigOption::SystemMessageTemplate, '11'],
        ]);

        $template = new Template('sys-template');
        $template->setContent('<html><body>[CONTENT]</body></html>');
        $template->setText('[CONTENT]');
        $this->templateRepository->method('findOneById')->with(11)->willReturn($template);

        $this->templateImageManager->method('parseLogoPlaceholders')->willReturnCallback(static fn ($h) => $h);

        // Html2Text is called twice: once for the HTML message -> text, and once for powered-by text
        $this->html2Text->expects($this->exactly(2))
            ->method('__invoke')
            ->withConsecutive(
                ['Hello <b>World</b>'],
                ['<i>PB</i>']
            )
            ->willReturnOnConsecutiveCalls('Hello World', 'PB');

        $constructor = new SystemMailConstructor(
            html2Text: $this->html2Text,
            configProvider: $this->configProvider,
            templateRepository: $this->templateRepository,
            templateImageManager: $this->templateImageManager,
            poweredByPhplist: true,
        );

        [$html, $text] = $constructor('Hello <b>World</b>', 'Sub');

        // HTML path: since poweredByPhplist=true, raw PoweredByText should be inserted before </body>
        $this->assertStringContainsString('Hello <b>World</b>', $html);
        $this->assertMatchesRegularExpression('~<i>PB</i></body>\s*</html>$~', $html);

        // TEXT path: PoweredByText (converted) appended with two newlines since no [SIGNATURE]
        $this->assertSame("Hello World\n\nPB", $text);
    }
}
