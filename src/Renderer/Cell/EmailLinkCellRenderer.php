<?php declare(strict_types=1);

namespace Vizion\Renderer\Cell;

use Vizion\Api\IReportCellRenderer;

final class EmailLinkCellRenderer implements IReportCellRenderer {

	public static function getName(): string {
		return 'emaillinkcellrenderer';
	}

	public function getRendererType(): string {
		return 'email-link';
	}

	public function getAliases(): array {
		return ['emaillink', 'email'];
	}

	public function getClientRendererKey(array $rendererConfig): string {
		return 'vizion.emailLink';
	}

	public function configureColumn(array $column, array $field, array $rendererConfig): array {
		return $column;
	}
}
