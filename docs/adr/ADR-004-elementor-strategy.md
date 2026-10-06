# ADR-004：Elementor 策略

## Status

**Accepted for v0.1.1 Experimental Scope**

> 本 ADR 只锁定当前实验范围内已经验证的 Elementor 策略，不代表 Elementor 全功能生产支持。

## Decision

Elementor 不进入 v0.1.0 Core。

v0.1.1 采用：

```text
Adapter Registry
+
Read-only _elementor_data Source Discovery
+
Translation Repository
+
Structured Locator-scoped Runtime Overlay
```

禁止：

```text
修改 _elementor_data
把目标语言写回 Elementor JSON
全局 DOM Translator
按 Source Text 做全局字符串替换
把 Translation Provider 放进前台请求
```

---

## Runtime Decision

v0.1.1 实验实际验证过：

```text
before_render
elementor/widget/render_content
elementor/frontend/the_content
```

最终结论：

- `before_render` 受 Elementor render state / cache path 影响，不能作为唯一稳定 Runtime 层。
- `elementor/widget/render_content` 可用于部分 Widget 渲染路径和诊断，但不能假设所有实际缓存路径都会进入。
- `elementor/frontend/the_content` 在当前实验中作为稳定的最终 Overlay 层。

这里的 `the_content` 使用方式不是全局翻译器。

Overlay 必须先具备：

```text
object_id
element_id
widget_type
setting_path
Source Unit
Translation state
```

然后只定位目标 Elementor Widget 和该 Adapter 明确声明的 DOM target。

---

## Supported Fields

v0.1.1 当前已验证：

```text
heading.title
button.text
```

当前 DOM target：

```text
heading.title
→ .elementor-heading-title

button.text
→ .elementor-button-text
```

未知 Widget / Field：

```text
ignore
```

不猜测。

---

## Identity

当前 Context：

```text
elementor:{object_id}:{element_id}:{widget_type}:{setting_path}
```

实验已经证明：

```text
Context
→ 当前 Source Unit Identity

Hash
→ Source Version

State
→ Publication Boundary
```

但仍保留原判断：

```text
WEM Translation Unit
≠
Elementor Widget ID
```

`element_id` 当前是 Locator，不声明为永久业务身份。

---

## Cache Invalidation

缓存失效不属于 Adapter 自身的第三方 API 细节。

统一通过：

```text
WEM_ML_Cache_Invalidator
```

当前已验证自动触发：

```text
Translation change
Source change
Object Language State change
Translated Slug change
```

WP Rocket 环境使用 targeted URL purge，不执行 WEM 主动的 full-domain purge。

---

## Consequences

优点：

```text
Source JSON 保持干净
Translation Repository 可独立审计
Hash / stale 规则与 Core 一致
不同 Widget Field 可以复用同一 Repository
Runtime 不依赖全局 Source Text 搜索
缓存职责与 Runtime Overlay 分离
```

代价：

```text
每个 Widget / Field 需要明确 Adapter
每个 Runtime DOM target 需要实测
复杂 Widget 不能自动推断
Widget Identity Migration 尚未解决
```

---

## Open Questions

后续仍需单独实验：

```text
Widget 删除重建
Widget Duplicate
Page Duplicate
Template Insert
Global Widget
Dynamic Tags
Repeater
Occurrence / Migration Model
```

如果未来实验需要改变当前 Identity 或 Runtime 策略，应新增 ADR 或将本 ADR 标记为 Superseded，而不是直接抹掉本轮验证历史。

---

## Evidence

详细实验结果见：

- [Elementor Structured Translation 策略](../05-elementor-strategy.md)
- [v0.1.1 Elementor Structured Translation Lab 验收报告](../09-v0.1.1-elementor-validation.md)
