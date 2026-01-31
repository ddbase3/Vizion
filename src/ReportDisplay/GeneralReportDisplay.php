<?php declare(strict_types=1);

namespace Vizion\ReportDisplay;

use Base3\Api\IDisplay;
use Base3\Api\IOutput;
use Base3\Api\IRequest;
use Base3\Api\IClassMap;
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

	public function getHelp(): string {
		return "Displays a report based on the configured display type and configuration provider.";
	}
}
