<?php

/**
 * Lightweight incremental benchmark for the plugin-owned query work.
 *
 * Run one isolated sample with:
 * php tests/performance-benchmark.php <scenario> <grid-count>
 *
 * Scenarios: disabled, enabled_no_match, simple, compiled, id_union,
 * empty_result.
 */

namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'ELGQR_URL', 'https://example.test/wp-content/plugins/query-id-rules-for-elementor-loop-grid/' );
	define( 'ELGQR_VERSION', 'benchmark' );

	$GLOBALS['elgqr_benchmark'] = array();
	$GLOBALS['elgqr_rules']     = array();
	$GLOBALS['elgqr_actions']   = array();
	$GLOBALS['elgqr_filters']   = array();

	function elgqr_reset_benchmark_counters() {
		$GLOBALS['elgqr_benchmark'] = array(
			'all_enabled_calls'  => 0,
			'repository_reads'   => 0,
			'get_calls'          => 0,
			'normalized_rules'   => 0,
			'context_reads'      => 0,
			'child_queries'      => 0,
			'child_ids'          => 0,
			'union_ids'          => 0,
			'after_apply_calls'  => 0,
			'diagnostic_child_queries' => 0,
			'diagnostic_cache_hits'    => 0,
			'diagnostic_cache_misses'  => 0,
			'enqueued_styles'    => array(),
			'enqueued_scripts'   => array(),
			'registered_styles'  => array(),
			'registered_scripts' => array(),
			'inline_scripts'     => array(),
		);
	}

	function add_action( $hook, $callback, $priority = 10 ) {
		$GLOBALS['elgqr_actions'][ $hook ][ $priority ][] = $callback;
	}

	function remove_action( $hook, $callback, $priority = 10 ) {
		if ( empty( $GLOBALS['elgqr_actions'][ $hook ][ $priority ] ) ) {
			return;
		}

		foreach ( $GLOBALS['elgqr_actions'][ $hook ][ $priority ] as $index => $registered ) {
			if ( $registered === $callback ) {
				unset( $GLOBALS['elgqr_actions'][ $hook ][ $priority ][ $index ] );
			}
		}
	}

	function do_action( $hook ) {
		$args = array_slice( func_get_args(), 1 );

		if ( 'elgqr/after_apply_rule' === $hook ) {
			++$GLOBALS['elgqr_benchmark']['after_apply_calls'];
		}

		if ( empty( $GLOBALS['elgqr_actions'][ $hook ] ) ) {
			return;
		}

		ksort( $GLOBALS['elgqr_actions'][ $hook ] );
		foreach ( $GLOBALS['elgqr_actions'][ $hook ] as $callbacks ) {
			foreach ( array_values( $callbacks ) as $callback ) {
				call_user_func_array( $callback, $args );
			}
		}
	}

	function add_filter( $hook, $callback, $priority = 10 ) {
		$GLOBALS['elgqr_filters'][ $hook ][ $priority ][] = $callback;
	}

	function remove_filter( $hook, $callback, $priority = 10 ) {
		if ( empty( $GLOBALS['elgqr_filters'][ $hook ][ $priority ] ) ) {
			return;
		}

		foreach ( $GLOBALS['elgqr_filters'][ $hook ][ $priority ] as $index => $registered ) {
			if ( $registered === $callback ) {
				unset( $GLOBALS['elgqr_filters'][ $hook ][ $priority ][ $index ] );
			}
		}
	}

	function apply_filters( $hook, $value ) {
		$args = array_slice( func_get_args(), 2 );

		if ( empty( $GLOBALS['elgqr_filters'][ $hook ] ) ) {
			return $value;
		}

		ksort( $GLOBALS['elgqr_filters'][ $hook ] );
		foreach ( $GLOBALS['elgqr_filters'][ $hook ] as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$value = call_user_func_array( $callback, array_merge( array( $value ), $args ) );
			}
		}

		return $value;
	}

	function get_posts( $args ) {
		if ( isset( $args['post_type'] ) && 'elgqr_rule' === $args['post_type'] ) {
			++$GLOBALS['elgqr_benchmark']['repository_reads'];
			return array_map(
				static function ( $id ) {
					return (object) array( 'ID' => $id );
				},
				array_keys( $GLOBALS['elgqr_rules'] )
			);
		}

		return array();
	}

	function get_post_meta( $post_id, $key, $single = false ) {
		if ( '_elgqr_config' === $key ) {
			++$GLOBALS['elgqr_benchmark']['normalized_rules'];
			return isset( $GLOBALS['elgqr_rules'][ $post_id ] ) ? $GLOBALS['elgqr_rules'][ $post_id ] : array();
		}

		if ( '_elgqr_query_id' === $key ) {
			return isset( $GLOBALS['elgqr_rules'][ $post_id ]['query_id'] ) ? $GLOBALS['elgqr_rules'][ $post_id ]['query_id'] : '';
		}

		if ( '_elgqr_enabled' === $key ) {
			return ! empty( $GLOBALS['elgqr_rules'][ $post_id ]['enabled'] ) ? '1' : '0';
		}

		++$GLOBALS['elgqr_benchmark']['context_reads'];
		return 'segment-a';
	}

	function get_term_meta() {
		++$GLOBALS['elgqr_benchmark']['context_reads'];
		return 'segment-a';
	}

	function get_post_status( $post_id ) {
		return isset( $GLOBALS['elgqr_rules'][ $post_id ] ) ? 'publish' : false;
	}

	function wp_parse_args( $args, $defaults = array() ) {
		return array_merge( $defaults, is_array( $args ) ? $args : array() );
	}

	function sanitize_key( $value ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
	}

	function sanitize_text_field( $value ) {
		return trim( (string) $value );
	}

	function absint( $value ) {
		return abs( (int) $value );
	}

	function taxonomy_exists( $taxonomy ) {
		return in_array( $taxonomy, array( 'category', 'topic' ), true );
	}

	function maybe_serialize( $value ) {
		return serialize( $value );
	}

	function wp_generate_uuid4() {
		static $index = 0;
		++$index;
		return sprintf( '00000000-0000-4000-8000-%012d', $index );
	}

	function is_singular() {
		return true;
	}

	function get_queried_object_id() {
		return 77;
	}

	function get_queried_object() {
		return null;
	}

	function wp_doing_ajax() {
		return false;
	}

	function wp_json_encode( $value, $flags = 0 ) {
		return json_encode( $value, $flags );
	}

	function wp_register_style( $handle, $src = '', $dependencies = array(), $version = false ) {
		$GLOBALS['elgqr_benchmark']['registered_styles'][ $handle ] = compact( 'src', 'dependencies', 'version' );
	}

	function wp_enqueue_style( $handle, $src = '', $dependencies = array(), $version = false ) {
		$GLOBALS['elgqr_benchmark']['enqueued_styles'][ $handle ] = compact( 'src', 'dependencies', 'version' );
	}

	function wp_register_script( $handle, $src = '', $dependencies = array(), $version = false, $in_footer = false ) {
		$GLOBALS['elgqr_benchmark']['registered_scripts'][ $handle ] = compact( 'src', 'dependencies', 'version', 'in_footer' );
	}

	function wp_enqueue_script( $handle, $src = '', $dependencies = array(), $version = false, $in_footer = false ) {
		$GLOBALS['elgqr_benchmark']['enqueued_scripts'][ $handle ] = compact( 'src', 'dependencies', 'version', 'in_footer' );
	}

	function wp_script_is( $handle, $status = 'enqueued' ) {
		return 'registered' === $status
			? isset( $GLOBALS['elgqr_benchmark']['registered_scripts'][ $handle ] )
			: isset( $GLOBALS['elgqr_benchmark']['enqueued_scripts'][ $handle ] );
	}

	function wp_add_inline_script( $handle, $script, $position = 'after' ) {
		$GLOBALS['elgqr_benchmark']['inline_scripts'][] = compact( 'handle', 'script', 'position' );
		return true;
	}

	class WP_REST_Request {}

	class WP_Term {
		public $taxonomy;
		public $term_id;
	}

	class WP_Query {
		public $query_vars = array();
		public $posts      = array();

		public function __construct( array $args = array() ) {
			$this->query_vars = $args;
			do_action( 'pre_get_posts', $this );

			if ( $this->get( 'elgqr_composition_child' ) ) {
				++$GLOBALS['elgqr_benchmark']['child_queries'];
				$rule_id     = absint( $this->get( 'elgqr_rule_id' ) );
				$this->posts = array( $rule_id * 10 + 1, $rule_id * 10 + 2, $rule_id * 10 + 3 );
				$GLOBALS['elgqr_benchmark']['child_ids'] += count( $this->posts );
			}
		}

		public function get( $key ) {
			return array_key_exists( $key, $this->query_vars ) ? $this->query_vars[ $key ] : '';
		}

		public function set( $key, $value ) {
			$this->query_vars[ $key ] = $value;
		}

		public function get_queried_object() {
			return null;
		}
	}
}

