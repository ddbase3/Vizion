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

namespace Base3\Vizion\Test\ReportDisplay;

use PHPUnit\Framework\TestCase;
use Base3\Vizion\ReportDisplay\GeneralReportDisplay;
use Base3\CoreFoundation\Api\IRequest;
use Base3\CoreFoundation\Api\IDisplay;
use Base3\CoreFoundation\Translation\Api\ITranslation;
use Base3\Test\Core\ClassMapStub;
use Base3\Vizion\Api\IReportConfigProvider;

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
