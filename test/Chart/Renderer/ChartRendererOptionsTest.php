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

namespace Base3\Vizion\Test\Chart\Renderer;

use PHPUnit\Framework\TestCase;
use Base3\Vizion\Chart\Renderer\BarChartRenderer;
use Base3\Vizion\Chart\Renderer\DoughnutChartRenderer;
use Base3\Vizion\Chart\Renderer\LineChartRenderer;
use Base3\Vizion\Chart\Renderer\PieChartRenderer;

final class ChartRendererOptionsTest extends TestCase {

	public function testBarChartDefaultsToResponsiveSingleDatasetWithoutLegend(): void {
		$renderer = new BarChartRenderer();
		$this->assertSame('barchartrenderer', $renderer::getName());
		$config = $renderer->buildClientConfig([], [['alias' => 'count']], ['field' => 'status']);
		$this->assertSame('bar', $config['type']);
		$this->assertSame('', $config['title']);
		$this->assertSame(['field' => 'status'], $config['dimension']);
		$this->assertFalse($config['options']['maintainAspectRatio']);
		$this->assertTrue($config['options']['responsive']);
		$this->assertFalse($config['options']['plugins']['legend']['display']);
		$this->assertFalse($config['options']['plugins']['title']['display']);
	}

	public function testTwoMeasuresEnableLegendAndTitle(): void {
		$config = (new BarChartRenderer())->buildClientConfig(['title' => 'Counts'], [['alias' => 'a'], ['alias' => 'b']], []);
		$this->assertTrue($config['options']['plugins']['legend']['display']);
		$this->assertTrue($config['options']['plugins']['title']['display']);
		$this->assertSame('Counts', $config['options']['plugins']['title']['text']);
		$this->assertSame('Counts', $config['title']);
	}

	public function testNestedOptionsOverrideDefaultsWithoutDroppingSiblings(): void {
		$config = (new BarChartRenderer())->buildClientConfig([
			'options' => [
				'responsive' => false,
				'plugins' => ['legend' => ['position' => 'bottom'], 'title' => ['text' => 'Overridden']],
				'scales' => ['y' => ['beginAtZero' => true]]
			]
		], [['alias' => 'count']], []);
		$this->assertFalse($config['options']['responsive']);
		$this->assertFalse($config['options']['plugins']['legend']['display']);
		$this->assertSame('bottom', $config['options']['plugins']['legend']['position']);
		$this->assertFalse($config['options']['plugins']['title']['display']);
		$this->assertSame('Overridden', $config['options']['plugins']['title']['text']);
		$this->assertTrue($config['options']['scales']['y']['beginAtZero']);
	}

	public function testPieAndDoughnutAlwaysExposeLegend(): void {
		$pie = new PieChartRenderer();
		$doughnut = new DoughnutChartRenderer();
		$this->assertSame('pie', $pie->getChartType());
		$this->assertSame(['polar'], $pie->getAliases());
		$this->assertSame('doughnut', $doughnut->getChartType());
		$this->assertSame(['donut'], $doughnut->getAliases());
		$this->assertTrue($pie->buildClientConfig([], [['alias' => 'only']], [])['options']['plugins']['legend']['display']);
		$this->assertTrue($doughnut->buildClientConfig([], [['alias' => 'only']], [])['options']['plugins']['legend']['display']);
	}

	public function testLineChartProvidesSmoothingAndHonorsCustomOption(): void {
		$renderer = new LineChartRenderer();
		$this->assertSame('linechartrenderer', $renderer::getName());
		$this->assertSame('line', $renderer->getChartType());
		$this->assertSame(0.25, $renderer->buildClientConfig([], [], [])['options']['elements']['line']['tension']);
		$config = $renderer->buildClientConfig(['options' => ['elements' => ['line' => ['tension' => 0.0]]]], [], []);
		$this->assertSame(0.0, $config['options']['elements']['line']['tension']);
	}
}
