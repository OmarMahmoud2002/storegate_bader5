# AI Integration Report

**Project:** FindNext / Store Operations System (`Downloads/vscode`)  
**Date:** 2026-07-21  
**Scope:** Source-code analysis of controllers, models, views, MySQL schema, and Excel pipelines

---

## 1. Project Overview

### Project type

Internal **Arabic-first store operations & inventory system** for a retail/telecom branch environment (employee IDs like `XSER*`, product serials, custody/hand-over flows, staff sales Excel from regional reports). Not a public e-commerce storefront.

### Main features (from implementation)

| Area | Implementation |
|------|----------------|
| **Inventory** | `current_store_items` (~12k+ rows): serial control, barcode, price, low quantity, categories |
| **Product ops** | Search by serial/item code, cart, custody assign/return, delivery, low-stock page |
| **Shelves** | Shelf placement / move / remove (`tbl_shelves`, `tbl_shelves_items`) |
| **Operations log** | Audit trail of actions on serials (`tbl_operations`) |
| **Employees** | Users, admins, roles & granular permissions (`tbl_user`, `roles`, `permissions`) |
| **Employee sales** | Upload multi-sheet Excel → hierarchical sales calc (agent → supervisor → manager → area) via PhpSpreadsheet |
| **Commission Excel** | Import order-level amounts into `tbl_insert_excel` (twasel / electronic / jowy / quickplus) |
| **Cash lock** | Daily cash/span lock by employee (`tbl_user_lock`) |
| **Shipments / settlements / requests** | Shipping packs, settlements, return requests + notifications |
| **Schedules** | Employee timetable (`users_schedules`) |
| **Forms** | Free-form settlement/replacement templates (`free_form/`, `tbl_variables`) |

### Tech stack

| Layer | Technology |
|-------|------------|
| Backend | PHP **8.2**, **CodeIgniter 3** (MVC) |
| Database | **MySQL / MariaDB** (`mysqli`), DB name `findnext_findnextdb` |
| Excel | **PhpSpreadsheet** (`phpoffice/phpspreadsheet`) |
| Frontend | Server-rendered PHP views, jQuery, Bootstrap, custom admin CSS/JS |
| Auth | Session-based; admin vs employee; role permissions |
| Hosting posture | Classic LAMP-style app (shared hosting–compatible today) |

### Current architecture

```
Browser (Arabic admin UI)
    → index.php (CI front controller)
    → Controllers (admin/Dashboard, Products, Shelves, Other | User, Excel_import)
    → Models (Model_store_items, Model_admin, Model_employees_sales, …)
    → MySQL tables + uploaded XLSX under public/employees_sales/
```

- **Fat controllers:** especially `admin/Other.php` (employees, sales Excel, shipments, locks, forms, permissions).
- **Thin service start:** `application/services/EmployeeSalesService.php` for Excel column grouping.
- **No existing AI, API gateway, queues, or vector store.**
- Data is mostly **structured SQL** plus **periodic Excel files** (not document-heavy PDFs).

---

## 2. AI Opportunities

Ranked by **business value** (high → low) and **implementation complexity** (low → high).

| # | Feature | Business value | Complexity | Notes |
|---|---------|----------------|------------|-------|
| 1 | **Ops Q&A assistant** (inventory, custody, serials, low stock, locks) | **Very high** | **Medium** | Reuses existing models; Arabic NL over daily ops |
| 2 | **Employee sales insights** (summarize performance, hierarchy, TGT vs actual from uploaded Excel) | **Very high** | **Medium–high** | Heavy Excel logic already exists; AI explains/aggregates |
| 3 | **Smart inventory alerts** (reorder suggestions, custody age, serial anomalies) | **High** | **Medium** | Rules + LLM narrative on top of `get_low_products()` / custody queries |
| 4 | **Natural-language product search** (description typos, Arabic synonyms) | **Medium–high** | **Low–medium** | Complements serial/code search in `Products::search` |
| 5 | **Excel import assistant** (map columns, detect bad rows, explain import failures) | **Medium** | **Medium** | Helps `Excel_import` + sales upload pipelines |
| 6 | **Auto-fill settlement/replacement forms** | **Medium** | **Low–medium** | Uses custody + user + form variables |
| 7 | **Operations log summarizer** (“what happened to serial X?”) | **Medium** | **Low** | Read `tbl_operations` + LLM summary |
| 8 | **Schedule optimizer** | **Low–medium** | **High** | Sparse `users_schedules` data; lower ROI now |
| 9 | **Full multi-agent warehouse robot** | **Low (for this stack)** | **Very high** | Overkill vs CI3 monolith |

