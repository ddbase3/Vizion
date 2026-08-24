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
use ResourceFoundation\Api\IReportConfigDefinitionProvider;
use Vizion\Api\IReportConfigProvider;

final class CompositeReportConfigProvider implements IReportConfigProvider {

	public function __construct(
		private readonly IClassMap $classMap
	) {}

	public function getConfig(string $report): array {
		$report = trim($report);
		if($report === '') {
			throw new \InvalidArgumentException('Missing report identifier');
		}

		[$scope, $localReport] = $this->splitReportIdentifier($report);
		$this->assertReportIdentifier($localReport);

		$providers = $this->getProviders();
		if($scope !== null) {
			if(!isset($providers[$scope])) {
				throw new \RuntimeException('Report scope not found: ' . $scope);
			}

			$definition = $this->getDefinition($providers[$scope], $scope, $localReport);
			if($definition === null) {
				throw new \RuntimeException('Report not found: ' . $scope . ':' . $localReport);
			}

			return $definition;
		}

		$matches = [];
		foreach($providers as $providerScope => $provider) {
			$definition = $this->getDefinition($provider, $providerScope, $localReport);
			if($definition !== null) {
				$matches[$providerScope] = $definition;
			}
		}

		if($matches === []) {
			throw new \RuntimeException('Report not found: ' . $localReport);
		}

		if(count($matches) > 1) {
			throw new \RuntimeException(
				'Report identifier is not unique: ' . $localReport . ' (' . implode(', ', array_keys($matches)) . '). Use scope:report.'
			);
		}

		return reset($matches);
	}

	private function getDefinition(IReportConfigDefinitionProvider $provider, string $scope, string $report): ?array {
		$datasets = $provider->getDefinitions();
		$dataset = $datasets[$report] ?? null;
		if(!is_array($dataset) || !$this->isEnabled($dataset)) {
			return null;
		}

		$definition = $dataset['definition'] ?? null;
		if(!is_array($definition)) {
			throw new \RuntimeException('Report definition dataset must contain a definition array: ' . $scope . '/' . $report);
		}

		return $definition;
	}

	private function assertReportIdentifier(string $report): void {
		if(preg_match('/^[a-zA-Z0-9_-]+$/', $report) !== 1) {
			throw new \InvalidArgumentException('Invalid report identifier: ' . $report);
		}
	}

	/**
	 * @return array{0:?string,1:string}
	 */
	private function splitReportIdentifier(string $report): array {
		if(!str_contains($report, ':')) {
			return [null, $report];
		}

		[$scope, $localReport] = explode(':', $report, 2);
		$scope = trim($scope);
		$localReport = trim($localReport);
		if($scope === '' || $localReport === '') {
			throw new \InvalidArgumentException('Invalid qualified report identifier: ' . $report);
		}

		if(preg_match('/^[a-z0-9_-]+$/', $scope) !== 1) {
			throw new \InvalidArgumentException('Invalid report scope: ' . $scope);
		}

		return [$scope, $localReport];
	}

	/**
	 * @return array<string,IReportConfigDefinitionProvider>
	 */
	private function getProviders(): array {
		$providers = [];
		foreach($this->classMap->getInstancesByInterface(IReportConfigDefinitionProvider::class) as $provider) {
			if(!$provider instanceof IReportConfigDefinitionProvider) {
				continue;
			}

			$scope = trim($provider->getScope());
			if($scope === '') {
				throw new \RuntimeException('Report definition provider has an empty scope: ' . $provider::getName());
			}

			if(preg_match('/^[a-z0-9_-]+$/', $scope) !== 1) {
				throw new \RuntimeException('Invalid report definition scope "' . $scope . '" from ' . $provider::getName());
			}

			if(isset($providers[$scope])) {
				throw new \RuntimeException('Report definition scope is not unique: ' . $scope);
			}

			$providers[$scope] = $provider;
		}

		return $providers;
	}

	private function isEnabled(array $dataset): bool {
		if(!array_key_exists('enabled', $dataset)) {
			return true;
		}

		$value = $dataset['enabled'];
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

		return true;
	}
}
