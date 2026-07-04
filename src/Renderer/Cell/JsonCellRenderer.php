<?php declare(strict_types=1);

namespace Vizion\Renderer\Cell;

use Vizion\Api\IReportCellRenderer;

final class JsonCellRenderer implements IReportCellRenderer {

	public static function getName(): string {
		return 'jsoncellrenderer';
	}

	public function getRendererType(): string {
		return 'json';
	}

	public function getAliases(): array {
		return ['code'];
	}

	public function getClientRendererKey(array $rendererConfig): string {
		return 'vizion.json';
	}

	public function configureColumn(array $column, array $field, array $rendererConfig): array {
		$column['monospace'] = true;
		return $column;
	}
}
