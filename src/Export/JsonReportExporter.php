<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of Vizion for BASE3 Framework.
 *
 * Vizion extends the BASE3 framework with modular, visual display
 * components for reports and structured data.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/vizion
 * https://github.com/ddbase3/Vizion
 **********************************************************************/

namespace Vizion\Export;

final class JsonReportExporter extends AbstractReportExporter {

	public static function getName(): string {
		return 'jsonreportexporter';
	}

	public function toString(): string {
		$result = $this->requireResult();
		$data = [
			'columns' => $result->columns,
			'rows' => $result->rows,
		];

		if($result->debugSql !== null) {
			$data['debugSql'] = $result->debugSql;
		}

		$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if($json === false) {
			throw new \RuntimeException('Failed to encode JSON report export.');
		}

		return $json;
	}

	public function getMimeType(): string {
		return 'application/json; charset=utf-8';
	}

	public function getFileExtension(): string {
		return 'json';
	}
}
