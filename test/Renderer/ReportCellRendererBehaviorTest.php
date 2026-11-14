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

namespace Base3\Vizion\Test\Renderer;

use Base3\Test\Core\ClassMapStub;
use PHPUnit\Framework\TestCase;
use Base3\Vizion\Renderer\Column\ValueColumnRenderer;
use Base3\Vizion\Renderer\Formatter\DateValueFormatter;
use Base3\Vizion\Renderer\Formatter\NumberValueFormatter;
use Base3\Vizion\Renderer\Formatter\TextValueFormatter;
use Base3\Vizion\Renderer\ReportCellRendererService;
use Base3\Vizion\Renderer\Value\EmailLinkValueRenderer;
use Base3\Vizion\Renderer\Value\MapValueRenderer;
use Base3\Vizion\Renderer\Value\TextValueRenderer;

final class ReportCellRendererBehaviorTest extends TestCase {

	public function testColumnsUseFormatterDefaultsAndPreserveVisibleMetadata(): void {
		$service = $this->service();
		$columns = $service->buildGridColumns([
			['config' => ['label' => 'Missing alias']],
			['alias' => 'name', 'config' => ['label' => 'Name', 'width' => '120', 'lines' => '0', 'visible' => false]],
			['alias' => 'count', 'config' => ['type' => 'integer']],
			['alias' => 'joined', 'config' => ['type' => 'datetime']],
			['alias' => 'blob', 'config' => ['type' => 'json']]
		]);
		$this->assertCount(4, $columns);
		$this->assertSame('name', $columns[0]['key']);
		$this->assertSame('Name', $columns[0]['label']);
		$this->assertSame(120, $columns[0]['width']);
		$this->assertSame(1, $columns[0]['lines']);
		$this->assertFalse($columns[0]['visible']);
		$this->assertSame('number', $columns[1]['formatter']['type']);
		$this->assertSame('datetime', $columns[2]['formatter']['type']);
		$this->assertTrue($columns[3]['monospace']);
	}

	public function testCustomRendererAndFormatterConfigArePreserved(): void {
		$columns = $this->service()->buildGridColumns([[
			'alias' => 'email', 'config' => [
				'formatter' => ['type' => 'number', 'maximumFractionDigits' => 4],
				'columnRenderer' => ['type' => 'CELL', 'mode' => 'compact'],
				'valueRenderer' => ['type' => 'EMAILLINKVALUERENDERER']
			]
		]]);
		$this->assertSame('number', $columns[0]['formatter']['type']);
		$this->assertSame(4, $columns[0]['formatter']['maximumFractionDigits']);
		$this->assertSame('cell', $columns[0]['columnRendererType']);
		$this->assertSame(['mode' => 'compact'], $columns[0]['columnRendererConfig']);
		$this->assertTrue($columns[0]['html']);
	}

	public function testRowsAreTransformedOnlyWhenExplicitRendererIsConfigured(): void {
		$service = $this->service();
		$fields = [
			['alias' => 'status', 'config' => ['valueRenderer' => ['type' => 'mapvaluerenderer', 'values' => [1 => 'Active']]]],
			['alias' => 'name', 'config' => ['label' => 'Name']],
			['alias' => 'email', 'config' => ['valueRenderer' => 'invalid']],
			['alias' => 'note', 'config' => ['valueRenderer' => ['type' => 'textvaluerenderer', 'placeholder' => 'none']]],
		];
		$rows = $service->renderGridRows([
			['status' => 1, 'name' => '<b>Name</b>', 'note' => null],
			['status' => 0, 'name' => 'Bob', 'note' => 'hello'],
		], $fields);
		$this->assertSame('Active', $rows[0]['status']);
		$this->assertSame('0', $rows[1]['status']);
		$this->assertSame('<b>Name</b>', $rows[0]['name']);
		$this->assertSame('none', $rows[0]['note']);
		$this->assertSame('hello', $rows[1]['note']);
	}

	public function testNonArrayRowsAndMissingRenderFieldsRemainUnchanged(): void {
		$service = $this->service();
		$rows = [null, 'not-array', ['name' => 'Alice'], []];
		$this->assertSame($rows, $service->renderGridRows($rows, [['alias' => 'name']]));
		$this->assertSame([null, 'not-array', ['name' => 'Alice'], ['name' => 'none']], $service->renderGridRows($rows, [[
			'alias' => 'name', 'config' => ['valueRenderer' => ['type' => 'textvaluerenderer', 'placeholder' => 'none']]
		]]));
	}

	public function testInternalColumnAssetMetadataIsRemovedWithoutDroppingOtherProperties(): void {
		$service = $this->service();
		$this->assertSame([], $service->collectGridRendererAssetPaths([['key' => 'name']]));
		$this->assertSame([['key' => 'name'], 'other', ['key' => 'age']], $service->stripInternalGridColumnMetadata([
			['key' => 'name', '_rendererAssetPaths' => ['/old']],
			'other',
			['key' => 'age']
		]));
	}

	private function service(): ReportCellRendererService {
		$map = new ClassMapStub();
		foreach([
			TextValueFormatter::class, NumberValueFormatter::class, DateValueFormatter::class,
			ValueColumnRenderer::class, TextValueRenderer::class, MapValueRenderer::class,
			EmailLinkValueRenderer::class
		] as $class) {
			$map->register($class);
		}
		return new ReportCellRendererService($map);
	}
}
