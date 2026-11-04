<?php declare(strict_types=1);

namespace Vizion\Test\Chart;

use Base3\Test\Core\ClassMapStub;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Vizion\Chart\Renderer\BarChartRenderer;
use Vizion\Chart\Renderer\DoughnutChartRenderer;
use Vizion\Chart\Renderer\PieChartRenderer;
use Vizion\Chart\ReportChartRendererRegistry;

final class ReportChartRendererRegistryTest extends TestCase {

	public function testResolvesTypesAndAliasesWithDefaultBar(): void {
		$map = new ClassMapStub();
		$map->register(BarChartRenderer::class)->register(DoughnutChartRenderer::class)->register(PieChartRenderer::class);
		$registry = new ReportChartRendererRegistry($map);
		$this->assertInstanceOf(BarChartRenderer::class, $registry->getRenderer('bar'));
		$this->assertInstanceOf(PieChartRenderer::class, $registry->getRenderer('polar'));
		$this->assertInstanceOf(DoughnutChartRenderer::class, $registry->getRenderer('DONUT'));
		$this->assertInstanceOf(BarChartRenderer::class, $registry->getRenderer('unknown'));
		$this->assertCount(3, $registry->getRenderers());
	}

	public function testMissingDefaultRendererRaisesRuntimeError(): void {
		$registry = new ReportChartRendererRegistry(new ClassMapStub());
		$this->expectException(RuntimeException::class);
		$registry->getRenderer('unknown');
	}
}
