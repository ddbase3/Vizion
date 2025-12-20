<?php declare(strict_types=1);

namespace Vizion\Test\ReportDisplay;

require_once __DIR__ . '/TestClassMap.php';

use PHPUnit\Framework\TestCase;
use Vizion\ReportDisplay\GeneralReportDisplay;
use Base3\Api\IRequest;
use Base3\Api\IDisplay;
use Vizion\Api\IReportConfigProvider;

final class GeneralReportDisplayTest extends TestCase {

	public function testGetNameReturnsExpectedValue(): void {
		$this->assertSame('generalreportdisplay', GeneralReportDisplay::getName());
	}

	public function testGetHelpReturnsExpectedValue(): void {
		$req = $this->createStub(IRequest::class);
		$classmap = new TestClassMap();
		$configProvider = $this->createStub(IReportConfigProvider::class);

		$display = new GeneralReportDisplay($req, $classmap, $configProvider);

		$this->assertSame(
			'Displays a report based on the configured display type and configuration provider.',
			$display->getHelp()
		);
	}

	public function testGetOutputUsesSetDataReportAndDelegatesToResolvedDisplay(): void {
		$req = $this->createStub(IRequest::class);

		$configProvider = $this->createStub(IReportConfigProvider::class);
		$configProvider->method('getConfig')->with('r1')->willReturn([
			'display' => 'datatablereportdisplay',
			'fields' => [],
		]);

		$resolvedConfig = null;
		$resolvedOutArg = null;

		$inner = $this->createStub(IDisplay::class);
		$inner->method('setData')->willReturnCallback(function($data) use (&$resolvedConfig): void {
			$resolvedConfig = $data;
		});
		$inner->method('getOutput')->willReturnCallback(function($out = 'html') use (&$resolvedOutArg): string {
			$resolvedOutArg = $out;
			return 'INNER_OUTPUT';
		});

		$classmap = new TestClassMap();
		$classmap->returnValue = $inner;

		$display = new GeneralReportDisplay($req, $classmap, $configProvider);
		$display->setData('r1');

		$out = $display->getOutput('json');

		$this->assertSame('INNER_OUTPUT', $out);

		$this->assertNotNull($resolvedConfig);
		$this->assertSame('r1', $resolvedConfig['report'] ?? null);
		$this->assertSame('datatablereportdisplay', $resolvedConfig['display'] ?? null);

		$this->assertSame('json', $resolvedOutArg);

		$this->assertCount(1, $classmap->calls);
		$this->assertSame('getInstanceByInterfaceName', $classmap->calls[0]['method']);
		$this->assertSame(IDisplay::class, $classmap->calls[0]['interface']);
		$this->assertSame('datatablereportdisplay', $classmap->calls[0]['name']);
	}

	public function testGetOutputReadsReportFromRequestWhenNotSetBySetData(): void {
		$req = $this->createStub(IRequest::class);
		$req->method('get')->with('report')->willReturn('r2');

		$configProvider = $this->createStub(IReportConfigProvider::class);
		$configProvider->method('getConfig')->with('r2')->willReturn([
			'display' => 'datatablereportdisplay',
			'fields' => [],
		]);

		$inner = $this->createStub(IDisplay::class);
		$inner->method('getOutput')->willReturn('OK');

		$classmap = new TestClassMap();
		$classmap->returnValue = $inner;

		$display = new GeneralReportDisplay($req, $classmap, $configProvider);

		$this->assertSame('OK', $display->getOutput('html'));
	}

	public function testGetOutputThrowsWhenReportMissing(): void {
		$req = $this->createStub(IRequest::class);
		$req->method('get')->with('report')->willReturn(null);

		$classmap = new TestClassMap();
		$configProvider = $this->createStub(IReportConfigProvider::class);

		$display = new GeneralReportDisplay($req, $classmap, $configProvider);

		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Missing report identifier');

		$display->getOutput('html');
	}

	public function testGetOutputThrowsWhenDisplayIsInvalid(): void {
		$req = $this->createStub(IRequest::class);
		$req->method('get')->with('report')->willReturn('r3');

		$configProvider = $this->createStub(IReportConfigProvider::class);
		$configProvider->method('getConfig')->with('r3')->willReturn([
			'display' => 'nope',
		]);

		$classmap = new TestClassMap();
		$classmap->returnValue = null;

		$display = new GeneralReportDisplay($req, $classmap, $configProvider);

		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Invalid display: nope');

		$display->getOutput('html');
	}
}
