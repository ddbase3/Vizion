<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of Vizion for BASE3 Framework.
 *
 * Vizion extends the BASE3 framework with modular, visual display
 * components for reports and structured data. It provides flexible
 * renderers such as interactive tables and charts, driven by
 * declarative configuration and seamlessly integrated into BASE3 pages.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/vizion
 * https://github.com/ddbase3/Vizion
 **********************************************************************/

namespace Vizion\ReportDisplay;

use Base3\Api\IDisplay;
use Base3\Api\IRequest;
use Base3\Api\IClassMap;
use Throwable;
use Vizion\Api\IReportConfigProvider;
use Vizion\Api\IReportDisplay;

class GeneralReportDisplay implements IReportDisplay {

	protected ?string $report = null;
	protected ?array $config = null;

	public function __construct(
		protected readonly IRequest $request,
		protected readonly IClassMap $classmap,
		protected readonly IReportConfigProvider $configProvider
	) {}

	public static function getName(): string {
		return "generalreportdisplay";
	}

	public function setData($data): void {
		$this->report = is_string($data) ? $data : null;
	}

	public function getOutput(string $out = 'html', bool $final = false): string {
		try {
			return $this->renderReport($out);
		}
		catch(Throwable $exception) {
			return strtolower($out) === 'json'
				? $this->renderJsonError($exception, $final)
				: $this->renderHtmlError($exception);
		}
	}

	private function renderReport(string $out): string {
		if (!$this->report) {
			$this->report = $this->request->get("report");
			if (!$this->report) {
				throw new \Exception("Missing report identifier");
			}
		}

		$this->config = $this->configProvider->getConfig($this->report);
		$this->config['report'] = $this->report;
		$displayName = $this->config['display'] ?? '';

		/** @var IDisplay $display */
		$display = $this->classmap->getInstanceByInterfaceName(IDisplay::class, $displayName);
		if (!$display) throw new \Exception("Invalid display: $displayName");

		$display->setData($this->config);
		return $display->getOutput($out);
	}

	private function renderJsonError(Throwable $exception, bool $final): string {
		if($final && !headers_sent()) {
			header('Content-Type: application/json; charset=utf-8');
		}

		return (string) json_encode([
			'ok' => false,
			'error' => $exception->getMessage(),
			'data' => [],
			'rows' => [],
			'labels' => [],
			'datasets' => [],
			'total' => 0
		], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}

	private function renderHtmlError(Throwable $exception): string {
		$message = htmlspecialchars($exception->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

		return '<div class="vizion-report-error" style="border:1px solid #d8a0a0;border-radius:6px;background:#fff5f5;color:#7f1d1d;padding:10px 12px;font-size:13px;">'
			. '<strong>Report konnte nicht geladen werden.</strong><br>'
			. $message
			. '</div>';
	}

	public function getHelp(): string {
		return "Displays a report based on the configured display type and configuration provider.";
	}
}
