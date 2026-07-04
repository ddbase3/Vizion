<?php declare(strict_types=1);

namespace Vizion\Renderer\Cell;

use Vizion\Api\IReportCellRenderer;

final class NumberCellRenderer implements IReportCellRenderer {

	public static function getName(): string {
		return 'numbercellrenderer';
	}

	public function getRendererType(): string {
		return 'number';
	}

	public function getAliases(): array {
		return ['int', 'integer', 'float', 'decimal'];
	}

	public function getClientRendererKey(array $rendererConfig): string {
		return 'vizion.text';
	}

	public function configureColumn(array $column, array $field, array $rendererConfig): array {
		return $column;
	}
}
