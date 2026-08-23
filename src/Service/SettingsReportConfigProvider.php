<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of Vizion for BASE3 Framework.
 *
 * Vizion extends the BASE3 framework with modular, visual display
 * components for reports and structured data.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/vizion
 * https://github.com/ddbase3/Vizion
 **********************************************************************/

namespace Vizion\Service;

use Base3\Settings\Api\ISettingsStore;
use Vizion\Api\IReportConfigProvider;

final class SettingsReportConfigProvider implements IReportConfigProvider {

	public function __construct(
		private readonly ISettingsStore $settingsStore,
		private readonly string $group
	) {}

	public function getConfig(string $report): array {
		$report = trim($report);
		if($report === '') {
			throw new \InvalidArgumentException('Missing report identifier');
		}

		if(preg_match('/^[a-zA-Z0-9_-]+$/', $report) !== 1) {
			throw new \InvalidArgumentException('Invalid report identifier: ' . $report);
		}

		$dataset = $this->settingsStore->get($this->group, $report, []);
		if($dataset === [] || !$this->isEnabled($dataset)) {
			throw new \RuntimeException('Report not found: ' . $report);
		}

		$definition = $dataset['definition'] ?? null;
		if(!is_array($definition)) {
			throw new \RuntimeException('Report setting must contain a definition array: ' . $report);
		}

		return $definition;
	}

	private function isEnabled(array $dataset): bool {
		if(!array_key_exists('enabled', $dataset)) {
			return true;
		}

		return $this->normalizeBoolean($dataset['enabled'], true);
	}

	private function normalizeBoolean(mixed $value, bool $default): bool {
		if(is_bool($value)) {
			return $value;
		}

		if(is_numeric($value)) {
			return (int)$value !== 0;
		}

		if(is_string($value)) {
			$value = strtolower(trim($value));
			if(in_array($value, ['1', 'true', 'yes', 'on', 'enabled'], true)) {
				return true;
			}

			if(in_array($value, ['0', 'false', 'no', 'off', 'disabled'], true)) {
				return false;
			}
		}

		return $default;
	}
}
