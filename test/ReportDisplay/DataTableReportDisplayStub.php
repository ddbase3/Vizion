<?php declare(strict_types=1);

namespace Vizion\Test\ReportDisplay;

use PHPUnit\Framework\TestCase;
use Vizion\ReportDisplay\GeneralReportDisplay;
use Base3\Api\IRequest;
use Base3\Api\IDisplay;
use Base3\Translation\Api\ITranslation;
use Base3\Test\Core\ClassMapStub;
use Vizion\Api\IReportConfigProvider;

final class DataTableReportDisplayStub implements IDisplay {

	public static ?self $lastInstance = null;
	public static string $nextOutput = 'INNER_OUTPUT';

	public ?array $receivedData = null;
	public ?string $receivedOut = null;
	public bool $receivedFinal = false;

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

	public function getOutput(string $out = 'html', bool $final = false): string {
		$this->receivedOut = (string)$out;
		$this->receivedFinal = $final;
		return self::$nextOutput;
	}

	public function getHelp(): string {
		return 'HELP';
	}
}