namespace ELGQR {
	require_once dirname( __DIR__ ) . '/includes/class-rule-repository.php';
	require_once dirname( __DIR__ ) . '/includes/class-context-resolver.php';

	class Polylang_Adapter {
		public function __construct( $context_resolver = null ) {}

		public function apply_current_language( $query, $widget ) {}

		public function translate_term_ids( $taxonomy, array $terms ) {
			return $terms;
		}
	}

	require_once dirname( __DIR__ ) . '/includes/class-query-applier.php';
	require_once dirname( __DIR__ ) . '/includes/class-empty-result-visibility.php';

	class Benchmark_Repository extends Rule_Repository {
		public function all_enabled() {
			++$GLOBALS['elgqr_benchmark']['all_enabled_calls'];
			return parent::all_enabled();
		}

		public function get( $post_id ) {
			++$GLOBALS['elgqr_benchmark']['get_calls'];
			return parent::get( $post_id );
		}
	}

	class Benchmark_Widget {
		private $id;

		public function __construct( $id ) {
			$this->id = $id;
		}

		public function get_name() {
			return 'loop-grid';
		}

		public function get_id() {
			return $this->id;
		}
	}

	function benchmark_rule( $id, array $overrides = array() ) {
		return array_merge(
			array(
				'id'                => $id,
				'query_id'          => 'rule_' . $id,
				'enabled'           => true,
				'post_types'        => array( 'post' ),
				'tax_relation'      => 'AND',
				'tax_filters'       => array(),
				'meta_relation'     => 'AND',
				'meta_filters'      => array(),
				'included_rule_ids' => array(),
				'sort'              => array( 'enabled' => 0 ),
				'empty_result'      => array( 'enabled' => 1, 'target_selector' => '' ),
			),
			$overrides
		);
	}

