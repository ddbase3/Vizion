<?php declare(strict_types=1);

namespace Vizion\Filter\Type;

use Vizion\Api\IReportFilterType;

abstract class AbstractReportFilterType implements IReportFilterType {

	public function getAliases(): array {
		return [];
	}

	public function normalizeMatch(string $match): string {
		$match = strtolower(trim($match));

		if($match === '') {
			return $this->getDefaultMatch();
		}

		return match($match) {
			'=', 'eq', 'equals' => 'equals',
			'!=', '<>', 'neq', 'not_equals', 'not-equals' => 'notEquals',
			'contains', 'like' => 'contains',
			'startswith', 'starts_with', 'starts-with' => 'startsWith',
			'endswith', 'ends_with', 'ends-with' => 'endsWith',
			'in' => 'in',
			'between' => 'between',
			'>', 'gt' => 'gt',
			'>=', 'gte' => 'gte',
			'<', 'lt' => 'lt',
			'<=', 'lte' => 'lte',
			default => $this->getDefaultMatch()
		};
	}

	public function getDefaultValue(array $definition): mixed {
		return array_key_exists('defaultValue', $definition)
			? $definition['defaultValue']
			: $this->getEmptyValue($definition);
	}

	public function getEmptyValue(array $definition): mixed {
		return array_key_exists('emptyValue', $definition) ? $definition['emptyValue'] : '';
	}

	public function normalizeValue(mixed $value, array $definition): mixed {
		return $this->normalizeScalarTextValue($value);
	}

	public function isEmptyValue(mixed $value, array $definition): bool {
		$emptyValue = array_key_exists('emptyValue', $definition)
			? $this->normalizeValue($definition['emptyValue'], $definition)
			: $this->normalizeValue($this->getEmptyValue($definition), $definition);

		if($this->valuesEqual($value, $emptyValue)) {
			return true;
		}

		if(is_array($value)) {
			return count(array_filter($value, fn($entry) => $entry !== '' && $entry !== null && $entry !== [])) === 0;
		}

		return $value === '' || $value === null;
	}

	public function configureGridField(array $gridField, array $field, array $definition): array {
		return $gridField;
	}

	public function buildCondition(mixed $element, mixed $value, array $definition): ?array {
		$match = $this->normalizeMatch((string) ($definition['match'] ?? ''));

		return match($match) {
			'equals' => $this->buildBinaryCondition($element, '=', $value),
			'notEquals' => $this->buildBinaryCondition($element, '<>', $value),
			'contains' => $this->buildLikeCondition($element, '%' . (string) $value . '%'),
			'startsWith' => $this->buildLikeCondition($element, (string) $value . '%'),
			'endsWith' => $this->buildLikeCondition($element, '%' . (string) $value),
			'gt' => $this->buildBinaryCondition($element, '>', $value),
			'gte' => $this->buildBinaryCondition($element, '>=', $value),
			'lt' => $this->buildBinaryCondition($element, '<', $value),
			'lte' => $this->buildBinaryCondition($element, '<=', $value),
			default => $this->buildLikeCondition($element, '%' . (string) $value . '%')
		};
	}

	protected function getDefaultMatch(): string {
		return 'contains';
	}

	protected function normalizeScalarTextValue(mixed $value): string {
		return is_scalar($value) ? trim((string) $value) : '';
	}

	protected function normalizeNumberValue(mixed $value): mixed {
		if($value === null || $value === '') {
			return '';
		}

		if(!is_scalar($value) || !is_numeric($value)) {
			return '';
		}

		$number = (string) $value;

		return str_contains($number, '.') ? (float) $number : (int) $number;
	}

	protected function valuesEqual(mixed $left, mixed $right): bool {
		return json_encode($left, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) === json_encode($right, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}

	/** @return array<string,mixed> */
	protected function buildBinaryCondition(mixed $element, string $operator, mixed $value): array {
		return [
			'type' => 'op',
			'operator' => $operator,
			'params' => [$element, $value]
		];
	}

	/** @return array<string,mixed> */
	protected function buildLikeCondition(mixed $element, string $value): array {
		return $this->buildBinaryCondition($element, 'LIKE', $value);
	}
}
