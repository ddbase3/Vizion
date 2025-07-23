<div id="reportDatatable"></div>

<script>
	async function initReportDatatable(ajaxUrl, columns, pageSize) {
		await AssetLoader.loadCssAsync('<?php echo $this->_['resolve']('plugin/ClientStack/assets/jquerydatatable/jquery.datatable.min.css'); ?>');
		await AssetLoader.loadScriptAsync('<?php echo $this->_['resolve']('plugin/ClientStack/assets/jquerydatatable/jquery.datatable.min.js'); ?>');

		$('#reportDatatable').jqueryDataTable({
			dataSource: ajaxUrl,
			columns: columns,
			pageSize: pageSize,
			sortColumn: columns[0]?.key ?? null,
			sortDirection: 'asc',
			layoutTargets: {
				'.header-left': ['columnSelector'],
				'.header-right': ['compactPager'],
				'.footer-left': ['resetButton'],
				'.footer-center': ['info'],
				'.footer-right': ['pageSizeSelector']
			}
		});
	}

	var ajaxUrl = "<?php echo $this->_['ajaxUrl']; ?>";
	var columns = <?php echo json_encode($this->_['columns'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
	var pageSize = <?php echo (int) $this->_['pageSize']; ?>;

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', () => {
			initReportDatatable(ajaxUrl, columns, pageSize);
		});
	} else {
		initReportDatatable(ajaxUrl, columns, pageSize);
	}
</script>

