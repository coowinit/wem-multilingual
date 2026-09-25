# ADR-005：Taxonomy 延后决策

## Status

Deferred

## Decision

Taxonomy 不进入 v0.1.0，也不在当前文档中假定最终实现。

## Reason

Single-source Post 与 Multi-term Taxonomy 的组合存在多个互相权衡的候选方案：

```text
Multi-Term Bridge
Source-Term Query Mapping
Shadow Tax Query
Multi-object
```

目前任何一个方案都存在第三方兼容或数据冗余代价。

## Next

建立：

```text
Taxonomy Architecture Lab
```

真实测试：

```text
WP_Query
tax_query
get_the_terms
REST
Breadcrumb
SEO
Elementor
Filter Plugins
Direct SQL
```

验证后再创建新的 ADR。
