<div id="reportDatatable"></div>

<script>
	document.addEventListener('DOMContentLoaded', async () => {
		await AssetLoader.loadCssAsync('<?php echo $this->_['resolve']('plugin/ClientStack/assets/jquerydatatable/jquery.datatable.min.css'); ?>');
		await AssetLoader.loadScriptAsync('<?php echo $this->_['resolve']('plugin/ClientStack/assets/jquerydatatable/jquery.datatable.min.js'); ?>');

		const columns = <?php echo json_encode($this->_['columns'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
		const ajaxUrl = "<?php echo $this->_['ajaxUrl']; ?>";
		const pageSize = <?php echo (int) $this->_['pageSize']; ?>;

		$('#reportDatatable').jqueryDataTable({
			dataSource: ajaxUrl,
			columns: columns,
			pageSize: pageSize,
			sortColumn: columns[0]?.key ?? null,
			sortDirection: 'asc',
			layoutTargets: {
				'.footer-right': ['pager'],
				'.footer-left': ['pageSizeSelector'],
				'.footer-center': ['info'],
				'.header-right': ['resetButton'],
				'.header-left': ['columnSelector']
			}
		});
	});
</script>
