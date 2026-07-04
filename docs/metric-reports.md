# Metric reports

Metric reports render compact dashboard cards backed by structured Resource/DataHawk queries.
They are intended for headline numbers such as users, active users, tutors, courses, certificates, or other small KPI sets.

## Display

```json
"display": "metricreportdisplay"
```

The display is discoverable through `IBase::getName()` as `metricreportdisplay`.

## Vdef structure

```json
{
  "display": "metricreportdisplay",
  "schema": "ilias_materialized",
  "table": "course_report_rows",
  "config": {
    "title": "User Kennzahlen",
    "cardMinWidth": 150
  },
  "metrics": [
    {
      "key": "users_total",
      "label": "User gesamt",
      "valueMode": "row_count",
      "distinct": true,
      "field": "usr_id"
    }
  ]
}
```

## Metric query modes

A metric can either provide a full query:

```json
{
  "key": "courses_total",
  "label": "Kurse gesamt",
  "valueField": "value",
  "query": {
    "type": "select",
    "schema": "ilias_materialized",
    "table": "course_report_rows",
    "fields": [
      {
        "element": {
          "type": "fn",
          "function": "COUNT",
          "params": ["*"]
        },
        "alias": "value"
      }
    ]
  }
}
```

or a compact shorthand. The shorthand is still Resource/DataHawk based; `where` is a normal structured expression.

```json
{
  "key": "active_users",
  "label": "Aktive User",
  "valueMode": "row_count",
  "distinct": true,
  "field": "usr_id",
  "where": {
    "type": "op",
    "operator": "=",
    "params": [
      { "type": "fld", "table": "course_report_rows", "field": "user_active" },
      1
    ]
  }
}
```

## Value modes

| Mode | Meaning |
| --- | --- |
| `first_value` | Uses the first scalar value from the first returned row. This is the default and fits `COUNT(*) AS value`. |
| `row_count` | Counts returned rows. This is useful for `distinct: true` queries such as unique users. |
| `sum` | Sums one returned value field over all rows. |

## Formatting

```json
"format": {
  "type": "number",
  "decimals": 0,
  "suffix": " %"
}
```

Metric reports do not use raw SQL strings. Complex logic should be expressed through Resource/DataHawk elements, or prepared in a materialized table and then read through a simple metric query.
