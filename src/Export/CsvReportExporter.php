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

final class CsvReportExporter extends AbstractReportExporter {

	public static function getName(): string {
		return 'csvreportexporter';
	}

	public function toString(): string {
		$result = $this->requireResult();
		$stream = fopen('php://temp', 'r+');

		if($stream === false) {
			throw new \RuntimeException('Failed to open temporary stream.');
		}

		fputcsv($stream, array_map(
			fn(array $column): string => $this->getColumnLabel($column),
			$result->columns
		));

		foreach($result->rows as $row) {
			if(!is_array($row)) {
				continue;
			}

			$values = [];
			foreach($result->columns as $index => $column) {
				$values[] = $this->getRowValue($row, $column, $index);
			}
			fputcsv($stream, $values);
		}

		rewind($stream);
		$csv = stream_get_contents($stream);
		fclose($stream);

		if($csv === false) {
			throw new \RuntimeException('Failed to read CSV stream.');
		}

		return $csv;
	}

	public function getMimeType(): string {
		return 'text/csv; charset=utf-8';
	}

	public function getFileExtension(): string {
		return 'csv';
	}
}
