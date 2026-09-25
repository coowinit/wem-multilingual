# 04. Translation Repository

## 1. 核心模型

```text
Source Unit
    │
    │ 1:N
    ▼
Translation
    │
    ├─ es
    ├─ de
    └─ fr
```

---

## 2. Source Unit

当前概念字段：

```text
id
source_language
source_text
source_hash
normalized_hash

context_type
context_key

object_type
object_id
field_key

state
created_at
updated_at
```

---

## 3. Translation

```text
id
string_id
target_language

translated_text
translated_from_hash

status
origin

provider
provider_model

reviewed_by
reviewed_at

created_at
updated_at
```

---

## 4. Context 与 Hash 的分工

核心口诀：

> Context 定身份，Hash 定版本。

例如：

```text
context_key
post:125:title
```

原文：

```text
Composite Decking
```

后来：

```text
Premium Composite Decking
```

仍然是同一个 Translation Unit。

只是：

```text
source_hash
AAA → BBB
```

---

## 5. Source Changed

Translation：

```text
translated_from_hash = AAA
```

Source：

```text
source_hash = BBB
```

那么：

```text
AAA != BBB
```

即可推导：

```text
Source Changed / Stale
```

不必破坏旧译文。

---

## 6. Status 与 Origin 分离

### Status

```text
needs_review
reviewed
```

### Origin

```text
manual
machine
memory
import
```

Provider：

```text
openai
deepl
gemini
libretranslate
```

例如：

```text
origin = machine
provider = openai
status = reviewed
```

表示：

> AI 生成，但已经人工审核。

---

## 7. Translation Memory

v0.1.0 不单独建立 TM 表。

以后：

```text
Reviewed Translation
```

即可自然成为 Exact Translation Memory。

例如：

```text
Request a Quote
→ Solicitar presupuesto
```

以后相同 Source 可复用。

Fuzzy Match 延后。

---

## 8. Glossary

独立于 TM。

例如：

```text
COOWIN
→ Never Translate

WPC
→ Never Translate

Composite Decking
→ Tarima compuesta
```

Glossary 不进入 v0.1.0 Core。