	function benchmark_rules() {
		$dynamic_meta = array(
			'target_key'     => 'segment',
			'source'         => 'current_post_meta',
			'source_key'     => 'source_segment',
			'value'          => '',
			'compare'        => '=',
			'type'           => 'CHAR',
			'empty_behavior' => 'no_results',
		);
		$static_meta = array_merge( $dynamic_meta, array( 'source' => 'static', 'source_key' => '', 'value' => 'segment-a' ) );
		$category    = array( 'taxonomy' => 'category', 'terms' => '10', 'operator' => 'IN', 'include_children' => 1 );
		$topic       = array( 'taxonomy' => 'topic', 'terms' => '20', 'operator' => 'IN', 'include_children' => 1 );

		return array(
			1  => benchmark_rule( 1, array( 'meta_filters' => array( $dynamic_meta ) ) ),
			2  => benchmark_rule( 2, array( 'tax_filters' => array( $category ) ) ),
			3  => benchmark_rule( 3, array( 'tax_filters' => array( $topic ) ) ),
			4  => benchmark_rule( 4, array( 'tax_filters' => array( $category ), 'meta_filters' => array( $static_meta ) ) ),
			5  => benchmark_rule( 5, array( 'tax_filters' => array( $topic ), 'meta_filters' => array( $static_meta ) ) ),
			10 => benchmark_rule( 10, array( 'included_rule_ids' => array( 2, 3 ) ) ),
			11 => benchmark_rule( 11, array( 'included_rule_ids' => array( 4, 5 ) ) ),
		);
	}

	function benchmark_asset_bytes() {
		$bytes = 0;
		$root  = dirname( __DIR__ );

		if ( isset( $GLOBALS['elgqr_benchmark']['enqueued_styles']['elgqr-frontend'] ) ) {
			$bytes += filesize( $root . '/assets/frontend.css' );
		}
		if ( isset( $GLOBALS['elgqr_benchmark']['enqueued_scripts']['elgqr-frontend'] ) ) {
			$bytes += filesize( $root . '/assets/frontend.js' );
		}
		foreach ( $GLOBALS['elgqr_benchmark']['inline_scripts'] as $inline ) {
			$bytes += strlen( $inline['script'] );
		}

		return $bytes;
	}

