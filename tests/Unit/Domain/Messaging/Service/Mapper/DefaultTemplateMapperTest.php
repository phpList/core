<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service\Mapper;

use InvalidArgumentException;
use PhpList\Core\Domain\Messaging\Service\Mapper\DefaultTemplateMapper;
use PHPUnit\Framework\TestCase;

class DefaultTemplateMapperTest extends TestCase
{
    private DefaultTemplateMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new DefaultTemplateMapper();
    }

    public function testListReturnsConfiguredDefaults(): void
    {
        $defaults = $this->mapper->list();

        $this->assertIsArray($defaults);
        $this->assertNotEmpty($defaults);
        $this->assertSame('system', $defaults[0]['key']);
        $this->assertSame('System', $defaults[0]['name']);
        $this->assertSame('system.html', $defaults[0]['file']);
        $this->assertArrayHasKey('description', $defaults[0]);
    }

    public function testFindByKeyReturnsExpectedTemplate(): void
    {
        $template = $this->mapper->findByKey('responsive');

        $this->assertSame('responsive', $template['key']);
        $this->assertSame('Responsive', $template['name']);
        $this->assertSame('responsive.html', $template['file']);
    }

    public function testFindByKeyThrowsForUnknownKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Default template with key "unknown" does not exist.');

        $this->mapper->findByKey('unknown');
    }

    public function testLoadContentReadsTemplateFile(): void
    {
        $content = $this->mapper->loadContent('system.html');

        $this->assertIsString($content);
        $this->assertNotSame('', $content);
        $this->assertStringContainsString('[CONTENT]', $content);
    }
}
