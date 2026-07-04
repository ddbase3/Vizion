<?php declare(strict_types=1);

namespace Vizion\Filter\Type;

trait DateValueTrait {

	private function normalizeDateValue(mixed $value, array $definition, string $type): string {
		$text = is_scalar($value) ? trim((string) $value) : '';

		if($text === '') {
			return '';
		}

		$targetFormat = $this->getDateValueFormat($definition, $type);
		$formats = $this->getDateInputFormats($definition, $type, $targetFormat);

		foreach($formats as $format) {
			$parts = $this->parseDateValue($text, $format);

			if($parts !== null) {
				return $this->formatDateValue($parts, $targetFormat);
			}
		}

		return $text;
	}

	private function getDateDisplayFormat(array $definition, string $type): string {
		if(isset($definition['format']) && is_scalar($definition['format'])) {
			return (string) $definition['format'];
		}

		return in_array($type, ['datetime', 'datetimerange'], true) ? 'YYYY-MM-DD HH:mm' : 'YYYY-MM-DD';
	}

	private function getDateValueFormat(array $definition, string $type): string {
		foreach(['valueFormat', 'submitFormat', 'storageFormat', 'queryFormat'] as $key) {
			if(isset($definition[$key]) && is_scalar($definition[$key])) {
				return (string) $definition[$key];
			}
		}

		return $this->getDateDisplayFormat($definition, $type);
	}

	/** @return array<int,string> */
	private function getDateInputFormats(array $definition, string $type, string $targetFormat): array {
		$formats = [
			$targetFormat,
			$this->getDateDisplayFormat($definition, $type),
			'YYYY-MM-DD',
			'DD.MM.YYYY'
		];

		if(in_array($type, ['datetime', 'datetimerange'], true)) {
			$formats[] = 'YYYY-MM-DD HH:mm';
			$formats[] = 'YYYY-MM-DDTHH:mm';
			$formats[] = 'DD.MM.YYYY HH:mm';
		}

		return array_values(array_unique($formats));
	}

	/** @return array<string,string>|null */
	private function parseDateValue(string $value, string $format): ?array {
		$tokens = [
			'YYYY' => '(\\d{4})',
			'MM' => '(\\d{2})',
			'DD' => '(\\d{2})',
			'HH' => '(\\d{2})',
			'mm' => '(\\d{2})'
		];
		$tokenNames = array_keys($tokens);
		$tokenOrder = [];
		$pattern = '/^';
		$index = 0;
		$length = strlen($format);

		while($index < $length) {
			$matchedToken = null;

			foreach($tokenNames as $token) {
				if(substr($format, $index, strlen($token)) === $token) {
					$matchedToken = $token;
					break;
				}
			}

			if($matchedToken !== null) {
				$pattern .= $tokens[$matchedToken];
				$tokenOrder[] = $matchedToken;
				$index += strlen($matchedToken);
				continue;
			}

			$pattern .= preg_quote($format[$index], '/');
			$index++;
		}

		$pattern .= '$/';

		if(preg_match($pattern, $value, $matches) !== 1) {
			return null;
		}

		$parts = [
			'YYYY' => '1970',
			'MM' => '01',
			'DD' => '01',
			'HH' => '00',
			'mm' => '00'
		];

		foreach($tokenOrder as $position => $token) {
			$parts[$token] = (string) $matches[$position + 1];
		}

		$timestamp = mktime((int) $parts['HH'], (int) $parts['mm'], 0, (int) $parts['MM'], (int) $parts['DD'], (int) $parts['YYYY']);

		if($timestamp === false) {
			return null;
		}

		if(date('Y', $timestamp) !== $parts['YYYY'] || date('m', $timestamp) !== $parts['MM'] || date('d', $timestamp) !== $parts['DD'] || date('H', $timestamp) !== $parts['HH'] || date('i', $timestamp) !== $parts['mm']) {
			return null;
		}

		return $parts;
	}

	/** @param array<string,string> $parts */
	private function formatDateValue(array $parts, string $format): string {
		return str_replace(
			['YYYY', 'MM', 'DD', 'HH', 'mm'],
			[$parts['YYYY'], $parts['MM'], $parts['DD'], $parts['HH'], $parts['mm']],
			$format
		);
	}
}
