<?php
	$json = static function($value): string {
		return json_encode(
			$value,
			JSON_UNESCAPED_UNICODE
			| JSON_UNESCAPED_SLASHES
			| JSON_INVALID_UTF8_SUBSTITUTE
			| JSON_THROW_ON_ERROR
		);
	};
	$translations = $this->getBricks('vizion_report_display');
	$translations = is_array($translations) ? $translations : [];
	$modularGridStrings = $this->getBricks('clientstack_modulargrid');
	$modularGridStrings = is_array($modularGridStrings) ? $modularGridStrings : [];
?>
<div id="reportDatatable"></div>

<script>
document.addEventListener('DOMContentLoaded', () => {
	const TRANSLATIONS = <?php echo $json($translations); ?>;
	const GRID_STRINGS = <?php echo $json($modularGridStrings); ?>;

	function tr(key, fallback) {
		const value = String(TRANSLATIONS[key] || '').trim();
		return value !== '' ? value : fallback;
	}

	function gridTr(key, fallback) {
		const value = String(GRID_STRINGS[key] || '').trim();
		return value !== '' ? value : fallback;
	}

	function formatString(template, replacements = {}) {
		return Object.entries(replacements).reduce((text, [key, value]) => {
			return text.split('{' + key + '}').join(String(value));
		}, String(template || ''));
	}

	function updateSettings(table, settings) {
		table.data('settings', settings);
		table.jqueryDataTable('updateSettings', settings);
	}

	function createLocalizedRenderers() {
		return {
			info(table, data) {
				const total = Number(data.total || 0);
				const page = Number(data.page || data.currentPage || 1);
				const pageSize = Number(data.pageSize || 10);
				const from = total > 0 ? ((page - 1) * pageSize) + 1 : 0;
				const to = total > 0 ? Math.min(page * pageSize, total) : 0;
				const text = formatString(gridTr('recordsRange', 'Records {from} to {to} of {total}'), { from, to, total });
				return $('<div class="jquerydatatable-info"></div>').text(text);
			},

			compactPager(table, data) {
				const settings = table.data('settings');
				const currentPage = Number(data.currentPage || data.page || 1);
				const totalPages = Math.max(1, Number(data.totalPages || 1));
				const wrapper = $('<div class="datatable-pager" style="display: inline-flex; align-items: center; gap: 0.5em;"></div>');
				const previous = $('<button type="button"></button>').text('← ' + gridTr('previous', 'Prev')).prop('disabled', currentPage <= 1);
				const next = $('<button type="button"></button>').text(gridTr('next', 'Next') + ' →').prop('disabled', currentPage >= totalPages);
				const pageStatus = $('<span></span>').text(formatString(gridTr('pageStatus', 'Page {page} of {totalPages}'), {
					page: currentPage,
					totalPages
				}));

				previous.on('click', () => {
					if (currentPage <= 1) return;
					settings.page = currentPage - 1;
					updateSettings(table, settings);
				});

				next.on('click', () => {
					if (currentPage >= totalPages) return;
					settings.page = currentPage + 1;
					updateSettings(table, settings);
				});

				return wrapper.append(previous, pageStatus, next);
			},

			pageSizeSelector(table) {
				const settings = table.data('settings');
				const select = $('<select class="jquerydatatable-per-page-selector"></select>');
				const options = Array.isArray(settings.pageSizeOptions) ? settings.pageSizeOptions : [10, 20, 50];

				options.forEach((value) => {
					const label = formatString(tr('datatable_per_page', '{count} per page'), { count: value });
					select.append($('<option></option>').val(value).text(label).prop('selected', Number(value) === Number(settings.pageSize)));
				});

				select.on('change', function() {
					settings.pageSize = parseInt($(this).val(), 10);
					settings.page = 1;
					updateSettings(table, settings);
				});

				return $('<div class="jquerydatatable-per-page-container"></div>').append(select);
			},

			columnSelector(table) {
				const settings = table.data('settings');
				const wrapper = $('<div class="jquerydatatable-column-selector-wrapper" style="position: relative; display: inline-block;"></div>');
				const button = $('<button type="button" class="column-toggle-btn"></button>').text(gridTr('columns', 'Columns') + ' ▾');
				const menu = $('<div class="column-toggle-menu" style="display:none; position:absolute; top:100%; left:0; z-index:1000; background:white; border:1px solid #ccc; padding:10px; box-shadow: 0 2px 6px rgba(0,0,0,0.2);"></div>');
				const actions = $('<div style="white-space:nowrap;"></div>');

				function setVisible(column, visible) {
					column.visible = visible;
					table.data('settings', settings).trigger('settingsChanged', [settings]);
					table.find('[data-key="' + column.key + '"]').toggle(visible);
				}

				const enableAll = $('<button type="button"></button>').text(tr('datatable_enable_all', 'Enable all')).on('click', (event) => {
					settings.columns.forEach((column) => setVisible(column, true));
					menu.find('input[type="checkbox"]').prop('checked', true);
					event.stopPropagation();
				});
				const disableAll = $('<button type="button"></button>').text(tr('datatable_disable_all', 'Disable all')).on('click', (event) => {
					settings.columns.forEach((column) => setVisible(column, false));
					menu.find('input[type="checkbox"]').prop('checked', false);
					event.stopPropagation();
				});

				actions.append(enableAll, disableAll);
				menu.append(actions, $('<hr style="margin:5px 0;">'));

				settings.columns.forEach((column) => {
					const label = $('<label style="display:block; margin-bottom: 4px; white-space: nowrap; cursor: pointer;"></label>');
					const checkbox = $('<input type="checkbox" name="' + column.key + '">').prop('checked', column.visible !== false);
					checkbox.on('change', function(event) {
						setVisible(column, $(this).is(':checked'));
						event.stopPropagation();
					});
					label.append(checkbox).append(' ' + column.label);
					menu.append(label);
				});

				button.on('click', (event) => {
					event.stopPropagation();
					menu.toggle();
				});
				menu.on('click', (event) => event.stopPropagation());
				$(document).off('click.viziondatatablecolumnselector').on('click.viziondatatablecolumnselector', () => menu.hide());

				return wrapper.append(button, menu);
			},

			resetButton(table) {
				const settings = table.data('settings');
				const button = $('<button type="button" class="jquerydatatable-reset"></button>').text(gridTr('reset', 'Reset'));

				button.on('click', () => {
					const initial = settings._initialSettings || {};
					settings.filters = {};
					settings.sortColumn = initial.sortColumn || '';
					settings.sortDirection = initial.sortDirection || 'asc';
					settings.pageSize = initial.pageSize || settings.pageSize || 10;
					settings.page = initial.page || 1;
					settings.columns.forEach((column, index) => {
						column.visible = initial.columns && initial.columns[index]
							? initial.columns[index].visible !== false
							: true;
					});
					updateSettings(table, settings);
				});

				return $('<div class="jquerydatatable-reset-container"></div>').append(button);
			},

			filterCell(column, settings, table) {
				let timer = null;
				const currentValue = settings.filters && settings.filters[column.key] ? settings.filters[column.key] : '';
				const wrapper = $('<div style="position: relative; width: 100%;"></div>');
				const input = $('<input type="text" style="width: 100%; box-sizing: border-box; padding-right: 18px;">')
					.attr('placeholder', tr('datatable_filter_placeholder', 'Filter...'))
					.val(currentValue);
				const reset = $('<span style="position: absolute; right: 6px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #aaa; font-size: 12px;">✖</span>')
					.attr('title', tr('datatable_reset_filter', 'Reset filter'));

				input.on('input', function() {
					const value = $(this).val();
					window.clearTimeout(timer);
					timer = window.setTimeout(() => {
						settings.filters = settings.filters || {};
						settings.filters[column.key] = value;
						settings.page = 1;
						updateSettings(table, settings);
					}, 300);
					reset.toggle(Boolean(value));
				});

				reset.on('click', () => input.val('').trigger('input'));
				reset.toggle(Boolean(currentValue));
				return wrapper.append(input, reset);
			},

			filterCellSelect(column, settings, table) {
				const currentValue = settings.filters && settings.filters[column.key] ? settings.filters[column.key] : '';
				const select = $('<select style="width:100%; box-sizing:border-box;"></select>');
				select.append($('<option></option>').val('').text(tr('datatable_all', 'All')));
				(column.options || []).forEach((option) => {
					select.append($('<option></option>').val(option.value).text(option.label));
				});
				select.val(currentValue);
				select.on('change', function() {
					settings.filters = settings.filters || {};
					settings.filters[column.key] = $(this).val();
					settings.page = 1;
					updateSettings(table, settings);
				});
				return select;
			}
		};
	}

	async function initReportDatatable(columns, config) {
		await AssetLoader.loadCssAsync('<?php echo $this->_['resolve']('plugin/ClientStack/assets/jquerydatatable/jquery.datatable.min.css'); ?>');
		await AssetLoader.loadScriptAsync('<?php echo $this->_['resolve']('plugin/ClientStack/assets/jquerydatatable/jquery.datatable.min.js'); ?>');

		$('#reportDatatable').jqueryDataTable({
			dataSource: <?php echo $json((string) $this->_['ajaxUrl']); ?>,
			columns: columns,
			pageSize: config.config.pageSize ?? 10,
			sortColumn: config.config.sortColumn ?? columns[0]?.key ?? null,
			sortDirection: config.config.sortDirection ?? 'asc',
			renderers: createLocalizedRenderers(),
			layoutTargets: {
				'.header-left': ['columnSelector'],
				'.header-right': ['compactPager'],
				'.footer-left': ['resetButton'],
				'.footer-center': ['info'],
				'.footer-right': ['pageSizeSelector']
			}
		});
	}

	const columns = <?php echo $json($this->_['columns']); ?>;
	const config = <?php echo $json($this->_['config']); ?>;
	initReportDatatable(columns, config);
});
</script>
