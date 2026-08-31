=== Query ID Rules for Elementor Loop Grid ===
Contributors: site-development-team
Tags: elementor, loop-grid, query-id, acf, taxonomy
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 0.4.2
License: GPLv2 or later

Create reusable Elementor Loop Grid Query ID rules with taxonomy, ACF/custom-field filters, composition, and sorting.

== Description ==

This plugin adds a Loop Grid Filters screen in WordPress admin. Each published and enabled rule registers one Elementor Query ID.

Supported rule components:

* One or more target post types.
* Multiple taxonomy conditions with AND/OR relationships.
* Term-ID-only taxonomy filters with IN, AND, and NOT IN operators.
* Fixed ACF/custom-field values.
* A value read from the current page's ACF field or post meta.
* A value read from the current taxonomy archive term's ACF field or term meta.
* Standard WP_Query meta comparisons and serialized ACF array matching.
* Custom-field sorting with a fallback order.
* Safe merging with filters already configured by Elementor.
* Query Rule composition for TAB_ALL = common conditions AND (TAB_A OR TAB_B).
* Optional Polylang current-language isolation and taxonomy-term translation.
* Optional empty-result visibility that can hide the containing Elementor Nested Tabs button or a custom CSS target.

Only Elementor widgets whose internal name is `loop-grid` are modified.

== Installation ==

1. Download the latest installable ZIP from the GitHub Releases page.
2. In WordPress, go to Plugins > Add New Plugin > Upload Plugin.
3. Upload the ZIP and activate Query ID Rules for Elementor Loop Grid.
4. Go to Tools > Loop Grid Filters and create the first rule.

== Usage ==

1. Go to WordPress admin > Tools > Loop Grid Filters > Add Query Rule, or click Settings / Query Rules on the Plugins screen.
2. Give the rule a descriptive title.
3. Generate or enter a Query ID.
4. Optionally choose a target post type. Leave all unchecked to preserve Elementor's selection.
5. Add taxonomy and/or ACF/custom-field rows.
6. Optionally enable sorting and enter the site's actual custom-field name.
7. Empty-result visibility is enabled by default. Uncheck it to keep an empty target visible. Leave its selector empty to hide the containing Nested Tabs button automatically, or enter a CSS selector for another target.
8. Enable and publish the rule.
9. Copy the Query ID into Elementor Loop Grid > Query > Query ID.

Each Loop Grid used in a tab can use a different rule while reusing the same Loop Item template.

== Frequently Asked Questions ==

= Is Elementor Pro required? =

Yes. Rules can be configured while Elementor Pro is inactive, but Elementor Pro Loop Grid is required to run the matching Query ID.

= Is ACF required? =

No. ACF is optional. ACF field values are queried through standard WordPress post or term meta.

= Is Polylang required? =

No. When Polylang is active, the plugin can preserve the current language and translate configured taxonomy Term IDs.

= Does this add a visitor-facing filter widget? =

No. It modifies the server-side query for an Elementor Loop Grid using Query ID rules. It does not add dropdowns, checkboxes, or an AJAX filter interface.

= Does empty-result visibility follow AJAX filtering or pagination? =

No. It consumes Elementor's completed initial Loop Grid query and does not track later AJAX filter, load-more, or pagination transitions.

== TAB_ALL composition ==

1. Create, enable, and publish the TAB_A rule.
2. Create, enable, and publish the TAB_B rule.
3. Create the TAB_ALL rule.
4. In Query Rule composition, select TAB_A and TAB_B.
5. Put conditions shared by every tab, such as the main category, directly on TAB_ALL.

The result is:

`TAB_ALL common conditions AND (TAB_A results OR TAB_B results)`

The child rules may contain their own taxonomy and ACF/custom-field conditions. TAB_ALL controls the final ordering; child-rule ordering is ignored while building the union.

For performance, taxonomy-only child rules are compiled into one tax query, and ACF/meta-only child rules are compiled into one meta query. Mixed taxonomy-plus-ACF branches use the ID-union fallback because WordPress cannot safely express that cross-table OR relationship in one standard WP_Query. Fallback IDs are cached for the current PHP request using the result-affecting child-query arguments; pagination and sorting inputs overwritten by the ID-only child query do not fragment that cache. No persistent cache or invalidation system is added.

== ACF current-page example ==

To use the page containing the Loop Grid as the filter source:

* Target field: the field stored on the listed posts
* Value source: Current page ACF
* Source field: the field stored on the page containing the Loop Grid
* Compare: `=`

If several pages have the same source-field value, they produce the same filtered result.

== ACF current-archive-term example ==

For a Loop Grid whose Source is Current Query on a taxonomy archive:

* Target field: the field stored on the listed posts
* Value source: Current archive term ACF
* Source field: the field stored on the currently viewed category, tag, or custom-taxonomy term
* Compare: `=`

The source is the archive term selected by WordPress/Polylang, never the shared Elementor Archive Template document.

== Polylang ==

