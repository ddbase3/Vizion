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

namespace Vizion\Service;

use Base3\Api\IClassMap;
use Vizion\Api\IReportConfigProvider;

class FileReportConfigProvider implements IReportConfigProvider {

	public function __construct(private readonly IClassMap $classmap) {}

	public function getConfig(string $report): array {
		$report = trim($report);
		if($report === '') {
			throw new \InvalidArgumentException('Missing report identifier');
		}

		if(preg_match('/^[a-zA-Z0-9_-]+$/', $report) !== 1) {
			throw new \InvalidArgumentException('Invalid report identifier: ' . $report);
		}

		$files = [];

		foreach($this->classmap->getPlugins() as $plugin) {
			if(!is_scalar($plugin)) {
				continue;
			}

			$pluginName = trim((string) $plugin);
			if($pluginName === '') {
				continue;
			}

			$file = DIR_PLUGIN . $pluginName . '/local/Vizion/' . $report . '.json';
			if(is_file($file)) {
				$files[] = $file;
			}
		}

		if(count($files) === 0) {
			throw new \RuntimeException('Report not found: ' . $report);
		}

		if(count($files) > 1) {
			throw new \RuntimeException('Report identifier is not unique: ' . $report);
		}

		$raw = file_get_contents($files[0]);
		if($raw === false) {
			throw new \RuntimeException('Failed to read report file: ' . $files[0]);
		}

		$config = json_decode($raw, true);
		if(!is_array($config) || json_last_error() !== JSON_ERROR_NONE) {
			throw new \RuntimeException('Invalid JSON in report file ' . $files[0] . ': ' . json_last_error_msg());
		}

		return $config;
	}
}