	function run_benchmark( $scenario, $grid_count ) {
		elgqr_reset_benchmark_counters();
		$GLOBALS['elgqr_rules']   = benchmark_rules();
		$GLOBALS['elgqr_actions'] = array();
		$GLOBALS['elgqr_filters'] = array();

		$memory_start = memory_get_usage( false );
		$time_start   = microtime( true );

		if ( 'disabled' !== $scenario ) {
			$repository = new Benchmark_Repository();
			$resolver   = new Context_Resolver();
			$applier    = new Query_Applier( $resolver, $repository, new Polylang_Adapter( $resolver ) );
			$visibility = new Empty_Result_Visibility( $repository );

			$repository->all_enabled();
			$repository->all_enabled();
			$visibility->enqueue_assets();

			$rule_id = array(
				'simple'       => 1,
				'compiled'     => 10,
				'id_union'     => 11,
				'empty_result' => 1,
			);

			if ( 'id_union' === $scenario ) {
				add_filter(
					'elgqr/enable_composition_diagnostics',
					static function () {
						return true;
					}
				);
			}

			for ( $index = 0; $index < $grid_count; ++$index ) {
				$widget = new Benchmark_Widget( 'grid_' . $index );
				$query  = new \WP_Query(
					array(
						'post_type'     => array( 'post' ),
						'paged'         => $index + 1,
						'posts_per_page'=> 6,
						'orderby'       => $index % 2 ? 'title' : 'date',
						'order'         => $index % 2 ? 'ASC' : 'DESC',
					)
				);

				if ( isset( $rule_id[ $scenario ] ) ) {
					$applier->apply( $query, $widget, $repository->get( $rule_id[ $scenario ] ) );
				}

				$query->posts = 'empty_result' === $scenario ? array() : array( (object) array( 'ID' => $index + 1 ) );
				$visibility->capture_query_result( $query, $widget );

				if ( 'id_union' === $scenario ) {
					$post_ids = $query->get( 'post__in' );
					$GLOBALS['elgqr_benchmark']['union_ids'] += is_array( $post_ids ) ? count( array_filter( $post_ids ) ) : 0;
					$diagnostics = $query->get( 'elgqr_composition_diagnostics' );
					if ( is_array( $diagnostics ) ) {
						$GLOBALS['elgqr_benchmark']['diagnostic_child_queries'] += isset( $diagnostics['child_query_count'] ) ? $diagnostics['child_query_count'] : 0;
						$GLOBALS['elgqr_benchmark']['diagnostic_cache_hits'] += isset( $diagnostics['cache_hits'] ) ? $diagnostics['cache_hits'] : 0;
						$GLOBALS['elgqr_benchmark']['diagnostic_cache_misses'] += isset( $diagnostics['cache_misses'] ) ? $diagnostics['cache_misses'] : 0;
					}
				}
			}

			$visibility->print_runtime_config();
		} else {
			for ( $index = 0; $index < $grid_count; ++$index ) {
				// Isolate loop overhead so the report does not claim Elementor's
				// primary queries as plugin-owned work.
			}
		}

		$elapsed_ms = ( microtime( true ) - $time_start ) * 1000;
		$peak_bytes = max( 0, memory_get_peak_usage( false ) - $memory_start );

		return array_merge(
			array(
				'scenario'    => $scenario,
				'grid_count'  => $grid_count,
				'elapsed_ms'  => round( $elapsed_ms, 4 ),
				'peak_bytes'  => $peak_bytes,
				'asset_bytes' => benchmark_asset_bytes(),
			),
			array_intersect_key(
				$GLOBALS['elgqr_benchmark'],
				array_flip(
					array(
						'all_enabled_calls', 'repository_reads', 'get_calls', 'normalized_rules',
						'context_reads', 'child_queries', 'child_ids', 'union_ids', 'after_apply_calls',
						'diagnostic_child_queries', 'diagnostic_cache_hits', 'diagnostic_cache_misses',
					)
				)
			)
		);
	}

	$scenario   = isset( $argv[1] ) ? $argv[1] : 'simple';
	$grid_count = isset( $argv[2] ) ? max( 1, (int) $argv[2] ) : 1;
	$allowed    = array( 'disabled', 'enabled_no_match', 'simple', 'compiled', 'id_union', 'empty_result' );

	if ( ! in_array( $scenario, $allowed, true ) ) {
		fwrite( STDERR, 'Unknown scenario: ' . $scenario . "\n" );
		exit( 2 );
	}

	echo json_encode( run_benchmark( $scenario, $grid_count ), JSON_UNESCAPED_SLASHES ) . "\n";
}
