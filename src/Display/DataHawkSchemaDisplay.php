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

namespace Vizion\Display;

use Base3\Api\IAssetResolver;
use Base3\Api\IMvcView;
use Base3\Api\IDisplay;
use ModuledPage\Page\AbstractModuleContent;
use ResourceFoundation\Api\IQuerySchemaProvider;

class DataHawkSchemaDisplay implements IDisplay {

	private $displayData;

	public function __construct(
		private readonly IMvcView $view,
		private readonly IQuerySchemaProvider $queryschemaprovider,
		private readonly IAssetResolver $assetResolver
	) {}

	// Implementation of IBase

	public static function getName(): string {
		return 'datahawkschemadisplay';
	}

	// Implementation of IDisplay

	public function setData($data) {
		$this->displayData = $data;
	}

	// Implementation of IOutput

	public function getOutput(string $out = 'html', bool $final = false): string {

		$data = ['data' => [], 'foreignKeys' => []];
		$schema = $this->queryschemaprovider->getSchema();

		foreach ($schema as $table) {

			if ($this->displayData != null && isset($this->displayData['domain']) && $table->domain != $this->displayData['domain']) continue;

			$fields = [];
			$primaryKeys = [];
			foreach ($table->fields as $field) {
				if ($field->primaryKey) $primaryKeys[] = $field->name;
				$fields[] = [
					'name' => $field->name,
					'type' => ($field->nullable ? '?' : '') . $field->type
				];
			}
			$data['data'][] = [
				'id' => $table->name,
				'name' => $table->name,
				'fields' => $fields,
				'primaryKeys' => $primaryKeys,
				'position' => array_merge(['x' => 100, 'y' => 100], $table->position ?? [])
			];

			foreach ($table->joins as $join) {
				foreach ($join->on as $from => $to) {
					$fromParts = explode('.', $from);
					$toParts = explode('.', $to);
					$data['foreignKeys'][] = [
						'from' => [
							'tableId' => $fromParts[0],
							'tableName' => $fromParts[0],
							'fieldName' => $fromParts[1]
						],
						'to' => [
							'tableId' => $toParts[0],
							'tableName' => $toParts[0],
							'fieldName' => $toParts[1]
						]
					];
				}
			}
		}

		$this->view->setPath(DIR_PLUGIN . 'Vizion');
		$this->view->setTemplate('Display/DataHawkSchemaDisplay.php');
		$this->view->assign('data', $data);
		$this->view->assign('resolve', fn($src) => $this->assetResolver->resolve($src));
		return $this->view->loadTemplate();
	}

	public function getHelp(): string {
		return 'Help of DataHawkSchemaDisplay';
	}
}
