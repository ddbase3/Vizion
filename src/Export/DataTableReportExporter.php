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

use Base3\Api\IAssetResolver;

final class DataTableReportExporter extends AbstractReportExporter {

	public function __construct(private readonly IAssetResolver $assetResolver) {}

	public static function getName(): string {
		return 'datatablereportexporter';
	}

	public function toString(): string {
		$result = $this->requireResult();
		$columns = [];

		foreach($result->columns as $column) {
			$columns[] = [
				'key' => (string)($column['name'] ?? ''),
				'label' => $this->getColumnLabel($column)
			];
		}

		$uniqueId = 'dt' . uniqid();
		$dataJson = json_encode($result->rows, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
		$columnJson = json_encode($columns, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
		$scriptUrl = json_encode($this->assetResolver->resolve('plugin/ClientStack/assets/jquerydatatable/jquery.datatable.min.js'));
		$styleUrl = json_encode($this->assetResolver->resolve('plugin/ClientStack/assets/jquerydatatable/jquery.datatable.min.css'));

		return '<div id="' . $uniqueId . '"></div>'
			. '<script>(async()=>{'
			. 'await AssetLoader.loadScriptAsync(' . $scriptUrl . ');'
			. 'await AssetLoader.loadCssAsync(' . $styleUrl . ');'
			. 'var data=' . $dataJson . ';var cols=' . $columnJson . ';'
			. '$("#' . $uniqueId . '").jqueryDataTable({' 
			. 'columns:cols,data:data,layoutTargets:{'
			. '".header-left":["columnSelector"],'
			. '".header-right":["compactPager"],'
			. '".footer-left":["resetButton"],'
			. '".footer-center":["info"],'
			. '".footer-right":["pageSizeSelector"]},'
			. 'pageSize:10,pageSizeOptions:[5,10,20]});'
			. '})();</script>'
			. '<style>#' . $uniqueId . ' *{line-height:1.2;}#' . $uniqueId . ' td,#' . $uniqueId . ' th{padding:5px;}#' . $uniqueId . ' td{background:#fff;}</style>';
	}

	public function getMimeType(): string {
		return 'text/html; charset=utf-8';
	}

	public function getFileExtension(): string {
		return 'html';
	}
}
