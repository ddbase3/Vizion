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

final class BarChartReportExporter extends AbstractReportExporter {

	public function __construct(private readonly IAssetResolver $assetResolver) {}

	public static function getName(): string {
		return 'barchartreportexporter';
	}

	public function toString(): string {
		$result = $this->requireResult();
		if(count($result->columns) < 2) {
			throw new \RuntimeException('Bar chart export requires at least two result columns.');
		}

		$xKey = (string)($result->columns[0]['name'] ?? '');
		$yKey = (string)($result->columns[1]['name'] ?? '');
		$label = $this->getColumnLabel($result->columns[1]);
		$uniqueId = 'bar' . uniqid();
		$rowsJson = json_encode($result->rows, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
		$scriptUrl = json_encode($this->assetResolver->resolve('plugin/ClientStack/assets/chart/chart.js'));
		$xJson = json_encode($xKey);
		$yJson = json_encode($yKey);
		$labelJson = json_encode($label);

		return '<div style="height:300px;"><canvas id="' . $uniqueId . '"></canvas></div>'
			. '<script>(async()=>{'
			. 'await AssetLoader.loadScriptAsync(' . $scriptUrl . ');'
			. 'var result=' . $rowsJson . ';var labels=[];var data=[];'
			. 'for(let i in result){labels.push(result[i][' . $xJson . ']);data.push(result[i][' . $yJson . ']);}'
			. 'var ctx=document.getElementById(' . json_encode($uniqueId) . ').getContext("2d");'
			. 'new Chart(ctx,{type:"bar",data:{labels:labels,datasets:[{label:' . $labelJson . ',data:data}]},options:{responsive:true,maintainAspectRatio:false}});'
			. '})();</script>';
	}

	public function getMimeType(): string {
		return 'text/html; charset=utf-8';
	}

	public function getFileExtension(): string {
		return 'html';
	}
}
