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

final class TextValueRenderer implements IReportValueRenderer {

	public static function getName(): string {
		return 'textvaluerenderer';
	}

	public function renderValue(mixed $value, array $row, array $field, array $rendererConfig): string {
		return $this->stringValue($value, (string)($rendererConfig['placeholder'] ?? '—'));
	}

	public function rendersHtml(): bool {
		return false;
	}

	private function stringValue(mixed $value, string $placeholder): string {
		if($value === null || $value === '') {
			return $placeholder;
		}

		if(is_bool($value)) {
			return $value ? 'true' : 'false';
		}

		if(is_scalar($value)) {
			return (string)$value;
		}

		$json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		return is_string($json) && $json !== '' ? $json : $placeholder;
	}
}
