# 05. Elementor Structured Translation 策略

> 状态：**v0.1.1 核心实验已验证；仍属于 Experimental Adapter**

## 1. 当前策略

Elementor 采用：

```text
Adapter Registry
+
Read-only Source Discovery
+
Translation Repository
+
Structured Locator-scoped Runtime Overlay
```

明确不采用：

```text
把目标语言写回 _elementor_data
全页面 DOM Translator
按 English 文本做全局字符串替换
前台实时调用 Translation Provider
```

核心原则：

> **知道 Widget / Field 身份时才翻译；不知道时不猜。**

---

## 2. Source Discovery

Source Discovery 只读：

```text
_elementor_data
↓
json_decode
↓
Widget Tree
↓
Adapter Registry
↓
Widget Type
↓
Setting Path
↓
Structured Source Field
```

当前已经验证：

```text
heading.title
button.text
```

Source Discovery 不执行：

```text
UPDATE _elementor_data
```

Translation、Runtime Overlay、Cache Invalidation 也不会把译文写回 Elementor JSON。

---

## 3. Translation Unit Identity

当前 v0.1.1 使用 locator-backed Context：

```text
elementor:{object_id}:{element_id}:{widget_type}:{setting_path}
```

例如：

```text
elementor:1730:6f3c7fe:heading:title
elementor:1730:fdad941:button:text
```

Context 当前承担 Source Unit Identity。

Source 内容变化时：

```text
Context 保持
Source Unit ID 保持
source_hash 更新
Translation 变 stale
```

但这不等于宣称：

> Elementor Widget ID 是永久业务身份。

当前仍坚持：

> **element_id 是 Locator；未来复杂迁移可能需要更高层 Identity / Occurrence 模型。**

---

## 4. Hash = Version

每个 Source Field 保存：

```text
source_text
source_hash
normalized_hash
```

Translation 保存：

```text
translated_text
translated_from_hash
status
```

当：

```text
source_hash != translated_from_hash
```

Runtime 不应用旧译文，而是：

```text
fallback source
```

该机制已经通过 Source Drift / Stale / Re-review 实验验证。

---

## 5. Runtime Overlay

### 5.1 实验结论

Runtime Hook 曾依次验证：

```text
before_render
elementor/widget/render_content
elementor/frontend/the_content
```

结论：

```text
before_render
→ 受 Elementor render state / cache path 影响，不作为唯一稳定层

render_content
→ 可进入部分 Widget 渲染路径，保留用于诊断和局部处理
→ 但不能假设所有缓存路径都会进入

elementor/frontend/the_content
→ 当前实验环境中最稳定的最终 Structured Overlay 层
```

### 5.2 为什么这不是 Global DOM Translator

虽然最终稳定 Hook 位于 Elementor Content 层，但处理流程不是：

```text
查找某段 English
→ 全文替换
```

而是：

```text
Adapter-approved field
↓
object_id
↓
element_id
↓
widget_type
↓
setting_path
↓
Source Unit / Translation
↓
精确定位 data-id Widget
↓
只修改已知 DOM target
```

当前 DOM target：

```text
heading.title
→ .elementor-heading-title

button.text
→ .elementor-button-text
```

因此当前实现属于：

> **Structured Locator-scoped Runtime Overlay**

而不是通用 DOM 翻译器。

---

## 6. Runtime Gate

只有以下条件同时满足时才应用 Translation：

```text
目标语言正确
Object Language State = published
Source Unit = active
Live Source Hash = Stored Source Hash
Translation exists
Translation status = reviewed
Translation is current
```

否则：

```text
fallback source
```

这让 Source Drift、Stale Translation、Draft Language State 都有明确安全回退。

---

## 7. 第一批已验证 Widget

当前已验证：

```text
Heading
└─ title ✅

Button
└─ text ✅
```

这两类字段已经完整跑通：

```text
Discovery
Source Unit
Translation
Hash / stale
Runtime Overlay
Cache Invalidation
Runtime Validation
```

下一批候选：

```text
Text Editor
Image
Icon List
```

但是否继续扩展，应先看是否会引入新的结构复杂度。

---

## 8. Cache Invalidation

Elementor Runtime 正确后，还必须处理页面缓存。

当前统一通过：

```text
WEM_ML_Cache_Invalidator
```

自动触发已验证：

```text
Translation change
Source change
Object Language State change
Translated Slug change
```

WP Rocket 环境优先：

```text
rocket_clean_files()
```

不主动执行：

```text
rocket_clean_domain()
```

Translated Slug 变化时会清：

```text
English URL
Old Spanish URL
New Spanish URL
```

重复保存相同值不重复 purge。

Manual Purge Lab 继续保留用于诊断。

---

## 9. Diagnostics / Lab

v0.1.1 保留：

```text
Elementor Source Discovery Lab
Elementor Source Sync Lab
Elementor Translation Lab
Structured Runtime Validation
Cache Environment / Manual Purge Diagnostics
```

这些页面属于长期：

```text
教学
诊断
回归测试
架构证据
```

不是临时代码。

---

## 10. 当前仍未解决的问题

以下能力不能因为 Heading / Button 已通过就视为解决：

```text
Widget 删除重建后的 Translation Migration
Widget Duplicate
Page Duplicate
Template Insert
Global Widget
Dynamic Tags
Repeater
Accordion / Tabs
Loop Grid
Occurrence Table
多目标语言
```

尤其：

> **Widget Identity Migration 仍是当前 Elementor 架构最重要的未完成问题之一。**

---

## 11. 当前结论

v0.1.1 已经证明：

```text
Adapter Registry
+
Read-only Elementor Source Discovery
+
Context / Hash / State
+
Translation Repository
+
Structured Runtime Overlay
+
Targeted Cache Invalidation
```

可以在 Heading.title 与 Button.text 范围内成立。

详细实验记录见：

- [v0.1.1 Elementor Structured Translation Lab 验收报告](09-v0.1.1-elementor-validation.md)
- [ADR-004：Elementor 策略](adr/ADR-004-elementor-strategy.md)
