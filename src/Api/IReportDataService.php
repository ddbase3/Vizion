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

namespace Vizion\Api;

/**
 * Headless access to executable Vizion report definitions.
 *
 * The service exposes the same semantic report configuration used by Vizion
 * displays without coupling non-UI consumers to a display implementation.
 * Query execution stays inside Vizion so filters, tree filters, sorting,
 * paging and ResourceFoundation security constraints remain identical for all
 * consumers.
 */
interface IReportDataService {

	/**
	 * Lists executable reports inside one user-facing reporting scope.
	 *
	 * @return array<string,mixed>
	 */
	public function listReports(string $reportingScope, string $search = '', int $limit = 20): array;

	/**
	 * Describes one executable report and its public field/filter contract.
	 *
	 * @return array<string,mixed>
	 */
	public function describeReport(string $report): array;

	/**
	 * Executes one report using the same semantic request model as ModularGrid.
	 *
	 * Optional payload keys are search, filters, treeFilters, sort, page,
	 * pageSize and fields. fields is an optional list of configured aliases to
	 * project from the report result.
	 *
	 * @param array<string,mixed> $payload
	 * @return array<string,mixed>
	 */
	public function executeReport(string $report, array $payload = []): array;

	/**
	 * Executes an already loaded report definition. This is used by Vizion
	 * displays so the configuration provider is not queried twice in one request.
	 *
	 * @param array<string,mixed> $config
	 * @param array<string,mixed> $payload
	 * @return array<string,mixed>
	 */
	public function executeConfig(array $config, array $payload = []): array;

	/**
	 * Loads the visible nodes for one configured report tree filter.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function loadTree(string $report, string $key): array;

	/**
	 * Loads tree nodes from an already loaded report definition.
	 *
	 * @param array<string,mixed> $config
	 * @return array<int,array<string,mixed>>
	 */
	public function loadTreeConfig(array $config, string $key): array;

	/**
	 * Searches one configured report tree filter and returns matching nodes with
	 * their breadcrumb path.
	 *
	 * @return array<string,mixed>
	 */
	public function searchTree(string $report, string $key, string $search = '', int $limit = 20): array;
}
