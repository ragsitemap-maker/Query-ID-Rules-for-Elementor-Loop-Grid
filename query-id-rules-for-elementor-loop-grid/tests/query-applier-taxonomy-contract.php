<?php

/**
 * Lightweight behavior tests for Query_Applier taxonomy contracts.
 *
 * Run with: php tests/query-applier-taxonomy-contract.php
 */

namespace {
	define( 'ABSPATH', __DIR__ );

	$GLOBALS['elgqr_test_taxonomies'] = array( 'category', 'topic' );
	$GLOBALS['elgqr_test_statuses']   = array();
	$GLOBALS['elgqr_child_queries']   = 0;
	$GLOBALS['elgqr_sort_filters']    = 0;
	$GLOBALS['elgqr_after_actions']   = 0;
	$GLOBALS['elgqr_diagnostics_enabled'] = false;

	function apply_filters( $hook, $value ) {
		if ( 'elgqr/enable_composition_diagnostics' === $hook ) {
			return $GLOBALS['elgqr_diagnostics_enabled'];
		}
		return $value;
	}

	function do_action( $hook ) {
		if ( 'elgqr/after_apply_rule' === $hook ) {
			++$GLOBALS['elgqr_after_actions'];
		}
	}

	function add_action() {}

	function remove_action() {}

	function add_filter( $hook ) {
		if ( 'posts_clauses' === $hook ) {
			++$GLOBALS['elgqr_sort_filters'];
		}
	}

	function remove_filter() {}

	function sanitize_key( $value ) {
		$value = strtolower( (string) $value );
		return preg_replace( '/[^a-z0-9_\-]/', '', $value );
	}

	function sanitize_text_field( $value ) {
		return trim( (string) $value );
	}

	function absint( $value ) {
		return abs( (int) $value );
	}

	function taxonomy_exists( $taxonomy ) {
		return in_array( $taxonomy, $GLOBALS['elgqr_test_taxonomies'], true );
	}

	function maybe_serialize( $value ) {
		return serialize( $value );
	}

	function wp_generate_uuid4() {
		return '00000000-0000-4000-8000-000000000000';
	}

	function get_post_status( $post_id ) {
		return isset( $GLOBALS['elgqr_test_statuses'][ $post_id ] )
			? $GLOBALS['elgqr_test_statuses'][ $post_id ]
			: 'publish';
	}

	class WP_Query {
		public $query_vars = array();
		public $posts = array();

		public function __construct( array $args = array() ) {
			$this->query_vars = $args;
			if ( ! empty( $args['elgqr_composition_child'] ) ) {
				++$GLOBALS['elgqr_child_queries'];
			}
		}

		public function get( $key ) {
			return array_key_exists( $key, $this->query_vars ) ? $this->query_vars[ $key ] : '';
		}

		public function set( $key, $value ) {
			$this->query_vars[ $key ] = $value;
		}
	}
}

namespace ELGQR {
	class Context_Resolver {
		private $post_id;

		public function __construct( $post_id = 0 ) {
			$this->post_id = $post_id;
		}

		public function current_post_id() {
			return $this->post_id;
		}

		public function set_current_post_id( $post_id ) {
			$this->post_id = $post_id;
		}

		public function value( $source, $source_key, $static_value ) {
			return 'static' === $source ? $static_value : null;
		}
	}

	class Rule_Repository {
		private $rules;

		public function __construct( array $rules = array() ) {
			$this->rules = $rules;
		}

		public function get( $post_id ) {
			return $this->rules[ $post_id ];
		}
	}

	class Polylang_Adapter {
		public function apply_current_language() {}

		public function translate_term_ids( $taxonomy, array $terms ) {
			return $terms;
		}
	}

	require_once dirname( __DIR__ ) . '/includes/class-query-applier.php';

	class Test_Widget {
		public function get_name() {
			return 'loop-grid';
		}
	}

	function test_rule( array $overrides = array() ) {
		return array_merge(
			array(
				'id'                => 1,
				'query_id'          => 'test_rule',
				'enabled'           => true,
				'post_types'        => array(),
				'tax_relation'      => 'AND',
				'tax_filters'       => array(),
				'meta_relation'     => 'AND',
				'meta_filters'      => array(),
				'included_rule_ids' => array(),
				'sort'              => array( 'enabled' => 0 ),
			),
			$overrides
		);
	}

	function tax_filter( $taxonomy, $terms, $operator = 'IN', $include_children = 1 ) {
		return array(
			'taxonomy'         => $taxonomy,
			'terms'            => $terms,
			'operator'         => $operator,
			'include_children' => $include_children,
		);
	}

	function assert_same( $expected, $actual, $message ) {
		if ( $expected !== $actual ) {
			throw new \RuntimeException(
				$message . "\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true )
			);
		}
	}

	function make_applier( array $rules = array() ) {
		return new Query_Applier(
			new Context_Resolver(),
			new Rule_Repository( $rules ),
			new Polylang_Adapter()
		);
	}

