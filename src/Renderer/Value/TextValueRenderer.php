<?php declare(strict_types=1);

namespace Vizion\Renderer\Value;

use Vizion\Api\IReportValueRenderer;

/**
 * Default renderer for one formatted text value.
 */
final class TextValueRenderer implements IReportValueRenderer {

	public static function getName(): string {
		return 'textvaluerenderer';
	}

	public function getRendererType(): string {
		return 'text';
	}

	public function getAliases(): array {
		return ['default', 'string', 'plain'];
	}

	public function getClientRendererKey(array $rendererConfig): string {
		return 'vizion.value.text';
	}

	public function getAssetPaths(array $rendererConfig): array {
		return [];
	}

	public function configureValue(array $column, array $field, array $rendererConfig): array {
		$column['valueRendererConfig'] = $this->extractRendererConfig($rendererConfig);

		return $column;
	}

	/** @return array<string,mixed> */
	private function extractRendererConfig(array $rendererConfig): array {
		unset($rendererConfig['type']);

		return $rendererConfig;
	}
}
