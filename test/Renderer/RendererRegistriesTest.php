<?php declare(strict_types=1);

namespace Vizion\Test\Renderer;

use Base3\Test\Core\ClassMapStub;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Vizion\Renderer\Column\ValueColumnRenderer;
use Vizion\Renderer\Formatter\NumberValueFormatter;
use Vizion\Renderer\Formatter\TextValueFormatter;
use Vizion\Renderer\ReportColumnRendererRegistry;
use Vizion\Renderer\ReportRowRendererRegistry;
use Vizion\Renderer\ReportValueFormatterRegistry;
use Vizion\Renderer\ReportValueRendererRegistry;
use Vizion\Renderer\Value\TextValueRenderer;

final class RendererRegistriesTest extends TestCase {

	public function testFormatterLookupAcceptsNameAliasesAndFallback(): void {
		$map = new ClassMapStub();
		$map->register(TextValueFormatter::class)->register(NumberValueFormatter::class);
		$registry = new ReportValueFormatterRegistry($map);
		$this->assertInstanceOf(NumberValueFormatter::class, $registry->getFormatter(' INTEGER '));
		$this->assertInstanceOf(NumberValueFormatter::class, $registry->getFormatter('numbervalueformatter'));
		$this->assertInstanceOf(TextValueFormatter::class, $registry->getFormatter('unknown-format'));
		$this->assertCount(2, $registry->getFormatters());
	}

	public function testFormatterRegistryThrowsIfDefaultImplementationIsMissing(): void {
		$registry = new ReportValueFormatterRegistry(new ClassMapStub());
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Missing Vizion text value formatter implementation.');
		$registry->getFormatter('unknown');
	}

	public function testColumnRendererLookupUsesSemanticTypeAndDefault(): void {
		$map = new ClassMapStub();
		$map->register(ValueColumnRenderer::class);
		$registry = new ReportColumnRendererRegistry($map);
		$this->assertInstanceOf(ValueColumnRenderer::class, $registry->getRenderer(' VALUE '));
		$this->assertInstanceOf(ValueColumnRenderer::class, $registry->getRenderer('cell'));
		$this->assertInstanceOf(ValueColumnRenderer::class, $registry->getRenderer('non-existing'));
		$this->assertCount(1, $registry->getRenderers());
	}

	public function testColumnRendererRegistryThrowsIfDefaultIsMissing(): void {
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Missing Vizion default column renderer implementation.');
		(new ReportColumnRendererRegistry(new ClassMapStub()))->getRenderer('missing');
	}

	public function testValueRendererRegistryCachesNormalizedNames(): void {
		$map = new ClassMapStub();
		$map->register(TextValueRenderer::class);
		$registry = new ReportValueRendererRegistry($map);
		$first = $registry->getRenderer(' TextValueRenderer ');
		$this->assertInstanceOf(TextValueRenderer::class, $first);
		$this->assertSame($first, $registry->getRenderer('textvaluerenderer'));
		$this->assertSame($first, $registry->getRenderer(''));
		$this->assertSame($first, $registry->getRenderer('  '));
	}

	public function testValueRendererRegistryRejectsUnregisteredTypes(): void {
		$registry = new ReportValueRendererRegistry(new ClassMapStub());
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Unknown Vizion value renderer: unknown');
		$registry->getRenderer(' UNKNOWN ');
	}

	public function testRowRendererRegistryReturnsNullWithoutMatches(): void {
		$registry = new ReportRowRendererRegistry(new ClassMapStub());
		$this->assertNull($registry->getRenderer('unknown'));
		$this->assertSame([], $registry->getRenderers());
	}
}