	function run_tests() {
		$widget = new Test_Widget();

		$existing = array(
			'relation' => 'AND',
			array(
				'taxonomy' => 'category',
				'field'    => 'term_id',
				'terms'    => array( 10 ),
			),
		);
		$query = new \WP_Query( array( 'tax_query' => $existing ) );
		$rule  = test_rule(
			array(
				'tax_filters' => array( tax_filter( 'topic', '20', 'IN', 1 ) ),
			)
		);
		make_applier()->apply( $query, $widget, $rule );
		$merged = $query->get( 'tax_query' );
		assert_same( 'AND', $merged['relation'], 'Current Query and Query ID must use an outer AND.' );
		assert_same( $existing, $merged[0], 'Current Query taxonomy constraints must be preserved.' );
		assert_same( array( 20 ), $merged[1][0]['terms'], 'Valid Query ID terms must be preserved.' );
		assert_same( 'IN', $merged[1][0]['operator'], 'The configured taxonomy operator must be preserved.' );
		assert_same( true, $merged[1][0]['include_children'], 'The Children setting must be preserved.' );

		$query = new \WP_Query( array( 'category_name' => 'category-a' ) );
		make_applier()->apply( $query, $widget, $rule );
		assert_same( 'category-a', $query->get( 'category_name' ), 'Current Query archive vars must be preserved.' );
		assert_same( array( 20 ), $query->get( 'tax_query' )[0]['terms'], 'The Query ID taxonomy clause must coexist with archive vars.' );

		$query = new \WP_Query();
		$rule  = test_rule( array( 'tax_filters' => array( tax_filter( 'missing_taxonomy', '20' ) ) ) );
		make_applier()->apply( $query, $widget, $rule );
		assert_same( array( 0 ), $query->get( 'post__in' ), 'An unavailable configured taxonomy must fail closed.' );

		$query = new \WP_Query();
		$rule  = test_rule( array( 'tax_filters' => array( tax_filter( 'category', '' ) ) ) );
		make_applier()->apply( $query, $widget, $rule );
		assert_same( array( 0 ), $query->get( 'post__in' ), 'Configured empty terms must fail closed.' );

		$query = new \WP_Query();
		$rule  = test_rule( array( 'tax_filters' => array( tax_filter( 'category', 'not-a-term-id' ) ) ) );
		make_applier()->apply( $query, $widget, $rule );
		assert_same( array( 0 ), $query->get( 'post__in' ), 'Invalid Term IDs must remain fail closed.' );

		$false_child_a = test_rule(
			array(
				'id'          => 2,
				'query_id'    => 'false_a',
				'tax_filters' => array( tax_filter( 'missing_taxonomy', '20' ) ),
			)
		);
		$false_child_b = test_rule(
			array(
				'id'          => 3,
				'query_id'    => 'false_b',
				'tax_filters' => array( tax_filter( 'category', '' ) ),
			)
		);
		$parent = test_rule(
			array(
				'id'                => 10,
				'query_id'          => 'parent_all_false',
				'included_rule_ids' => array( 2, 3 ),
			)
		);
		$query = new \WP_Query();
		make_applier( array( 2 => $false_child_a, 3 => $false_child_b ) )->apply( $query, $widget, $parent );
		assert_same( array( 0 ), $query->get( 'post__in' ), 'All-false taxonomy composition must return no results.' );

		$valid_child = test_rule(
			array(
				'id'          => 4,
				'query_id'    => 'valid_child',
				'tax_filters' => array( tax_filter( 'topic', '30' ) ),
			)
		);
		$parent = test_rule(
			array(
				'id'                => 11,
				'query_id'          => 'parent_partial',
				'included_rule_ids' => array( 2, 4 ),
			)
		);
		$query = new \WP_Query();
		make_applier( array( 2 => $false_child_a, 4 => $valid_child ) )->apply( $query, $widget, $parent );
		$compiled = $query->get( 'tax_query' );
		assert_same( 'OR', $compiled['relation'], 'Taxonomy composition must keep its OR union.' );
		assert_same( 2, count( $compiled ), 'A false child must be removed without creating a match-all branch.' );
		assert_same( array( 30 ), $compiled[0][0]['terms'], 'The valid composition child must remain.' );
		assert_same( 'compiled_tax', $query->get( 'elgqr_composition_mode' ), 'Partial valid composition must remain compiled.' );

		$mixed_child = test_rule(
			array(
				'id'           => 5,
				'query_id'     => 'mixed_child',
				'tax_filters'  => array( tax_filter( 'category', '10' ) ),
				'meta_filters' => array(
					array(
						'target_key' => 'segment',
						'source'     => 'static',
						'value'      => 'a',
						'compare'    => '=',
						'type'       => 'CHAR',
					),
				),
			)
		);
		$forced_empty = test_rule(
			array(
				'id'                => 12,
				'query_id'          => 'forced_empty',
				'tax_filters'       => array( tax_filter( 'missing_taxonomy', '10' ) ),
				'included_rule_ids' => array( 5 ),
				'sort'              => array(
					'enabled'  => 1,
					'key'      => 'priority',
					'type'     => 'NUMERIC',
					'order'    => 'ASC',
					'missing'  => 'last',
					'fallback' => 'date',
				),
			)
		);
		$GLOBALS['elgqr_child_queries'] = 0;
		$GLOBALS['elgqr_sort_filters']  = 0;
		$after_before = $GLOBALS['elgqr_after_actions'];
		$query = new \WP_Query();
		make_applier( array( 5 => $mixed_child ) )->apply( $query, $widget, $forced_empty );
		assert_same( array( 0 ), $query->get( 'post__in' ), 'A forced-empty sentinel must remain empty.' );
		assert_same( 0, $GLOBALS['elgqr_child_queries'], 'Forced-empty taxonomy must skip composition child queries.' );
		assert_same( 0, $GLOBALS['elgqr_sort_filters'], 'Forced-empty taxonomy must skip sorting SQL hooks.' );
		assert_same( $after_before + 1, $GLOBALS['elgqr_after_actions'], 'Forced-empty must still run the after hook exactly once.' );

		$cache_parent = test_rule(
			array(
				'id'                => 13,
				'query_id'          => 'cache_parent',
				'included_rule_ids' => array( 5 ),
			)
		);
		$quiet_applier = new Query_Applier( new Context_Resolver( 77 ), new Rule_Repository( array( 5 => $mixed_child ) ), new Polylang_Adapter() );
		$quiet_query   = new \WP_Query( array( 'post_type' => 'post' ) );
		$quiet_applier->apply( $quiet_query, $widget, $cache_parent );
		assert_same( '', $quiet_query->get( 'elgqr_composition_diagnostics' ), 'Production diagnostics must remain absent unless explicitly enabled.' );

		$resolver = new Context_Resolver( 77 );
		$applier  = new Query_Applier( $resolver, new Rule_Repository( array( 5 => $mixed_child ) ), new Polylang_Adapter() );
		$GLOBALS['elgqr_child_queries'] = 0;
		$GLOBALS['elgqr_diagnostics_enabled'] = true;

		$query = new \WP_Query( array( 'post_type' => 'post', 'paged' => 1, 'orderby' => 'date', 'order' => 'DESC' ) );
		$applier->apply( $query, $widget, $cache_parent );
		$diagnostics = $query->get( 'elgqr_composition_diagnostics' );
		assert_same( 1, $GLOBALS['elgqr_child_queries'], 'The first ID-union branch must execute one child query.' );
		assert_same( 1, $diagnostics['child_query_count'], 'Diagnostics must report the executed child query.' );
		assert_same( 1, $diagnostics['cache_misses'], 'Diagnostics must report the first cache miss.' );
		assert_same( 1, count( $diagnostics['child_id_counts'] ), 'Diagnostics must report each child ID count without exposing values.' );

		$query = new \WP_Query( array( 'post_type' => 'post', 'paged' => 9, 'offset' => 48, 'posts_per_page' => 6, 'orderby' => 'title', 'order' => 'ASC' ) );
		$applier->apply( $query, $widget, $cache_parent );
		$diagnostics = $query->get( 'elgqr_composition_diagnostics' );
		assert_same( 1, $GLOBALS['elgqr_child_queries'], 'Pagination and sorting inputs overwritten by the ID-only child query must reuse the cache.' );
		assert_same( 0, $diagnostics['child_query_count'], 'A cache hit must not be reported as an executed child query.' );
		assert_same( 1, $diagnostics['cache_hits'], 'Diagnostics must expose the safe cache reuse.' );

		$relevant_variants = array(
			array( 'post_type' => 'post', 'lang' => 'fr' ),
			array( 'post_type' => 'post', 'post__in' => array( 99 ) ),
			array( 'post_type' => 'post', 'tax_query' => array( array( 'taxonomy' => 'category', 'terms' => array( 99 ) ) ) ),
			array( 'post_type' => 'post', 'meta_query' => array( array( 'key' => 'segment', 'value' => 'b' ) ) ),
		);

		foreach ( $relevant_variants as $variant ) {
			$query = new \WP_Query( $variant );
			$applier->apply( $query, $widget, $cache_parent );
		}
		assert_same( 5, $GLOBALS['elgqr_child_queries'], 'Language, post intersection, taxonomy, and meta constraints must not collide in the cache.' );

		$resolver->set_current_post_id( 88 );
		$query = new \WP_Query( array( 'post_type' => 'post' ) );
		$applier->apply( $query, $widget, $cache_parent );
		assert_same( 6, $GLOBALS['elgqr_child_queries'], 'Different current-post context must not reuse an ID-union cache entry.' );
		$GLOBALS['elgqr_diagnostics_enabled'] = false;

		echo "Query_Applier taxonomy contract tests passed.\n";
	}

	try {
		run_tests();
	} catch ( \Throwable $error ) {
		fwrite( STDERR, $error->getMessage() . "\n" );
		exit( 1 );
	}
}
