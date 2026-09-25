# 05. Elementor Structured Translation 策略

> 状态：**v0.1.1 实验方向，尚未最终实现锁定**

## 1. 当前共识

不采用：

```text
全页面 DOM Translator
把目标语言写回 _elementor_data
全局 the_content String Replacement
```

采用：

```text
Adapter Registry
+
Read-only Source Discovery
+
Widget-scoped Runtime Overlay
```

---

## 2. Source Discovery

后台 Scan：

```text
_elementor_data
↓
只读 json_decode
↓
Widget Tree
↓
Widget Type
↓
Setting Path
↓
Translation Unit
```

禁止：

```text
UPDATE _elementor_data
```

---

## 3. Runtime

运行时由：

```text
Elementor Adapter
```

负责在 Widget 范围内应用目标语言 Overlay。

具体 Hook：

```text
before_render
render_content
其他 Widget-level hook
```

尚未锁死。

必须在 v0.1.1 Lab 中实测。

---

## 4. 第一批 Widget

建议从最简单开始：

```text
Heading
Button
```

再增加：

```text
Text Editor
Image
Icon List
```

复杂 Widget 延后：

```text
Repeater
Accordion
Tabs
Loop Grid
Global Widget
Dynamic Tags
Shortcode
HTML Widget
```

---

## 5. Translation Unit Identity

目前不要假定 Elementor Widget ID 是永久 Identity。

建议区分：

```text
WEM Translation Unit
```

和：

```text
Elementor Locator
```

例如：

```text
Unit #8372
```

当前 Locator：

```text
post_id = 125
element_id = abc123
widget_type = heading
setting_path = title
```

如果 Widget 发生变化：

```text
element_id
source_hash
widget_type
setting_path
```

用于寻找 Migration Candidate。

---

## 6. 关键原则

> Widget ID 更适合作为 Locator，而不是 WEM 永久身份。

是否需要完整 Occurrence Table：

```text
v0.2+
```

再决定。

---

## 7. v0.1.1 要验证

```text
Widget 移动
Widget 复制
Widget 删除重建
Page Duplicate
Template Insert
Elementor Editor
Source Changed
Runtime Overlay
Disable Safety
```

验证完成后再形成 Elementor ADR 的最终版。