---

## 3. Best Recommendation

### Single best AI feature

**Arabic Operations Assistant (inventory + employee sales Q&A)**  
A permission-aware chat panel in the admin UI that answers operational questions by **calling existing backend functions**, then explaining results in Arabic.

### Why highest ROI

1. **Pain is navigation, not missing data.** Managers already have products, custody, sales Excel, locks, and ops logs—but spread across many screens and multi-thousand-row Excel files.
2. **Data is structured.** Function calling against MySQL/Excel parsers is more accurate and cheaper than pure RAG.
3. **Fits current code.** Models already expose queries (`Model_store_items`, `Model_admin`, `Model_custody`, employee sales search in `Other.php`). AI becomes a thin orchestration layer, not a rewrite.
4. **Arabic UI ready.** Sidebar, flash messages, and employee sales screens are Arabic; NL answers match user language.
5. **Measurable time savings.** Example queries:
   - «كم كمية الصنف `ZOWEE-5GROUTER…` المتبقية؟»
   - «من عنده عهدة السيريال `865658072372138`؟»
   - «لخص مبيعات الموظف XSER3141 من آخر ملف مبيعات»
   - «اعرض المنتجات تحت الحد الأدنى»

---

## 4. Implementation Approach

### Recommended architecture: **Hybrid (Function Calling + Chatbot UI)**

| Component | Role |
|-----------|------|
| **Chatbot UI** | Floating panel in admin shell (sidebar layout already exists) |
| **Function calling** | Primary: LLM selects tools → PHP executes → LLM formats answer |
| **Light context** | Optional short RAG only for internal SOPs / form instructions later |
| **Not primary** | Full agent swarm, unsupervised write tools, or pure RAG over raw Excel |

### Why this fits

- Inventory and users live in **MySQL** → tools map cleanly to models.
- Employee sales live in **uploaded XLSX** → tools wrap existing `process_employee_sales_search` / `EmployeeSalesService` logic (read-only first).
- CodeIgniter is request/response; function-calling tools as controller endpoints fit naturally.
- Shared hosting can call an external LLM API without local GPU.

```
Admin chat (Arabic)
    → POST /admin/ai/chat
    → AiService (system prompt + tools schema)
    → SpaceXAI (xAI) chat/completions with tools
    → Tool handlers → Models / Excel service (read-only MVP)
    → Final Arabic answer + optional table payload for UI
```

---

## 5. Data Sources

| Source | Location | AI use |
|--------|----------|--------|
| **Inventory** | `current_store_items` | Stock levels, serial lookup, category search, price, low stock |
| **Custody** | `tbl_user_custody` | Who holds what, order numbers, open custody |
| **Users / employees** | `tbl_user` | Names, employee IDs, job numbers, roles |
| **Requests** | `tbl_requests` | Pending returns |
| **Operations** | `tbl_operations` | Serial history narrative |
| **Sales (branch)** | `tbl_sales`, `tbl_sold` | Period sales lists |
| **Commission import** | `tbl_insert_excel` | Order amounts by channel (twasel, etc.) |
| **Employee sales Excel** | `public/employees_sales/*.xlsx` + `tbl_employees_sales` | Performance, hierarchy, TGT columns |
| **Cash lock** | `tbl_user_lock` | Daily cash/span totals |
| **Shipments** | `tbl_shipments` | Pack tracking |
| **Shelves** | `tbl_shelves*` | Location of items |
| **Settings / forms** | `tbl_settings`, `tbl_variables` | Form labels, uploaded file path |
| **Schedules** | `users_schedules` | Who is on leave/store/shift |
| **Permissions** | `permissions`, `role_permissions` | Gate which tools a user may invoke |
| **PDFs** | Not a core system input | Out of scope for MVP |
| **Product images** | `tbl_products_images`, `public/img` | Optional vision later |