The same Query ID can be used on every translation of a page. When Polylang is active, the plugin:

* Preserves Polylang's current-language query condition.
* Recovers the language from the page containing the Loop Grid during Elementor REST/editor requests when needed.
* Translates configured taxonomy terms to the current language at query time.
* Keeps Query Rule records global and unavailable for Polylang translation.
* Leaves non-translated post types and taxonomies unchanged.

Store canonical/default-language Term IDs in taxonomy rows. Slugs, names, and term-taxonomy IDs are intentionally not accepted, which avoids ambiguity and keeps cross-language mapping predictable.

For ACF filters, use a language-neutral stored code or Current page ACF as the value source. Polylang cannot infer that two unrelated translated text strings represent the same ACF filter value.

== Notes ==

* ACF is optional. ACF values are ultimately queried through WordPress post meta.
* Use `ACF serialized contains` for a value stored inside an ACF Checkbox, Relationship, or other serialized array.
* Repeater subfields are not optimized for this type of query. A dedicated flat field or taxonomy is recommended for large datasets.
* Rules are loaded on the next request after being saved.
* Empty-result visibility uses Elementor's completed initial Loop Grid query. It does not rerun the query and does not track later AJAX filter or pagination updates.
* The small empty-result hide stylesheet stays in the page head to avoid a flash; the frontend script and runtime config load only when the request actually produces an empty configured Loop Grid.
* If an automatically hidden TAB is selected, the first available TAB in the same tab list is activated.

== Changelog ==

= 0.4.2 =
* Cache enabled rules, normalized rule configuration, and repeated current post/term field values for one PHP request only.
* Stop composition child queries and sorting work after an exact no-results sentinel while preserving extension hooks.
* Skip visibility-rule initialization for Loop Grids not using this plugin.
* Demand-load the empty-result footer script/config and deduplicate selector/target work while retaining the anti-FOUC stylesheet.
* Canonicalize ID-union child cache keys around the arguments that affect ID results.
* Add opt-in count-only ID-union diagnostics without default logging or sensitive values.

= 0.4.1 =
* Enable empty-result target hiding by default for new rules and rules without a saved empty-result setting.
* Preserve rules that were explicitly saved with empty-result target hiding disabled.

= 0.4.0 =
* Add an opt-in empty-result visibility panel to each Query Rule.
* Hide the containing Elementor Nested Tabs button automatically when the final Loop Grid query is empty.
* Allow a custom CSS selector to replace automatic TAB targeting.
* Keep existing rules disabled by default and avoid hiding elements in the Elementor editor.

= 0.3.6 =
* Make configured taxonomy rows fail closed when their taxonomy or Term IDs cannot produce a query clause, instead of silently falling back to the unfiltered Current Query.
* Preserve no-results branches while compiling taxonomy rule compositions, including an all-empty composition result.

= 0.3.5 =
* Move Enable this rule into its own sidebar panel below Publish.
* Keep Sorting as the next sidebar panel.
* Move Loop Grid post-type controls into a final Post attributes sidebar panel.
* Keep the main Elementor Query ID panel focused on Query ID controls only.

= 0.3.4 =
* Simplify taxonomy filters to accept Term IDs only.
* Remove the Term field selector and all slug, name, and term-taxonomy-ID lookup paths.
* Invalid or legacy non-ID taxonomy values now return no results instead of silently broadening a query.

= 0.3.3 =
* Move the Query Rules control screen under WordPress Tools.
* Add a Settings / Query Rules shortcut to the Plugins screen.

= 0.3.2 =
* Rebuilt the distributable ZIP with portable forward-slash entry paths for WordPress/Linux installations.

= 0.3.1 =
* Preserve an existing Polylang language query instead of replacing it with the language of a shared Elementor Archive Template.
* Make `pll_current_language()` authoritative and use only an explicit request/page ID as the REST/editor fallback.
* Keep Query Rule records global across languages.
* Add Current archive term ACF and term-meta value sources for Current Query archives.

= 0.3.0 =
* Added an optional Polylang adapter with no hard dependency.
* The same Query ID now inherits the current frontend or translated-page language.
* Translate canonical taxonomy Term IDs, term-taxonomy IDs, slugs, and names at runtime.
* Preserve REST, editor-preview, composition, and non-Polylang behavior.

= 0.2.1 =
* Compile taxonomy-only TAB compositions into one WP_Query.
* Compile ACF/meta-only TAB compositions into one WP_Query.
* Keep the ID-union fallback only for mixed or nested rule combinations.
* Add request-scoped fallback result caching without persistent-cache complexity.

= 0.2.0 =
* Added reusable Query Rule composition.
* TAB_ALL can include the union of TAB_A, TAB_B, and additional rules.
* Added circular-reference protection and mixed taxonomy/ACF rule support.

= 0.1.1 =
* Removed all site-specific default field names, terms, and post types.
* New rules preserve Elementor's post type and ordering unless explicitly overridden.

= 0.1.0 =
* Initial modular implementation.
