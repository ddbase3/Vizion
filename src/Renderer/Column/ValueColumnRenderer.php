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

namespace Base3\Vizion\Renderer\Column;

use Base3\Vizion\Api\IReportColumnRenderer;

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
