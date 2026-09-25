# ADR-001：核心对象模型

## Status

Accepted for v0.1.0

## Context

我们需要避免 Elementor 企业站因“一语言一 Post”造成页面结构复制和长期漂移，但又不能强迫所有 WordPress Object 使用统一 Single Source 模型。

## Decision

v0.1.0：

```text
Page / Post
→ Single Source + Structured Overlay
```

其他对象：

```text
Elementor
→ v0.1.1 Adapter Lab

Taxonomy
→ Deferred Lab

Menu
→ Per-language Later
```

## Rejected

### Universal Single Source

原因：

```text
Taxonomy / Menu / Third-party ecosystem
```

兼容风险过大。

### Universal Multi-object

原因：

企业站 Elementor 页面结构会被复制多份，维护成本高。

## Consequences

Core 只负责：

```text
Language
Routing
Repository
State
Structured Overlay
SEO
```

不同对象通过 Adapter / Lab 扩展。
