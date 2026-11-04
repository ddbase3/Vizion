<?php declare(strict_types=1);

namespace Vizion\Test\Export;

use PHPUnit\Framework\TestCase;
use ResourceFoundation\Dto\QueryResult;
use RuntimeException;
use Vizion\Export\CsvReportExporter;
use Vizion\Export\ExcelHtmlReportExporter;
use Vizion\Export\HtmlTableReportExporter;
use Vizion\Export\JsonReportExporter;

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
