<?php declare(strict_types=1);

namespace Vizion\Test\Renderer\Column;

use PHPUnit\Framework\TestCase;
use Vizion\Renderer\Column\ValueColumnRenderer;

final class ValueColumnRendererBehaviorTest extends TestCase {

	public function testIdentityAndClientRendererMetadata(): void {
		$renderer = new ValueColumnRenderer();
		$this->assertSame('valuecolumnrenderer', $renderer::getName());
		$this->assertSame('value', $renderer->getRendererType());
		$this->assertSame(['default', 'text', 'cell'], $renderer->getAliases());
		$this->assertSame('vizion.column.value', $renderer->getClientRendererKey([]));
		$this->assertSame([], $renderer->getAssetPaths([]));
	}

	public function testConfiguredRendererStripsTypeAndPreservesOtherOptions(): void {
		$renderer = new ValueColumnRenderer();
		$column = ['key' => 'name', 'width' => 120];
		$this->assertSame($column, $renderer->configureColumn($column, [], ['type' => 'value']));
		$this->assertSame($column + ['columnRendererConfig' => ['mode' => 'compact', 'lines' => 2]], $renderer->configureColumn($column, [], [
			'type' => 'value', 'mode' => 'compact', 'lines' => 2
		]));
	}
}
