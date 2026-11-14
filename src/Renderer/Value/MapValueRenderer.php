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

namespace Base3\Vizion\Renderer\Value;

use Base3\Vizion\Api\IReportValueRenderer;

final class MapValueRenderer implements IReportValueRenderer {

	public static function getName(): string {
		return 'mapvaluerenderer';
	}

	public function renderValue(mixed $value, array $row, array $field, array $rendererConfig): string {
		$placeholder = (string)($rendererConfig['placeholder'] ?? '—');

		if($value === null || $value === '') {
			return $placeholder;
		}

		$key = (string)$value;
		$values = isset($rendererConfig['values']) && is_array($rendererConfig['values']) ? $rendererConfig['values'] : [];

		if(array_key_exists($key, $values) && is_scalar($values[$key])) {
			return (string)$values[$key];
		}

		return $key;
	}

	public function rendersHtml(): bool {
		return false;
	}
}
