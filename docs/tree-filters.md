# Vizion tree filters

Vizion tree filters are prominent hierarchical report filters for ModularGrid reports. They are separate from normal field filters and only exist when the report definition contains a `treeFilters` array.

The browser control is generic ClientStack code. Vizion owns the report-state and query integration. Domain plugins own the actual hierarchy sources and report bindings.

## Runtime flow

```text
Vizion report definition
        |
        v
IReportTreeFilterService
        |
        +-- tree endpoint -> generic nodes for ClientStack
        |
        +-- selected node validation
        |
        +-- structured ResourceFoundation query condition
        v
ModularGridReportQueryBuilder
        |
        v
IQueryService
```

`treeFilters` is a separate ModularGrid state section. Breadcrumb, tree folding and search do not create additional report filter state.

## Minimal definition

```json
{
  "treeFilters": [
    {
      "key": "category",
      "label": "Categories",
      "shortLabel": "Categories",
      "emptyLabel": "Not filtered",
      "clearLabel": "Clear category filter",
      "accentColor": "#6f7f55",
      "searchPlaceholder": "Search categories",
      "source": {
        "schema": "example",
        "table": "category_tree",
        "id": { "type": "fld", "table": "category_tree", "field": "id" },
        "parentId": { "type": "fld", "table": "category_tree", "field": "parent_id" },
        "label": { "type": "fld", "table": "category_tree", "field": "label" },
        "tree": { "type": "fld", "table": "category_tree", "field": "tree" },
        "left": { "type": "fld", "table": "category_tree", "field": "lft" },
        "right": { "type": "fld", "table": "category_tree", "field": "rgt" },
        "depth": { "type": "fld", "table": "category_tree", "field": "depth" }
      },
      "filter": {
        "type": "node",
        "element": { "type": "fld", "table": "report_rows", "field": "category_id" }
      }
    }
  ]
}
```

`source.where` can restrict which hierarchy nodes are selectable. `source.orderBy` can override the default ordering by tree and left boundary.

The optional presentation keys `shortLabel`, `emptyLabel`, `clearLabel` and `accentColor` only affect the generic ClientStack control. An empty selection is represented by the configured empty string and is deliberately distinct from selecting the hierarchy root.

## Filter types

### `range`

Use `range` when the report query already contains the same nested-set coordinates as the selected hierarchy.

```json
{
  "type": "range",
  "tree": { "type": "fld", "table": "report_tree", "field": "tree" },
  "left": { "type": "fld", "table": "report_tree", "field": "lft" },
  "right": { "type": "fld", "table": "report_tree", "field": "rgt" }
}
```

The generated condition compares the report row directly with the selected tree, left and right bounds. This is the preferred path when the report table already carries those indexed coordinates.

### `node`

Use `node` when the report row can be matched to values derived from the selected hierarchy subtree.

```json
{
  "type": "node",
  "element": { "type": "fld", "table": "report_rows", "field": "object_id" },
  "value": { "type": "fld", "table": "tree_objects", "field": "object_id" }
}
```

Vizion creates a structured subquery over the selected hierarchy nested-set range and filters the report `element` with `IN`. By default the subquery returns `source.id`. The optional `filter.value` can select another related value instead, for example a stable domain object id while the tree itself is addressed by reference ids.

An optional `filter.where` restricts the descendant subquery when only a subset of related records should contribute values.

### `path_membership`

Use `path_membership` when the authoritative hierarchy is a parent chain and a materialized path read model is available. This is appropriate when nested-set coordinates are unavailable or unreliable.

```json
{
  "type": "path_membership",
  "element": { "type": "fld", "table": "report_rows", "field": "object_id" },
  "source": {
    "schema": "reporting",
    "table": "repository_paths",
    "value": { "type": "fld", "table": "repository_paths", "field": "object_id" },
    "path": { "type": "fld", "table": "repository_paths", "field": "repository_path" },
    "delimiter": ","
  }
}
```

Vizion validates the selected tree node through the configured tree source. It then selects distinct source values whose materialized path contains the selected node id as a complete path segment and filters the report element with `IN`. The delimiter on both sides prevents partial identifier matches.

### `membership`

Use `membership` when report rows are related to a hierarchy through another indexed membership table.

```json
{
  "type": "membership",
  "element": { "type": "fld", "table": "report_rows", "field": "user_id" },
  "source": {
    "schema": "example",
    "table": "tree_membership",
    "value": { "type": "fld", "table": "tree_membership", "field": "user_id" },
    "tree": { "type": "fld", "table": "tree_membership", "field": "tree" },
    "left": { "type": "fld", "table": "tree_membership", "field": "lft" },
    "right": { "type": "fld", "table": "tree_membership", "field": "rgt" }
  }
}
```

Vizion selects distinct membership values inside the selected subtree and filters the report element with `IN`.

## Security

Tree loading, selected-node validation and report filtering all use `ResourceFoundation\Api\IQueryService`. Host or project query-security constraints therefore apply to the hierarchy queries and nested subqueries through the same structured query path as the report itself.

An unavailable selected node is rejected. It is not silently converted into an unfiltered report.

## Performance

Tree nodes are loaded lazily when a control is first opened. Report reloads submit only the selected node id.

For report filtering, prefer this order when the data model permits it:

1. `range` against indexed nested-set columns already present in the report query
2. `membership` against an indexed membership fact
3. `path_membership` against a small materialized path read model when parent links are authoritative
4. `node` against an indexed hierarchy id and nested-set range

Do not copy complete hierarchy payloads into report state or materialized report rows solely for browser rendering.