**Privacy note:** Employee national IDs appear in sales Excel columns—tools must respect roles and never expose IDs to unauthorized users.

---

## 6. Technical Design

### High-level architecture

```
┌─────────────────────────────────────────────┐
│  Admin UI (view_admin_sidebar + chat widget) │
└────────────────────┬────────────────────────┘
                     │ JSON chat API
┌────────────────────▼────────────────────────┐
│  Controller: admin/Ai.php                    │
│  - Auth + check_permission('ai.chat')        │
│  - Rate limit / audit log                    │
└────────────────────┬────────────────────────┘
                     │
┌────────────────────▼────────────────────────┐
│  Library/Service: AiAssistantService         │
│  - Build system prompt (Arabic ops context)  │
│  - Register tools + execute loop (max N)     │
│  - Call SpaceXAI (OpenAI-compatible API)     │
└───────┬──────────────────────┬──────────────┘
        │                      │
        ▼                      ▼
  Model_* (MySQL)     EmployeeSalesService / Excel IO
  (read-only MVP)     (reuse process_employee_sales_search data)
```

### Required backend changes

1. **Config**  
   - `application/config/ai.php` (or env): `XAI_API_KEY`, model name, max tokens, timeout.  
   - Never commit keys; keep server-side only.

2. **New files**  
   - `application/controllers/admin/Ai.php` — chat endpoint.  
   - `application/libraries/Ai_client.php` — HTTP client to `https://api.x.ai/v1`.  
   - `application/services/AiAssistantService.php` — tool loop.  
   - `application/services/AiTools/` — one class per tool domain.

3. **MVP tools (read-only)**  

| Tool | Backed by |
|------|-----------|
| `search_products` | `Model_store_items` (name/code/category) |
| `get_product_by_serial` | existing product serial lookup |
| `get_low_stock` | `get_low_products()` |
| `get_employee_custody` | `Model_custody` / `Model_admin` |
| `get_operations_for_serial` | `Model_operations` |
| `get_employee_sales_summary` | Wrap sales Excel search result (reuse session/file path from `tbl_variables`) |
| `get_cash_locks` | `tbl_user_lock` by date/user |

4. **Permissions**  
   - New keys: `ai.chat`, optionally per-tool (e.g. `ai.sales`, `ai.inventory`).  
   - Mirror existing `MY_Controller::check_permission`.

5. **Audit**  
   - Table `ai_chat_logs` (user_id, prompt, tools_called, response, tokens, created_at).

6. **Writes (Phase 2 only)**  
   - Explicit confirmations; never auto-delete stock or custody.

### Required frontend changes

- Floating chat button on admin pages (reuse `.admin-ui-shell` patterns).
- Simple message list + input; render tool results as compact Arabic tables when JSON includes rows.
- Loading state (Excel tools can be slow—show “جاري قراءة ملف المبيعات…”).
- No API key in JS.

### APIs and models

