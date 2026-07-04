<?php declare(strict_types=1);

namespace Vizion\Renderer\Column;

use Vizion\Api\IReportColumnRenderer;

/**
 * Default column renderer that delegates the cell content to the value renderer.
 */
final class ValueColumnRenderer implements IReportColumnRenderer {

	public static function getName(): string {
		return 'valuecolumnrenderer';
	}

	public function getRendererType(): string {
		return 'value';
	}

	public function getAliases(): array {
		return ['default', 'text', 'cell'];
	}

	public function getClientRendererKey(array $rendererConfig): string {
		return 'vizion.column.value';
	}

	public function getAssetPaths(array $rendererConfig): array {
		return [];
	}

	public function configureColumn(array $column, array $field, array $rendererConfig): array {
		$config = $this->extractRendererConfig($rendererConfig);

		if($config !== []) {
			$column['columnRendererConfig'] = $config;
		}

		return $column;
	}

	/** @return array<string,mixed> */
	private function extractRendererConfig(array $rendererConfig): array {
		unset($rendererConfig['type']);

		return $rendererConfig;
	}
}
