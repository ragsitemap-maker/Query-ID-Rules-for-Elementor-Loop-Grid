# Query ID Rules for Elementor Loop Grid

[English](README.md) | **繁體中文**

Query ID Rules for Elementor Loop Grid 是一個 WordPress 外掛，讓管理員不必為
每個 Elementor Loop Grid 維護個別 PHP snippet，也能建立並重複使用伺服器端
Query ID 規則。

每一筆已發布且啟用的規則會註冊一個 Elementor Query ID。規則可以在保留原有
Loop Grid 查詢條件的前提下，加入 taxonomy、ACF／自訂欄位條件、規則組合、
自訂欄位排序、Polylang 語言隔離，以及空結果顯示控制。

本 repository 用於發布外掛原始碼、contract tests、說明文件及可安裝版本。
本專案並非 Elementor Ltd. 的官方產品，也未獲其背書。

## 外掛功能

- 從「工具 → Query ID Rules」建立可重複使用的 Query ID。
- 依 taxonomy Term ID 篩選一個或多個 post type。
- 支援 taxonomy 的 `IN`、`AND`、`NOT IN` operator。
- 支援固定 post meta 值，以及從目前頁面或目前 taxonomy archive term 讀取
  ACF／meta 值。
- 支援常用 `WP_Query` meta comparisons 與 serialized ACF array matching。
- 可組合既有規則，表達 `COMMON AND (RULE_A OR RULE_B)`。
- 可依自訂欄位排序，並設定穩定的 fallback order。
- 保留 Elementor Current Query 原有條件，不以外掛規則取代它。
- Polylang 啟用時，可將規則中的 taxonomy term 對應到目前語言。
- 初始 Loop Grid 最終結果為空時，可隱藏所在的 Nested Tabs 按鈕或指定的
  CSS target。

此外掛只會修改 Elementor 內部名稱為 `loop-grid` 的 widget。

## 不包含的功能

- 不提供訪客操作的 filter widget、下拉選單、checkbox list 或 AJAX filter UI。
- 不取代 Elementor 原生 Loop Grid query controls。
- 不自動翻譯任意 ACF 文字值。
- 空結果顯示控制不追蹤後續 AJAX filter、Load More 或分頁狀態。
- 不建立 persistent query cache。

## 系統需求

- WordPress 6.0 以上
- PHP 7.4 以上
- 具備 Loop Grid widget 的 Elementor Pro
- ACF 為選用相依
- Polylang 為選用相依

Elementor Pro 未啟用時仍可設定規則，但只有 Elementor Pro 執行對應 Loop Grid
Query ID 時才會套用規則。

## 安裝

1. 從 [GitHub Releases](https://github.com/ragsitemap-maker/Query-ID-Rules-for-Elementor-Loop-Grid/releases)
   下載最新可安裝 ZIP。
2. 在 WordPress 後台開啟「外掛 → 安裝外掛 → 上傳外掛」。
3. 上傳 ZIP 並啟用 **Query ID Rules for Elementor Loop Grid**。
4. 前往「工具 → Query ID Rules」。

## 快速開始

1. 前往「工具 → Query ID Rules → Add Query ID Rule」。
2. 輸入容易辨識的規則標題。
3. 產生或輸入 Query ID。
4. 加入需要的 taxonomy 與／或 ACF／自訂欄位條件。
5. 視需要設定規則組合、排序或空結果顯示控制。
6. 啟用並發布規則。
7. 將 Query ID 貼到「Elementor Loop Grid → Query → Query ID」。

## 規則組合

如果「全部」TAB 需要重用兩個子規則，可以設定為：

```text
TAB_ALL 共用條件 AND (TAB_A 結果 OR TAB_B 結果)
```

所有 TAB 共用的條件直接設定在 `TAB_ALL`，再於 composition panel 選取
`TAB_A` 與 `TAB_B`。最終排序由 parent rule 控制。

只有 taxonomy 或只有 meta 的分支會編譯為對應的 WordPress query 結構；混合
taxonomy 與 meta 的分支則使用 ID-union fallback 與單次 request cache。

## 空結果顯示控制

新規則預設啟用空結果顯示控制。CSS selector 留空時，會自動隱藏包含該空白
Loop Grid 的 Elementor Nested Tabs 按鈕；也可以輸入 selector 改為隱藏其他
目標。判斷直接使用 Elementor 已完成的初始查詢，不會重跑查詢。0.5.5 會短暫
等待含有自動隱藏空結果 TAB 的 Nested Tabs 完成初始化，再啟用同一組完整
WordPress 查詢總數最大的可用 TAB，即使目前 selected TAB 本身不是空集合也會
比較。最大值並列時選 DOM 順序第一個；數量缺漏或有歧義時，若目前 TAB 可用就
維持不動，只有目前 TAB 被隱藏時才退回下一個可用 TAB。readiness retry 有明確
上限：每100ms一次，最多100次或10秒，不留下常駐observer、interval或event listener。

## Polylang

Polylang 啟用時，外掛會保留 Current Query 的目前語言，並可將規則中設定的
taxonomy Term ID 對應到目前語言。規則請儲存 canonical／預設語言 Term ID；
未翻譯的 post type 與 taxonomy 不會被改動。

## Repository 結構

- `query-id-rules-for-elementor-loop-grid/`：WordPress 外掛原始碼與 contract tests
- `README.md`：英文專案說明
- `README.zh-TW.md`：繁體中文專案說明
- `LICENSE`：GNU General Public License v2

GitHub Release ZIP 只包含正式執行檔；測試與 repository 文件不會放入安裝包。

## 開發驗證

可從 repository root 執行 contract fixtures：

```bash
php query-id-rules-for-elementor-loop-grid/tests/rule-repository-defaults-contract.php
php query-id-rules-for-elementor-loop-grid/tests/context-resolver-cache-contract.php
php query-id-rules-for-elementor-loop-grid/tests/query-applier-taxonomy-contract.php
php query-id-rules-for-elementor-loop-grid/tests/empty-result-visibility-contract.php
node query-id-rules-for-elementor-loop-grid/tests/frontend-empty-result-contract.js
```

Performance fixture 接受 scenario 與 grid count：

```bash
php query-id-rules-for-elementor-loop-grid/tests/performance-benchmark.php simple 26
```

## 授權

GPL-2.0-or-later
