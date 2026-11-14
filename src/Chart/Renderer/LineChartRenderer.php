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

namespace Base3\Vizion\Chart\Renderer;

final class LineChartRenderer extends AbstractChartRenderer {

	public static function getName(): string {
		return 'linechartrenderer';
	}

	public function getChartType(): string {
		return 'line';
	}

	protected function getDefaultOptions(array $chartConfig, array $measures, array $dimension): array {
		$options = parent::getDefaultOptions($chartConfig, $measures, $dimension);
		$options['elements'] = [
			'line' => [
				'tension' => 0.25
			]
		];

		return $options;
	}
}
