<?php declare(strict_types=1);

namespace Vizion\Api;

use ResourceFoundation\Dto\QueryResult;

/**
 * Builds Vizion chart report client config, ResourceFoundation queries, and
 * JSON payloads.
 *
 * This service is the chart counterpart to the grid report orchestration. It
 * keeps chart reports independent from ModularGrid while reusing the same
 * filter definitions, filter normalization, and value formatter metadata. The
 * service works only with semantic vdef structures; it must not read raw SQL
 * fragments from JSON and must not contain project-specific rendering logic.
 */
interface IReportChartService {

	/**
	 * Builds the client-side chart definition used by the ChartReportDisplay
	 * template.
	 *
	 * The returned structure contains normalized dimension and measure metadata,
	 * formatter configs, and renderer configuration. It must be serializable to
	 * JSON and safe to embed into the rendered page.
	 *
	 * @param array<string,mixed> $config Full report vdef
	 * @param array<int,array<string,mixed>> $fields Report field definitions
	 * @return array<string,mixed> Client chart config
	 */
	public function buildClientConfig(array $config, array $fields): array;

	/**
	 * Builds the ResourceFoundation query used to load chart data.
	 *
	 * Filters are already normalized before they are passed in. The resulting
	 * query uses ResourceFoundation field/function/group structures, not
	 * SQL fragments.
	 *
	 * @param array<string,mixed> $config Full report vdef
	 * @param array<int,array<string,mixed>> $fields Report field definitions
	 * @param array<string,mixed> $filters Normalized active filters
	 * @return array<string,mixed> ResourceFoundation query JSON
	 */
	public function buildQuery(array $config, array $fields, array $filters): array;

	/**
	 * Converts a query result into a chart JSON payload.
	 *
	 * The payload contains raw labels and numeric datasets. Browser-side code then
	 * applies Chart.js rendering and value formatting.
	 *
	 * @param array<string,mixed> $config Full report vdef
	 * @param QueryResult $result Query result returned by IQueryService
	 * @return array<string,mixed> JSON response payload
	 */
	public function buildPayload(array $config, QueryResult $result): array;
}
