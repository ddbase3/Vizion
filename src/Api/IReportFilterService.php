<?php declare(strict_types=1);

namespace Vizion\Api;

/**
 * Orchestrates report filters for Vizion displays and data endpoints.
 *
 * This service is the stable facade used by displays. It reads vdef field
 * filter definitions, delegates type-specific behavior to IReportFilterType
 * implementations, delegates client-control metadata to IReportFilterControl
 * implementations, and converts browser payloads into ResourceFoundation query
 * conditions.
 *
 * The service must not contain project-specific filter behavior. New generic or
 * project-specific filter types should be added as discoverable
 * IReportFilterType/IReportFilterControl implementations and selected by their
 * semantic type/control names in the vdef. Their IBase::getName() values remain technical class map names and must not be
 * overloaded with short vdef names.
 */
interface IReportFilterService {

	/**
	 * Builds ModularGrid-compatible filter field definitions from vdef fields.
	 *
	 * The result is JSON-serializable. It may include renderControlKey values that
	 * are resolved by Vizion's browser-side filter-control registry.
	 *
	 * @param array<int,array<string,mixed>> $fields vdef field definitions
	 * @return array<int,array<string,mixed>> ModularGrid filter fields
	 */
	public function buildGridFilterFields(array $fields): array;

	/**
	 * Builds the initial filter state from vdef filter definitions.
	 *
	 * Initial values are active startup values. They are distinct from defaultValue,
	 * which describes Clear/Reset behavior, and emptyValue, which describes the
	 * inactive/no-filter state.
	 *
	 * @param array<int,array<string,mixed>> $fields vdef field definitions
	 * @return array<string,mixed> Filter state keyed by field alias
	 */
	public function buildInitialFilterValues(array $fields): array;

	/**
	 * Normalizes and validates incoming browser filter payloads.
	 *
	 * Unknown fields and inactive values are removed. Returned values are suitable
	 * for buildFilterWhere() and for exposing as applied filters.
	 *
	 * @param mixed $filtersPayload Raw request payload, usually an associative array
	 * @param array<int,array<string,mixed>> $fields vdef field definitions
	 * @return array<string,mixed> Normalized active filters keyed by field alias
	 */
	public function normalizeFilters(mixed $filtersPayload, array $fields): array;

	/**
	 * Builds a ResourceFoundation where condition from normalized active filters.
	 *
	 * The generated condition uses ResourceFoundation query-array structures only.
	 * It must not contain SQL fragments from JSON definitions.
	 *
	 * @param array<string,mixed> $filters Normalized filters from normalizeFilters()
	 * @param array<int,array<string,mixed>> $fields vdef field definitions
	 * @param array<string,mixed> $fieldDefs map of alias to ResourceFoundation element
	 * @return array<string,mixed>|null ResourceFoundation where condition or null
	 */
	public function buildFilterWhere(array $filters, array $fields, array $fieldDefs): ?array;
}
