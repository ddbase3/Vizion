<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of Vizion for BASE3 Framework.
 *
 * Vizion provides reporting and data visualization for BASE3 projects.
 * It supports grids, interactive tables, charts and data exporters.
 * Visual components consume structured reporting and query results.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/vizion
 * https://github.com/ddbase3/Vizion
 **********************************************************************/

namespace Base3\Vizion\Test\Chart;

use Base3\Test\Core\ClassMapStub;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Base3\Vizion\Chart\Renderer\BarChartRenderer;
use Base3\Vizion\Chart\Renderer\DoughnutChartRenderer;
use Base3\Vizion\Chart\Renderer\PieChartRenderer;
use Base3\Vizion\Chart\ReportChartRendererRegistry;

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
