<?php declare(strict_types=1);

namespace Vizion\Renderer\Cell;

use Vizion\Api\IReportCellRenderer;

final class TextCellRenderer implements IReportCellRenderer {

	public static function getName(): string {
		return 'textcellrenderer';
	}

	public function getRendererType(): string {
		return 'text';
	}

	public function getAliases(): array {
		return ['default', 'string'];
	}

	public function getClientRendererKey(array $rendererConfig): string {
		return 'vizion.text';
	}

	public function configureColumn(array $column, array $field, array $rendererConfig): array {
		return array_merge($column, $this->extractRendererConfig($rendererConfig));
	}

	/** @return array<string,mixed> */
	private function extractRendererConfig(array $rendererConfig): array {
		unset($rendererConfig['type']);
		return $rendererConfig;
	}
}
