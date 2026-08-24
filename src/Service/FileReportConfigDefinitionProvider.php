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

use ResourceFoundation\Api\IReportConfigDefinitionProvider;

final class FileReportConfigDefinitionProvider implements IReportConfigDefinitionProvider {

	public static function getName(): string {
		return 'vizionfilereportconfigdefinitionprovider';
	}

	public function getScope(): string {
		return 'vizion';
	}

	public function getDefinitions(): array {
		$directory = dirname(__DIR__, 2) . '/local/Vizion';
		$files = glob(rtrim($directory, DIRECTORY_SEPARATOR . '/\\') . DIRECTORY_SEPARATOR . '*.json') ?: [];
		sort($files);

		$definitions = [];
		foreach($files as $file) {
			$name = pathinfo($file, PATHINFO_FILENAME);
			if($name === '') {
				continue;
			}

			$definitions[$name] = [
				'enabled' => true,
				'definition' => $this->loadDefinition($file)
			];
		}

		return $definitions;
	}

	private function loadDefinition(string $file): array {
		$json = file_get_contents($file);
		if($json === false) {
			throw new \RuntimeException('Unable to read Vizion report definition: ' . $file);
		}

		$definition = json_decode($json, true);
		if(!is_array($definition) || json_last_error() !== JSON_ERROR_NONE) {
			throw new \RuntimeException('Invalid Vizion report JSON in ' . $file . ': ' . json_last_error_msg());
		}

		return $definition;
	}
}
