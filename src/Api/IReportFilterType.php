<?php declare(strict_types=1);

namespace Vizion\Api;

use Base3\Api\IBase;

/**
 * Defines the server-side semantics of one Vizion report filter type.
 *
 * A filter type is responsible for data semantics, not for the concrete UI widget.
 * Examples are text, number, multiselect, slider, daterange, and datetimerange.
 * Implementations are discovered through the BASE3 class map by this interface and
 * their IBase::getName() value.
 *
 * Important naming rule:
 * getName() is the globally unique technical BASE3 class name, usually the full
 * lowercase class name such as textfiltertype or daterangefiltertype. It is not
 * the short vdef filter type. The short vdef name is returned by getType(), and
 * additional accepted vdef names are returned by getAliases().
 *
 * The JSON config remains declarative: it describes the intended filter semantics
 * and must not contain SQL fragments. Implementations translate those semantics
 * into the ResourceFoundation query array structure used by Vizion.
 *
 * Implementations must be stateless or treat themselves as shared services. They
 * must not render HTML or JavaScript. Browser-side controls are handled by
 * IReportFilterControl and the Vizion filter-control JavaScript registry.
 */
interface IReportFilterType extends IBase {

	/**
	 * Returns the canonical short filter type used in vdefs.
	 *
	 * Examples are text, number, multiselect, slider, range, daterange, and
	 * datetimerange. This value is intentionally separate from IBase::getName()
	 * so class map names can remain globally unique.
	 *
	 * @return string Lowercase vdef filter type name
	 */
	public function getType(): string;

	/**
	 * Returns alternate vdef names accepted for this filter type.
	 *
	 * Aliases are used only for vdef lookup and migration convenience, for example
	 * multi-select or datetime_range. Return an empty array if no aliases are
	 * supported.
	 *
	 * @return array<int,string> Lowercase or case-insensitive alias names
	 */
	public function getAliases(): array;

	/**
	 * Normalizes the configured match mode for this filter type.
	 *
	 * The returned value is a semantic match mode such as contains, equals, in, or
	 * between. It is not SQL. The implementation may fall back to its default match
	 * mode when the supplied value is empty or unknown.
	 *
	 * @param string $match Raw match value from the vdef filter config
	 * @return string Canonical semantic match value
	 */
	public function normalizeMatch(string $match): string;

	/**
	 * Returns the value used by Clear/Reset when no explicit defaultValue exists.
	 *
	 * This is distinct from getEmptyValue(). A report may intentionally reset to an
	 * active filter, for example a multiselect with two selected default values. In
	 * that case defaultValue is active, while emptyValue describes "no filter".
	 *
	 * @param array<string,mixed> $definition Normalized vdef filter definition
	 * @return mixed Reset/default value for the filter
	 */
	public function getDefaultValue(array $definition): mixed;

	/**
	 * Returns the value that means "this filter is inactive".
	 *
	 * This value controls whether an optional filter is considered visible and
	 * whether a request filter should be removed from the server payload.
	 *
	 * @param array<string,mixed> $definition Normalized vdef filter definition
	 * @return mixed Inactive/empty value for the filter
	 */
	public function getEmptyValue(array $definition): mixed;

	/**
	 * Normalizes an incoming filter value from the browser or vdef.
	 *
	 * Implementations should return stable scalar, array, or object-like array
	 * values that can safely be compared and converted into query conditions.
	 * Invalid values should normalize to the empty value instead of throwing for
	 * ordinary user input.
	 *
	 * @param mixed $value Raw incoming value
	 * @param array<string,mixed> $definition Normalized vdef filter definition
	 * @return mixed Normalized value
	 */
	public function normalizeValue(mixed $value, array $definition): mixed;

	/**
	 * Determines whether a normalized value is inactive for this filter type.
	 *
	 * @param mixed $value Normalized value
	 * @param array<string,mixed> $definition Normalized vdef filter definition
	 * @return bool True when the value should not produce a query condition
	 */
	public function isEmptyValue(mixed $value, array $definition): bool;

	/**
	 * Applies type-specific grid metadata to the field sent to ModularGrid.
	 *
	 * The input field already contains common keys such as key, label, type, match,
	 * visibility, defaultValue, and initialValue. Implementations may add
	 * type-specific keys such as options, min, max, step, format, valueFormat, or
	 * selectedLabel. They must not attach JavaScript functions; that is handled by
	 * the selected IReportFilterControl and the client-side registry.
	 *
	 * @param array<string,mixed> $gridField Common grid filter field
	 * @param array<string,mixed> $field Original vdef field definition
	 * @param array<string,mixed> $definition Normalized vdef filter definition
	 * @return array<string,mixed> Grid filter field
	 */
	public function configureGridField(array $gridField, array $field, array $definition): array;

	/**
	 * Builds a ResourceFoundation query condition for the normalized value.
	 *
	 * The $element parameter is the vdef field element, usually a ResourceFoundation
	 * field expression. Implementations must return query-array structures only;
	 * raw SQL snippets are not allowed in vdefs or generated output here.
	 *
	 * @param mixed $element ResourceFoundation query element for the filtered field
	 * @param mixed $value Normalized, non-empty filter value
	 * @param array<string,mixed> $definition Normalized vdef filter definition
	 * @return array<string,mixed>|null Query condition or null when no condition applies
	 */
	public function buildCondition(mixed $element, mixed $value, array $definition): ?array;
}
