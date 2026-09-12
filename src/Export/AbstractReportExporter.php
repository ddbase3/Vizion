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

namespace Vizion\Export;

use ResourceFoundation\Api\IReportExporter;
use ResourceFoundation\Dto\QueryResult;

abstract class AbstractReportExporter implements IReportExporter {

	private ?QueryResult $result = null;

	public function setResult(QueryResult $result): self {
		$this->result = $result;
		return $this;
	}

	public function getResult(): ?QueryResult {
		return $this->result;
	}

	public function toFile(string $filePath): self {
		$content = $this->toString();

		if(file_put_contents($filePath, $content) === false) {
			throw new \RuntimeException('Failed to write report export: ' . $filePath);
		}

		return $this;
	}

	protected function requireResult(): QueryResult {
		if(!$this->result instanceof QueryResult) {
			throw new \RuntimeException('No query result set for export.');
		}

		return $this->result;
	}

	/**
	 * @param array<string, mixed> $column
	 */
	protected function getColumnLabel(array $column): string {
		$label = trim((string)($column['label'] ?? ''));
		return $label !== '' ? $label : (string)($column['name'] ?? '');
	}

	/**
	 * @param array<string, mixed>|array<int, mixed> $row
	 * @param array<string, mixed> $column
	 */
	protected function getRowValue(array $row, array $column, int $index): mixed {
		$name = (string)($column['name'] ?? '');
		if($name !== '' && array_key_exists($name, $row)) {
			return $row[$name];
		}

		$values = array_values($row);
		return $values[$index] ?? null;
	}
}
