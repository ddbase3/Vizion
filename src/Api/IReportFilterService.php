<?php declare(strict_types=1);

namespace Vizion\Api;

interface IReportFilterService {

	/**
	 * @param array<int,array<string,mixed>> $fields
	 * @return array<int,array<string,mixed>>
	 */
	public function buildGridFilterFields(array $fields): array;

	/**
	 * @param array<int,array<string,mixed>> $fields
	 * @return array<string,mixed>
	 */
	public function buildInitialFilterValues(array $fields): array;

	/**
	 * @param mixed $filtersPayload
	 * @param array<int,array<string,mixed>> $fields
	 * @return array<string,mixed>
	 */
	public function normalizeFilters(mixed $filtersPayload, array $fields): array;

	/**
	 * @param array<string,mixed> $filters
	 * @param array<int,array<string,mixed>> $fields
	 * @param array<string,mixed> $fieldDefs
	 * @return array<string,mixed>|null
	 */
	public function buildFilterWhere(array $filters, array $fields, array $fieldDefs): ?array;
}
