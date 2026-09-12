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

final class HtmlTableReportExporter extends AbstractReportExporter {

	public static function getName(): string {
		return 'htmltablereportexporter';
	}

	public function toString(): string {
		$result = $this->requireResult();
		$html = '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse: collapse; font-family: Arial, sans-serif; font-size: 13px;">';
		$html .= '<thead><tr>';

		foreach($result->columns as $column) {
			$html .= '<th style="background-color: #f0f0f0; font-weight: bold; border: 1px solid #ccc;">'
				. htmlspecialchars($this->getColumnLabel($column), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
				. '</th>';
		}

		$html .= '</tr></thead><tbody>';

		foreach($result->rows as $row) {
			if(!is_array($row)) {
				continue;
			}

			$html .= '<tr>';
			foreach($result->columns as $index => $column) {
				$value = $this->getRowValue($row, $column, $index);
				$html .= '<td style="border: 1px solid #ccc;">'
					. htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
					. '</td>';
			}
			$html .= '</tr>';
		}

		$html .= '</tbody></table>';
		return $html;
	}

	public function getMimeType(): string {
		return 'text/html; charset=utf-8';
	}

	public function getFileExtension(): string {
		return 'html';
	}
}
