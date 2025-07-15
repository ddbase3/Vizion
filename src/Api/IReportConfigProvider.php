<?php declare(strict_types=1);

namespace Vizion\Api;

interface IReportConfigProvider {

	/**
	 * Get the configuration for a particular report name.
	 *
	 * @param string $report The logical name of the reports (i.e. "example", "userreport", ...)
	 * @return array associative report configuration
	 * @throws \Exception if report is not available
	 */
	public function getConfig(string $report): array;
}

