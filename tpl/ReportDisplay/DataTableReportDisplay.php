<div id="reportDatatable"></div>

<script>
	async function initReportDatatable(columns, config) {
		await AssetLoader.loadCssAsync('<?php echo $this->_['resolve']('plugin/ClientStack/assets/jquerydatatable/jquery.datatable.min.css'); ?>');
		await AssetLoader.loadScriptAsync('<?php echo $this->_['resolve']('plugin/ClientStack/assets/jquerydatatable/jquery.datatable.min.js'); ?>');

		$('#reportDatatable').jqueryDataTable({
			dataSource: "<?php echo $this->_['ajaxUrl']; ?>",
			columns: columns,
			pageSize: config.config.pageSize ?? 10,
			sortColumn: config.config.sortColumn ?? columns[0]?.key ?? null,
			sortDirection: config.config.sortDirection ?? 'asc',
			layoutTargets: {
				'.header-left': ['columnSelector'],
				'.header-right': ['compactPager'],
				'.footer-left': ['resetButton'],
				'.footer-center': ['info'],
				'.footer-right': ['pageSizeSelector']
			}
		});
	}

	var columns = <?php echo json_encode($this->_['columns'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
	var config = <?php echo json_encode($this->_['config'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', () => {
			initReportDatatable(columns, config);
		});
	} else {
		initReportDatatable(columns, config);
	}
</script>

