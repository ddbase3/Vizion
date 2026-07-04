<?php declare(strict_types=1);

namespace Vizion\Renderer\Cell;

use Vizion\Api\IReportCellRenderer;

final class DateCellRenderer implements IReportCellRenderer {

	public static function getName(): string {
		return 'datecellrenderer';
	}

	public function getRendererType(): string {
		return 'date';
	}

	public function getAliases(): array {
		return ['datetime'];
	}

	public function getClientRendererKey(array $rendererConfig): string {
		return 'vizion.date';
	}

	public function configureColumn(array $column, array $field, array $rendererConfig): array {
		return $column;
	}
}
