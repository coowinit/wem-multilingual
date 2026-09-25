# 02. 核心设计原则

## 1. Source Data Must Stay Clean

禁止：

```text
把译文写回 post_title
把语言标记写入 post_content
修改 Elementor _elementor_data 以保存目标语言内容
```

允许：

```text
WEM 自有表
Runtime Overlay
Adapter Mapping
```

---

## 2. Single Source 不是 Universal Single Source

Single Source 当前只作为：

```text
Page / Post
```

的核心方向。

其他对象必须独立评估。

---

## 3. Structured Translation First

如果系统知道：

```text
这是 post_title
这是 SEO Title
这是 Button Text
这是 URL
```

就必须使用结构化规则。

不要等最终 HTML 出现后再猜。

---

## 4. Adapter 描述数据，Core 执行行为

Adapter 负责：

```text
Where?
What?
Mode?
```

Core 负责：

```text
Repository
Hash
State
Route
SEO
Review
```

---

## 5. 字段模式

```text
Translate
Shared
Localize
Route
Ignore
```

### Translate

独立语言译文：

```text
Title
Description
SEO Title
```

### Shared

所有语言共享：

```text
SKU
Dimensions
Numeric Value
```

### Localize

允许语言覆盖：

```text
PDF
Image
External Market URL
```

### Route

内部对象 / URL：

```text
Post ID
Term ID
CTA Link
```

### Ignore

技术数据：

```text
Cache
Nonce
Internal Config
```

---

## 6. Discovery 与 Runtime 分离

### Source Language

```text
Discovery ON
Translation Lookup OFF / Optional
```

### Target Language

```text
Discovery OFF
Translation Lookup ON
```

避免：

```text
Spanish Text
→ 被再次登记成 English Source
```

---

## 7. Visitor Request 不调用 Translation Provider

访客请求必须：

```text
Local DB Lookup
```

缺译文时：

```text
Fallback Source
```

不能：

```text
现场调用 AI
```

---

## 8. Disable Safety

停用插件后：

```text
默认语言
URL
Elementor
WordPress Content
```

必须继续工作。

WEM 数据可以保留，但不能影响前台默认语言。
