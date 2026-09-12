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

final class HtmlPageReportExporter extends AbstractReportExporter {

	public static function getName(): string {
		return 'htmlpagereportexporter';
	}

	public function toString(): string {
		$tableExporter = new HtmlTableReportExporter();
		$tableExporter->setResult($this->requireResult());
		$table = $tableExporter->toString();

		return '<!DOCTYPE html><html><head>'
			. '<meta charset="utf-8">'
			. '<title>Report</title>'
			. '<style>table{border-collapse:collapse;width:100%;}th,td{padding:6px;text-align:left;}th{background:#f4f4f4;}</style>'
			. '</head><body>'
			. $table
			. '</body></html>';
	}

	public function getMimeType(): string {
		return 'text/html; charset=utf-8';
	}

	public function getFileExtension(): string {
		return 'html';
	}
}
