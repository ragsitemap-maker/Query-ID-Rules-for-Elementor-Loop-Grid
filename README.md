# Query ID Rules for Elementor Loop Grid

**English** | [Traditional Chinese](README.zh-TW.md)

Query ID Rules for Elementor Loop Grid is a WordPress plugin for creating and
reusing server-side Elementor Query ID rules without maintaining custom PHP
snippets for every Loop Grid.

Each published rule registers one Elementor Query ID. The rule can narrow the
existing Loop Grid query with taxonomy and ACF/custom-field conditions, combine
other rules, apply custom-field sorting, preserve the current Polylang language,
and control what happens when the final result is empty.

This repository publishes the plugin source, contract tests, documentation, and
installable release packages. It is not affiliated with or endorsed by
Elementor Ltd.

## What the Plugin Does

- Creates reusable Query IDs from **Tools → Loop Grid Filters**.
- Filters one or more post types by taxonomy Term IDs.
- Supports `IN`, `AND`, and `NOT IN` taxonomy operators.
- Filters by fixed post-meta values or values read from the current page or
  current taxonomy archive term.
- Supports common `WP_Query` meta comparisons and serialized ACF array matching.
- Combines reusable rules for expressions such as
  `COMMON AND (RULE_A OR RULE_B)`.
- Sorts by a custom field with a deterministic fallback order.
- Preserves Elementor's existing Current Query constraints instead of replacing
  them.
- Optionally maps configured taxonomy terms to the current Polylang language.
- Hides the containing Nested Tabs button, or another CSS target, when the
  completed initial Loop Grid query is empty.

Only Elementor widgets whose internal name is `loop-grid` are modified.

## What It Does Not Do

- It does not add a visitor-facing filter widget, dropdown, checkbox list, or
  AJAX filtering interface.
- It does not replace Elementor's normal Loop Grid query controls.
- It does not translate arbitrary ACF text values.
- It does not monitor later AJAX filter, load-more, or pagination transitions
  for empty-result visibility.
- It does not add a persistent query cache.

## Requirements

- WordPress 6.0 or later
- PHP 7.4 or later
- Elementor Pro with the Loop Grid widget
- ACF is optional
- Polylang is optional

Rules can still be configured while Elementor Pro is inactive, but they are
only applied when Elementor Pro runs the matching Loop Grid Query ID.

## Installation

1. Download the latest installable ZIP from
   [GitHub Releases](https://github.com/ragsitemap-maker/Query-ID-Rules-for-Elementor-Loop-Grid/releases).
2. In WordPress, open **Plugins → Add New Plugin → Upload Plugin**.
3. Upload the ZIP and activate **Query ID Rules for Elementor Loop Grid**.
4. Open **Tools → Loop Grid Filters**.

## Quick Start

1. Choose **Tools → Loop Grid Filters → Add Query Rule**.
2. Give the rule a descriptive title.
3. Generate or enter a Query ID.
4. Add the required taxonomy and/or ACF/custom-field conditions.
5. Optionally configure rule composition, sorting, or empty-result visibility.
6. Enable and publish the rule.
7. Copy its Query ID into **Elementor Loop Grid → Query → Query ID**.

## Rule Composition

For an “All” tab made from two reusable child rules:

```text
TAB_ALL common conditions AND (TAB_A results OR TAB_B results)
```

Put conditions shared by every tab directly on `TAB_ALL`, then include `TAB_A`
and `TAB_B` in its composition panel. The parent rule owns final sorting.

Taxonomy-only and meta-only branches are compiled into their corresponding
WordPress query structures. Mixed taxonomy-plus-meta branches use an ID-union
fallback with request-scoped caching.

## Empty Results

Empty-result visibility is enabled by default for new rules. Leave the CSS
selector empty to hide the Elementor Nested Tabs button whose panel contains the
empty Loop Grid, or enter a selector to hide another target. This consumes
Elementor's completed initial query and does not rerun it.

## Polylang

When Polylang is active, the plugin preserves the current query language and can
translate configured taxonomy Term IDs to the current language. Store canonical
or default-language Term IDs in rules. Non-translated post types and taxonomies
are left unchanged.

## Repository Layout

- `elementor-loop-grid-query-rules/` — WordPress plugin source and contract tests
- `README.md` — English project documentation
- `README.zh-TW.md` — Traditional Chinese project documentation
- `LICENSE` — GNU General Public License v2

GitHub release ZIP files contain only the production plugin files; tests and
repository documentation are excluded from the installable package.

## Development Validation

The contract fixtures can be run from the repository root:

```bash
php elementor-loop-grid-query-rules/tests/rule-repository-defaults-contract.php
php elementor-loop-grid-query-rules/tests/context-resolver-cache-contract.php
php elementor-loop-grid-query-rules/tests/query-applier-taxonomy-contract.php
php elementor-loop-grid-query-rules/tests/empty-result-visibility-contract.php
node elementor-loop-grid-query-rules/tests/frontend-empty-result-contract.js
```

The performance fixture accepts a scenario and grid count:

```bash
php elementor-loop-grid-query-rules/tests/performance-benchmark.php simple 26
```

## License

GPL-2.0-or-later
