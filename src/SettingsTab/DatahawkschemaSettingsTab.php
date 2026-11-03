<?php declare(strict_types=1);

namespace Vizion\SettingsTab;

use UiFoundation\Settings\AbstractFeatureSettingsTab;

final class DatahawkschemaSettingsTab extends AbstractFeatureSettingsTab {

	public function getParentTabName(): ?string {
		return 'datahawksettingstab';
	}

	public function getDisplayName(): ?string {
		return 'datahawkschemadisplay';
	}

	public function getOrder(): int {
		return 10;
	}
}
