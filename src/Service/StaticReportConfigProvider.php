<?php declare(strict_types=1);

namespace Vizion\Service;

use Vizion\Api\IReportConfigProvider;

class StaticReportConfigProvider implements IReportConfigProvider {

	public function getConfig(string $report): array {
		if ($report !== "example") {
			throw new \Exception("Report not found: $report");
		}

		return [
			"display" => "datatablereportdisplay",
			"config" => [
				"paging" => true,
				"columnSelector" => true
			],
			"fields" => [
				[
					"alias" => "repository_name",
					"element" => [
						"type" => "fld",
						"table" => "git_repository",
						"field" => "name"
					],
					"config" => [
						"label" => "Repository",
						"sortable" => true,
						"filter" => [
							"type" => "text",
							"placeholder" => "Filter Repository"
						]
					]
				],
				[
					"alias" => "size",
					"element" => [
						"type" => "fld",
						"table" => "git_repository",
						"field" => "size"
					],
					"config" => [
						"label" => "Size",
						"sortable" => true,
						"filter" => [
							"type" => "numberrange"
						]
					]
				]
			],
			"from" => "git_repository",
			"where" => null
		];
	}
}

