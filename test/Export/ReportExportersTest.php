<?php declare(strict_types=1);

namespace Vizion\Test\Export;

use Base3\Api\IAssetResolver;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Api\IReportExporter;
use ResourceFoundation\Dto\QueryResult;
use Vizion\Export\BarChartReportExporter;
use Vizion\Export\CsvReportExporter;
use Vizion\Export\DataTableReportExporter;
use Vizion\Export\ExcelHtmlReportExporter;
use Vizion\Export\HtmlPageReportExporter;
use Vizion\Export\HtmlTableReportExporter;
use Vizion\Export\JsonReportExporter;
use Vizion\Export\PieChartReportExporter;
use Vizion\Export\XlsxReportExporter;

final class ReportExportersTest extends TestCase {

	public function testExporterNamesStayStable(): void {
		$this->assertSame('csvreportexporter', CsvReportExporter::getName());
		$this->assertSame('excelhtmlreportexporter', ExcelHtmlReportExporter::getName());
		$this->assertSame('xlsxreportexporter', XlsxReportExporter::getName());
		$this->assertSame('jsonreportexporter', JsonReportExporter::getName());
		$this->assertSame('htmltablereportexporter', HtmlTableReportExporter::getName());
		$this->assertSame('htmlpagereportexporter', HtmlPageReportExporter::getName());
		$this->assertSame('datatablereportexporter', DataTableReportExporter::getName());
		$this->assertSame('barchartreportexporter', BarChartReportExporter::getName());
		$this->assertSame('piechartreportexporter', PieChartReportExporter::getName());
	}

	public function testDownloadExportersUseProvidedQueryResult(): void {
		$result = $this->makeResult();
		$exporters = [
			new CsvReportExporter(),
			new ExcelHtmlReportExporter(),
			new XlsxReportExporter(),
			new JsonReportExporter(),
		];

		foreach($exporters as $exporter) {
			$this->assertInstanceOf(IReportExporter::class, $exporter);
			$this->assertSame($exporter, $exporter->setResult($result));
			$this->assertSame($result, $exporter->getResult());
			$this->assertNotSame('', $exporter->toString());
		}
	}

	public function testCsvUsesConfiguredColumnLabelsAndAssociativeRows(): void {
		$exporter = new CsvReportExporter();
		$exporter->setResult($this->makeResult());

		$csv = $exporter->toString();

		$this->assertStringContainsString('Name,Count', $csv);
		$this->assertStringContainsString('Alice,2', $csv);
		$this->assertSame('text/csv; charset=utf-8', $exporter->getMimeType());
		$this->assertSame('csv', $exporter->getFileExtension());
	}

	public function testExcelExporterCreatesNativeXlsxWorkbook(): void {
		$exporter = new ExcelHtmlReportExporter();
		$exporter->setResult($this->makeResult());

		$content = $exporter->toString();
		$file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vizion_export_' . bin2hex(random_bytes(5)) . '.xlsx';

		try {
			file_put_contents($file, $content);

			$this->assertStringStartsWith('PK', $content);
			$this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $exporter->getMimeType());
			$this->assertSame('xlsx', $exporter->getFileExtension());

			$archive = new \PharData($file);
			$this->assertTrue(isset($archive['[Content_Types].xml']));
			$this->assertTrue(isset($archive['xl/workbook.xml']));
			$this->assertTrue(isset($archive['xl/worksheets/sheet1.xml']));
		}
		finally {
			@unlink($file);
		}
	}

	public function testExcelHtmlExporterKeepsLegacyHtmlWorkbookFormat(): void {
		$exporter = new ExcelHtmlReportExporter();
		$exporter->setResult($this->makeResult());

		$content = $exporter->toString();

		$this->assertStringContainsString('<html xmlns:o="urn:schemas-microsoft-com:office:office"', $content);
		$this->assertSame('application/vnd.ms-excel; charset=utf-8', $exporter->getMimeType());
		$this->assertSame('xls', $exporter->getFileExtension());
	}

	public function testXlsxExporterUsesNativeOfficeOpenXmlFormat(): void {
		$exporter = new XlsxReportExporter();
		$exporter->setResult($this->makeResult());

		$content = $exporter->toString();

		$this->assertStringStartsWith("PK", $content);
		$this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $exporter->getMimeType());
		$this->assertSame('xlsx', $exporter->getFileExtension());
	}

	public function testJsonContainsResultMetadata(): void {
		$exporter = new JsonReportExporter();
		$exporter->setResult($this->makeResult());

		$data = json_decode($exporter->toString(), true, 512, JSON_THROW_ON_ERROR);

		$this->assertSame('SELECT example', $data['debugSql'] ?? null);
		$this->assertSame('Alice', $data['rows'][0]['name'] ?? null);
		$this->assertSame('application/json; charset=utf-8', $exporter->getMimeType());
		$this->assertSame('json', $exporter->getFileExtension());
	}

	public function testHtmlExportersEscapeValuesAndRemainRenderable(): void {
		$result = new QueryResult(
			columns: [['name' => 'name', 'label' => 'Name']],
			rows: [['name' => '<b>Alice</b>']]
		);

		$table = new HtmlTableReportExporter();
		$table->setResult($result);
		$this->assertStringContainsString('&lt;b&gt;Alice&lt;/b&gt;', $table->toString());

		$page = new HtmlPageReportExporter();
		$page->setResult($result);
		$this->assertStringContainsString('<!DOCTYPE html>', $page->toString());
	}

	public function testUiExportersUseClientStackAssets(): void {
		$assets = $this->createStub(IAssetResolver::class);
		$assets->method('resolve')->willReturnCallback(
			static fn(string $path): string => '/resolved/' . basename($path)
		);
		$result = $this->makeResult();

		$dataTable = new DataTableReportExporter($assets);
		$dataTable->setResult($result);
		$dataTableHtml = $dataTable->toString();
		$this->assertStringContainsString('/resolved/jquery.datatable.min.js', $dataTableHtml);
		$this->assertStringContainsString('jqueryDataTable', $dataTableHtml);

		$bar = new BarChartReportExporter($assets);
		$bar->setResult($result);
		$barHtml = $bar->toString();
		$this->assertStringContainsString('/resolved/chart.js', $barHtml);
		$this->assertStringContainsString('type:"bar"', $barHtml);

		$pie = new PieChartReportExporter($assets);
		$pie->setResult($result);
		$pieHtml = $pie->toString();
		$this->assertStringContainsString('/resolved/chart.js', $pieHtml);
		$this->assertStringContainsString('type:"doughnut"', $pieHtml);
	}

	public function testToFileWritesSerializedContent(): void {
		$exporter = new CsvReportExporter();
		$exporter->setResult($this->makeResult());
		$file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vizion_export_' . bin2hex(random_bytes(5)) . '.csv';

		try {
			$this->assertSame($exporter, $exporter->toFile($file));
			$this->assertFileExists($file);
			$this->assertStringContainsString('Alice,2', (string)file_get_contents($file));
		}
		finally {
			@unlink($file);
		}
	}

	private function makeResult(): QueryResult {
		return new QueryResult(
			columns: [
				['name' => 'name', 'label' => 'Name'],
				['name' => 'count', 'label' => 'Count'],
			],
			rows: [
				['name' => 'Alice', 'count' => 2],
				['name' => 'Bob', 'count' => 5],
			],
			debugSql: 'SELECT example'
		);
	}
}
