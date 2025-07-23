<?php declare(strict_types=1);

namespace Vizion\Service;

use Base3\Api\IClassMap;
use Vizion\Api\IReportConfigProvider;

class FileReportConfigProvider implements IReportConfigProvider {

	public function __construct(private readonly IClassMap $classmap) {}

	// Implementation of IReportConfigProvider

	public function getConfig(string $report): array {

		$plugins = $this->classmap->getPlugins();
		foreach ($plugins as $plugin) {
			$file = DIR_PLUGIN . $plugin . '/local/Report/' . $report . '.json';
			if (!file_exists($file)) continue;
			$json = file_get_contents($file);
			return json_decode($json, true);
		}

		if ($report !== "example") {
			throw new \Exception("Report not found: $report");
		}
	}
}

