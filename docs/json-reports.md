# Vizion JSON Reports

Vizion reports can be defined as JSON files and executed through `generalreportdisplay`.
The display loads a named report config and passes it to the configured report display implementation.

## Materialized report tables

A report can read directly from a DataHawk materialized logical table:

```json
{
	"query": {
		"type": "select",
		"schema": "ilias_materialized",
		"table": "course_report_rows"
	}
}
```

DataHawk resolves the logical table through the materialization registry. Vizion does not need to know the physical `base3_mat_*` table name.

## Recommended structure

Keep report configs project-local, for example:

```text
Base3IliasLab/local/Vizion/course_report_rows.json
```

Use materialized report rows for fast interactive grids. Expensive live joins should be moved into DataHawk materialization manifests first.