| Item | Recommendation |
|------|----------------|
| Provider | **SpaceXAI** (xAI), OpenAI-compatible |
| Base URL | `https://api.x.ai/v1` |
| Auth | `XAI_API_KEY` |
| Model (default) | `grok-4.5` (confirm current aliases on [docs.x.ai](https://docs.x.ai)) |
| API style | Chat completions **with tools/function calling** |
| PHP HTTP | cURL or Guzzle; no need for Python service in MVP |
| Streaming | Optional Phase 2 (SSE) for better UX |

---

## 7. Implementation Roadmap

### Phase 1 — MVP (2–3 weeks)

- Config + `Ai_client` + `admin/Ai` chat endpoint.
- Tools: product search, serial lookup, low stock, custody, operations history.
- Admin chat widget (Arabic).
- Permission `ai.chat` + basic rate limit (e.g. 30 req/user/hour).
- Logging table; **read-only** tools only.
- Success metric: manager finds stock/custody answer without opening 3+ pages.

### Phase 2 (3–5 weeks)

- Employee sales tool: reuse Excel upload path + search by emp id/name.
- Summaries: top/bottom performers for current file, simple TGT vs actual explanation.
- Cash lock + shipment status tools.
- Citation in UI (“المصدر: current_store_items / ملف يناير 2026”).
- Soft alerts: “3 أجهزة عهدة لأكثر من 14 يوم”.

### Future improvements

- Column-mapping help for Excel imports.
- Optional RAG over short internal manuals (forms SOPs).
- Streaming answers; mobile-friendly employee self-service Q&A (custody only).
- Predictive reorder from historical `tbl_operations` / sales (needs cleaner time series).
- Vision: identify product from photo against `tbl_products_images` (low priority).

---

## 8. Cost Estimate

### API costs (order of magnitude)

Assumptions: ~15 admin users, ~20 AI questions/day average, ~1.5k input + 0.5k output tokens/question with 1–2 tool rounds.

| Scale | Rough monthly tokens | Rough monthly API cost* |
|-------|----------------------|-------------------------|
| Light pilot | ~1–2M | **$5–25** |
| Active branch | ~5–10M | **$30–120** |
| Multi-branch growth | 20M+ | **$150–400+** |

\*Depends on live SpaceXAI/xAI pricing; tool results should be **trimmed** (top N rows) to control cost.

### Infrastructure

| Need | MVP | Scale-up |
|------|-----|----------|
| LLM hosting | External API only | Same |
| Vector DB | Not required | Optional later |
| PHP memory | Already raised for Excel (`512M` in Dashboard) | Keep for sales tools |
| Redis/queue | Optional | Recommended if many concurrent Excel tools |
| SSL + secrets | Required for API key | Required |

### Shared hosting vs VPS

| | Shared hosting | VPS |
|--|----------------|-----|
| MVP chat + MySQL tools | **Usually sufficient** if outbound HTTPS allowed | Preferred |
| Heavy concurrent Excel parse | Risk of timeouts/memory kill | **Recommended** |
| Production multi-user | Possible with strict timeouts | **Safer** |

**Recommendation:** Start MVP on current hosting if outbound HTTPS works; move to a small **VPS** (2 vCPU / 4 GB) if Excel-backed tools or concurrent admins cause timeouts. No local GPU required.

---

## 9. Final Recommendation

**Build an Arabic Operations Assistant using Hybrid Function Calling + Chatbot UI, powered by SpaceXAI (`grok-4.5` via `https://api.x.ai/v1`), integrated as a thin CodeIgniter service on top of existing models—not a separate AI platform.**

### Why this is the best fit

1. Matches how the system actually works: **SQL inventory + Excel staff sales + custody workflows**.  
2. Highest day-to-day value for admins without rewriting the CI3 monolith.  
3. Production-ready pattern: **read-only tools first**, permissions, audit logs, controlled costs.  
4. Defers complex RAG/agents until structured tools prove value.  
5. Shared hosting can pilot; VPS only if Excel concurrency becomes the bottleneck.

### Suggested first sprint deliverable

> Permission-gated admin chat that answers: product stock/serial, low stock list, employee custody, and serial operation history—grounded only in live MySQL data via function calls.

---

*Generated from analysis of `application/controllers`, `application/models`, `application/services`, `backup.sql` schema, and Excel sales pipelines under `public/employees_sales/`.*
