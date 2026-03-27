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

	// Implementation of IReportConfigProvider

	public function getConfig(string $report): array {

		$plugins = $this->classmap->getPlugins();
		foreach ($plugins as $plugin) {
			$file = DIR_PLUGIN . $plugin . '/local/Report/' . $report . '.json';
			if (!file_exists($file)) continue;
			$json = file_get_contents($file);
			return json_decode($json, true);
		}

		throw new \Exception("Report not found: $report");
	}
}

