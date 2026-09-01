=== Query ID Rules for Elementor Loop Grid ===
Tags: elementor, loop-grid, query-id, acf, taxonomy
Requires at least: 6.0
Requires PHP: 7.4
Tested up to: 7.1
Stable tag: 0.5.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create reusable Elementor Loop Grid Query ID rules with taxonomy, ACF/custom-field filters, composition, and sorting.

== Description ==

This plugin adds a Query ID Rules screen in WordPress admin. Each published and enabled rule registers one Elementor Query ID.

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
* Query ID Rule composition for TAB_ALL = common conditions AND (TAB_A OR TAB_B).
* Optional Polylang current-language isolation and taxonomy-term translation.
* Optional empty-result visibility that can hide the containing Elementor Nested Tabs button or a custom CSS target.

Only Elementor widgets whose internal name is `loop-grid` are modified.

== Installation ==

1. Download the latest installable ZIP from the GitHub Releases page.
2. In WordPress, go to Plugins > Add New Plugin > Upload Plugin.
3. Upload the ZIP and activate Query ID Rules for Elementor Loop Grid.
4. Go to Tools > Query ID Rules and create the first rule.

== Usage ==

1. Go to WordPress admin > Tools > Query ID Rules > Add Query ID Rule, or click Settings / Query ID Rules on the Plugins screen.
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
4. In Query ID Rule composition, select TAB_A and TAB_B.
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
* Keeps Query ID Rule records global and unavailable for Polylang translation.
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
* If an automatically hidden TAB is selected, the next available TAB in the same tab list is activated after Elementor finishes initializing. The readiness check is short-lived and bounded.
* Elementor is a trademark of Elementor Ltd. This independent plugin is not affiliated with or endorsed by Elementor Ltd.

== Changelog ==

= 0.5.3 =
* Switch from a hidden selected TAB to the next available TAB after Elementor Nested Tabs finishes initializing.
* Hide all empty TAB targets before choosing the fallback, including consecutive empty TABs and end-to-start wrapping.
* Use a bounded readiness retry without persistent observers, intervals, or event listeners.

= 0.5.0 =
* Rename the public plugin identity to Query ID Rules for Elementor Loop Grid.
* Align the plugin folder, main file, and Text Domain with the proposed WordPress.org slug `query-id-rules-for-elementor-loop-grid`.
* Add complete license metadata and separate the historical changelog into `changelog.txt`.
* Preserve existing Query ID Rule storage and runtime behavior.

= Earlier versions =

See `changelog.txt` for the complete version history.
