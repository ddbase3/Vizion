<?php declare(strict_types=1);

namespace Vizion\Test\ReportDisplay;

use PHPUnit\Framework\TestCase;
use Vizion\ReportDisplay\GeneralReportDisplay;
use Base3\Api\IRequest;
use Base3\Api\IDisplay;
use Base3\Test\Core\ClassMapStub;
use Vizion\Api\IReportConfigProvider;

final class GeneralReportDisplayTest extends TestCase {

	public function testGetNameReturnsExpectedValue(): void {
		$this->assertSame('generalreportdisplay', GeneralReportDisplay::getName());
	}

	public function testGetHelpReturnsExpectedValue(): void {
		$req = $this->createStub(IRequest::class);
		$classmap = new ClassMapStub();
		$configProvider = $this->createStub(IReportConfigProvider::class);

		$display = new GeneralReportDisplay($req, $classmap, $configProvider);

		$this->assertSame(
			'Displays a report based on the configured display type and configuration provider.',
			$display->getHelp()
		);
	}

	public function testGetOutputUsesSetDataReportAndDelegatesToResolvedDisplay(): void {
		DataTableReportDisplayStub::reset();

		$req = $this->createStub(IRequest::class);

		$configProvider = $this->createStub(IReportConfigProvider::class);
		$configProvider->method('getConfig')->with('r1')->willReturn([
			'display' => 'datatablereportdisplay',
			'fields' => [],
		]);

		$classmap = new ClassMapStub();
		$classmap->registerInterface(IDisplay::class, DataTableReportDisplayStub::class);
		$classmap->registerName('datatablereportdisplay', DataTableReportDisplayStub::class);

		$display = new GeneralReportDisplay($req, $classmap, $configProvider);
		$display->setData('r1');

		$out = $display->getOutput('json');

		$this->assertSame('INNER_OUTPUT', $out);

		$this->assertNotNull(DataTableReportDisplayStub::$lastInstance);
		$this->assertIsArray(DataTableReportDisplayStub::$lastInstance->receivedData);

		$resolvedConfig = DataTableReportDisplayStub::$lastInstance->receivedData;
		$this->assertSame('r1', $resolvedConfig['report'] ?? null);
		$this->assertSame('datatablereportdisplay', $resolvedConfig['display'] ?? null);

		$this->assertSame('json', DataTableReportDisplayStub::$lastInstance->receivedOut);

		// ClassMapStub logs both: getInstanceByInterfaceName + instantiate
		$methods = array_values(array_map(fn($c) => $c['method'], $classmap->calls));

		$this->assertSame(['getInstanceByInterfaceName', 'instantiate'], array_slice($methods, 0, 2));

		$this->assertSame(IDisplay::class, $classmap->calls[0]['interface'] ?? null);
		$this->assertSame('datatablereportdisplay', $classmap->calls[0]['name'] ?? null);
	}

	public function testGetOutputReadsReportFromRequestWhenNotSetBySetData(): void {
		DataTableReportDisplayStub::reset();
		DataTableReportDisplayStub::$nextOutput = 'OK';

		$req = $this->createStub(IRequest::class);
		$req->method('get')->with('report')->willReturn('r2');

		$configProvider = $this->createStub(IReportConfigProvider::class);
		$configProvider->method('getConfig')->with('r2')->willReturn([
			'display' => 'datatablereportdisplay',
			'fields' => [],
		]);

		$classmap = new ClassMapStub();
		$classmap->registerInterface(IDisplay::class, DataTableReportDisplayStub::class);
		$classmap->registerName('datatablereportdisplay', DataTableReportDisplayStub::class);

		$display = new GeneralReportDisplay($req, $classmap, $configProvider);

		$this->assertSame('OK', $display->getOutput('html'));
	}

	public function testGetOutputThrowsWhenReportMissing(): void {
		$req = $this->createStub(IRequest::class);
		$req->method('get')->with('report')->willReturn(null);

		$classmap = new ClassMapStub();
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

		$classmap = new ClassMapStub(); // no registrations => invalid display

		$display = new GeneralReportDisplay($req, $classmap, $configProvider);

		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Invalid display: nope');

		$display->getOutput('html');
	}
}

final class DataTableReportDisplayStub implements IDisplay {

	public static ?self $lastInstance = null;
	public static string $nextOutput = 'INNER_OUTPUT';

	public ?array $receivedData = null;
	public ?string $receivedOut = null;

	public function __construct() {
		self::$lastInstance = $this;
	}

	public static function reset(): void {
		self::$lastInstance = null;
		self::$nextOutput = 'INNER_OUTPUT';
	}

	public static function getName(): string {
		return 'datatablereportdisplay';
	}

	public function setData($data): void {
		$this->receivedData = is_array($data) ? $data : null;
	}

	public function getOutput($out = "html") {
		$this->receivedOut = (string)$out;
		return self::$nextOutput;
	}

	public function getHelp() {
		return 'HELP';
	}
}
