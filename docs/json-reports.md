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

## Report definition scopes

Runtime report configuration is aggregated centrally by Vizion. A feature plugin contributes one independent report scope through ResourceFoundation `IReportConfigDefinitionProvider`.

The provider returns named datasets with an optional `enabled` flag and a `definition` array. The provider may read those datasets from `ISettingsStore`, files, or another backend. Vizion does not own the storage decision.

Report ids can remain unqualified while they are unique across all active technical report scopes. Feature plugins group those technical scopes through `IReportingScopeDefinitionProvider`, and Vizion can address a report through the user-facing reporting scope:

```text
reporting-scope:report
```

Technical `scope:report` lookup remains available internally.

Use materialized report rows for fast interactive grids. Expensive live joins should be moved into DataHawk materializations first.
