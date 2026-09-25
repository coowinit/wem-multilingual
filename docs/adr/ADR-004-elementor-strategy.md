# ADR-004：Elementor 策略

## Status

Provisional / v0.1.1 Experiment

## Decision

Elementor 不进入 v0.1.0。

v0.1.1 采用：

```text
Adapter Registry
+
Read-only _elementor_data Source Scan
+
Widget-scoped Runtime Overlay
```

禁止：

```text
修改 _elementor_data
全局 DOM Translator
全局 the_content Translation Core
```

## Identity

最终身份模型尚未完全确定。

当前原则：

```text
WEM Translation Unit
≠
Elementor Widget ID
```

Widget ID 可作为 Locator。

v0.1.1 通过实验确定：

```text
Locator
Migration
Source Hash
Widget lifecycle
```
