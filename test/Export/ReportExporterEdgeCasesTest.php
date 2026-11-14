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

namespace Base3\Vizion\Test\Export;

use PHPUnit\Framework\TestCase;
use Base3\ResourceFoundation\Dto\QueryResult;
use RuntimeException;
use Base3\Vizion\Export\CsvReportExporter;
use Base3\Vizion\Export\ExcelHtmlReportExporter;
use Base3\Vizion\Export\HtmlTableReportExporter;
use Base3\Vizion\Export\JsonReportExporter;

final class ReportExporterEdgeCasesTest extends TestCase {

	public function testDownloadExportersRejectMissingResult(): void {
		foreach([new CsvReportExporter(), new JsonReportExporter(), new ExcelHtmlReportExporter(), new HtmlTableReportExporter()] as $exporter) {
			try {
				$exporter->toString();
				$this->fail('Exporter without query result must fail.');
			} catch(RuntimeException $exception) {
				$this->assertSame('No query result set for export.', $exception->getMessage());
			}
		}
	}

	public function testCsvEscapesSeparatorsQuotesAndNewlines(): void {
		$exporter = new CsvReportExporter();
		$exporter->setResult(new QueryResult(
			columns: [['name' => 'name', 'label' => 'Person'], ['name' => 'note', 'label' => 'Description']],
			rows: [['name' => 'Alice, Bob', 'note' => "A \"quote\"\nand newline"]]
		));
		$csv = $exporter->toString();
		$this->assertStringContainsString('Person,Description', $csv);
		$this->assertStringContainsString('"Alice, Bob"', $csv);
		$this->assertStringContainsString('"A ""quote""', $csv);
		$this->assertStringContainsString('and newline"', $csv);
	}

	public function testCsvUsesPositionalFallbackForNumericRows(): void {
		$exporter = new CsvReportExporter();
		$exporter->setResult(new QueryResult([
			['name' => 'a', 'label' => ' '], ['name' => 'b', 'label' => 'B']
		], [['first', 3], ['a' => 'explicit', 'second']]));
		$csv = $exporter->toString();
		$this->assertStringContainsString("a,B\n", $csv);
		$this->assertStringContainsString('first,3', $csv);
		$this->assertStringContainsString('explicit,second', $csv);
	}

	public function testHtmlExportersEscapeColumnNamesAndTextContent(): void {
		$result = new QueryResult([
			['name' => 'content', 'label' => '<Column>']
		], [
			['content' => '<script>alert("x")</script>']
		]);
		foreach([new ExcelHtmlReportExporter(), new HtmlTableReportExporter()] as $exporter) {
			$exporter->setResult($result);
			$html = $exporter->toString();
			$this->assertStringContainsString('&lt;Column&gt;', $html);
			$this->assertStringContainsString('&lt;script&gt;', $html);
			$this->assertStringNotContainsString('<script>', $html);
		}
	}

	public function testJsonPreservesUnicodeAndOmitsAbsentDebugSql(): void {
		$exporter = new JsonReportExporter();
		$exporter->setResult(new QueryResult([['name' => 'title']], [['title' => 'Überblick']]));
		$content = $exporter->toString();
		$this->assertStringContainsString('Überblick', $content);
		$this->assertArrayNotHasKey('debugSql', json_decode($content, true, 512, JSON_THROW_ON_ERROR));
	}

	public function testJsonExporterRejectsInvalidUtf8(): void {
		$exporter = new JsonReportExporter();
		$exporter->setResult(new QueryResult([], [['value' => "\xB1\x31"]]));
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Failed to encode JSON report export.');
		$exporter->toString();
	}
}
