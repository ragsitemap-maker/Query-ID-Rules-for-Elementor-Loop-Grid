<?php

/**
 * Lightweight behavior tests for the empty-result visibility handler.
 *
 * Run with: php tests/empty-result-visibility-contract.php
 */

namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'ELGQR_URL', 'https://example.test/plugin/' );
	define( 'ELGQR_VERSION', 'test' );

	$GLOBALS['elgqr_inline_scripts'] = array();
	$GLOBALS['elgqr_registered_scripts'] = array();
	$GLOBALS['elgqr_enqueued_scripts'] = array();
	$GLOBALS['elgqr_registered_styles'] = array();
	$GLOBALS['elgqr_enqueued_styles'] = array();

	function add_action() {}

	function wp_register_style( $handle ) {
		$GLOBALS['elgqr_registered_styles'][ $handle ] = true;
	}

	function wp_enqueue_style( $handle ) {
		$GLOBALS['elgqr_enqueued_styles'][ $handle ] = true;
	}

	function wp_register_script( $handle, $src = '', $deps = array() ) {
		$GLOBALS['elgqr_registered_scripts'][ $handle ] = array(
			'src'  => $src,
			'deps' => $deps,
		);
	}

	function wp_enqueue_script( $handle ) {
		$GLOBALS['elgqr_enqueued_scripts'][ $handle ] = true;
	}

	function wp_script_is( $handle, $status = 'enqueued' ) {
		return 'registered' === $status
			? isset( $GLOBALS['elgqr_registered_scripts'][ $handle ] )
			: isset( $GLOBALS['elgqr_enqueued_scripts'][ $handle ] );
	}

	function wp_add_inline_script( $handle, $script, $position ) {
		$GLOBALS['elgqr_inline_scripts'][] = compact( 'handle', 'script', 'position' );
		return true;
	}

	function wp_json_encode( $value, $flags = 0 ) {
		return json_encode( $value, $flags );
	}

	function absint( $value ) {
		return abs( (int) $value );
	}

	function sanitize_key( $value ) {
		$value = strtolower( (string) $value );
		return preg_replace( '/[^a-z0-9_\-]/', '', $value );
	}

	class WP_Query {
		public $posts = array();
		public $found_posts = 0;

		private $vars = array();

		public function __construct( array $vars = array(), array $posts = array(), $found_posts = null ) {
			$this->vars        = $vars;
			$this->posts       = $posts;
			$this->found_posts = null === $found_posts ? count( $posts ) : $found_posts;
		}

		public function get( $key ) {
			return isset( $this->vars[ $key ] ) ? $this->vars[ $key ] : '';
		}
	}
}

namespace ELGQR {
	class Rule_Repository {
		private $rules;

		public $all_enabled_calls = 0;

		public function __construct( array $rules ) {
			$this->rules = $rules;
		}

		public function all_enabled() {
			++$this->all_enabled_calls;
			return $this->rules;
		}
	}

	require_once dirname( __DIR__ ) . '/includes/class-empty-result-visibility.php';

	class Test_Widget {
		private $name;

		private $id;

		public function __construct( $id = 'abc123', $name = 'loop-grid' ) {
			$this->id   = $id;
			$this->name = $name;
		}

		public function get_name() {
			return $this->name;
		}

		public function get_id() {
			return $this->id;
		}
	}

	function rule( array $overrides = array() ) {
		return array_merge(
			array(
				'id'           => 10,
				'query_id'     => 'energy_rule',
				'empty_result' => array(
					'enabled'         => 1,
					'target_selector' => '',
				),
			),
			$overrides
		);
	}

	function assert_same( $expected, $actual, $message ) {
		if ( $expected !== $actual ) {
			throw new \RuntimeException(
				$message . "\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true )
			);
		}
	}

	function reset_inline_scripts() {
		$GLOBALS['elgqr_inline_scripts'] = array();
	}

	function reset_assets() {
		$GLOBALS['elgqr_registered_scripts'] = array();
		$GLOBALS['elgqr_enqueued_scripts']   = array();
		$GLOBALS['elgqr_registered_styles']  = array();
		$GLOBALS['elgqr_enqueued_styles']    = array();
		reset_inline_scripts();
	}

	function config_from_handler( Empty_Result_Visibility $handler ) {
		reset_inline_scripts();
		$handler->print_runtime_config();

		if ( empty( $GLOBALS['elgqr_inline_scripts'] ) ) {
			return array(
				'records' => array(),
				'counts'  => array(),
			);
		}

		$entry  = $GLOBALS['elgqr_inline_scripts'][0];
		$prefix = 'window.ELGQR_EMPTY_RESULTS = ';
		$json   = substr( $entry['script'], strlen( $prefix ), -1 );
		$data   = json_decode( $json, true );

		assert_same( 'elgqr-frontend', $entry['handle'], 'Runtime config must attach to the frontend handle.' );
		assert_same( 'before', $entry['position'], 'Runtime config must print before the frontend script.' );

		return array(
			'records' => isset( $data['records'] ) ? $data['records'] : array(),
			'counts'  => isset( $data['counts'] ) ? $data['counts'] : array(),
		);
	}

	function records_from_handler( Empty_Result_Visibility $handler ) {
		$config = config_from_handler( $handler );
		return $config['records'];
	}

	function run_tests() {
		$handler = new Empty_Result_Visibility( new Rule_Repository( array( rule() ) ) );
		$handler->capture_query_result( new \WP_Query( array( 'elgqr_rule_id' => 10 ), array() ), new Test_Widget() );
		$config  = config_from_handler( $handler );
		$records = $config['records'];
		assert_same( 1, count( $records ), 'An enabled empty result must create one visibility record.' );
		assert_same( 'abc123', $records[0]['widgetId'], 'The record must identify the rendered widget.' );
		assert_same( 'energy_rule', $records[0]['queryId'], 'The record must identify the applied Query ID.' );
		assert_same( '', $records[0]['selector'], 'An empty selector must preserve automatic Nested Tabs mode.' );
		assert_same( array( array( 'widgetId' => 'abc123', 'total' => null ) ), $config['counts'], 'An empty query must not claim an exact full-result total.' );
		assert_same( true, isset( $GLOBALS['elgqr_enqueued_scripts']['elgqr-frontend'] ), 'A real empty result must demand-load the footer script.' );
		assert_same( array(), $GLOBALS['elgqr_registered_scripts']['elgqr-frontend']['deps'], 'The frontend script must stay independent from the Elementor frontend handle.' );

		$handler = new Empty_Result_Visibility( new Rule_Repository( array( rule() ) ) );
		$handler->capture_query_result( new \WP_Query( array(), array( (object) array( 'ID' => 1 ) ), 12 ), new Test_Widget( 'candidate' ) );
		$handler->capture_query_result( new \WP_Query( array( 'elgqr_rule_id' => 10 ), array(), 0 ), new Test_Widget( 'empty' ) );
		$config = config_from_handler( $handler );
		assert_same(
			array(
				array( 'widgetId' => 'candidate', 'total' => 12 ),
				array( 'widgetId' => 'empty', 'total' => null ),
			),
			$config['counts'],
			'Counts rendered before the managed empty Grid must remain available without requiring an ELGQR Query ID.'
		);

		$handler = new Empty_Result_Visibility( new Rule_Repository( array( rule() ) ) );
		$handler->capture_query_result( new \WP_Query( array(), array( (object) array( 'ID' => 1 ) ), 7 ), new Test_Widget( 'latest' ) );
		$handler->capture_query_result( new \WP_Query( array( 'no_found_rows' => true ), array( (object) array( 'ID' => 1 ) ), 99 ), new Test_Widget( 'latest' ) );
		$handler->capture_query_result( new \WP_Query( array( 'elgqr_rule_id' => 10 ), array(), 0 ), new Test_Widget( 'empty' ) );
		$config = config_from_handler( $handler );
		assert_same( array( 'widgetId' => 'latest', 'total' => null ), $config['counts'][0], 'The latest callback must replace a stale exact total, and no_found_rows must remain unknown.' );

		$handler = new Empty_Result_Visibility( new Rule_Repository( array( rule() ) ) );
		$handler->capture_query_result( new \WP_Query( array(), array( (object) array( 'ID' => 1 ), (object) array( 'ID' => 2 ) ), 1 ), new Test_Widget( 'invalid_total' ) );
		$handler->capture_query_result( new \WP_Query( array( 'elgqr_rule_id' => 10 ), array(), 0 ), new Test_Widget( 'empty' ) );
		$config = config_from_handler( $handler );
		assert_same( null, $config['counts'][0]['total'], 'A found_posts value below the rendered post count must be treated as unknown.' );

		$handler = new Empty_Result_Visibility( new Rule_Repository( array( rule() ) ) );
		$handler->capture_query_result( new \WP_Query( array( 'elgqr_rule_id' => 10 ), array( (object) array( 'ID' => 1 ) ) ), new Test_Widget() );
		assert_same( array(), records_from_handler( $handler ), 'A non-empty query must not create a visibility record.' );

		$disabled = rule( array( 'empty_result' => array( 'enabled' => 0, 'target_selector' => '' ) ) );
		$handler  = new Empty_Result_Visibility( new Rule_Repository( array( $disabled ) ) );
		$handler->capture_query_result( new \WP_Query( array( 'elgqr_rule_id' => 10 ), array() ), new Test_Widget() );
		assert_same( array(), records_from_handler( $handler ), 'A disabled rule must not create a visibility record.' );

		$legacy  = rule( array( 'empty_result' => null ) );
		$handler = new Empty_Result_Visibility( new Rule_Repository( array( $legacy ) ) );
		$handler->capture_query_result( new \WP_Query( array( 'elgqr_rule_id' => 10 ), array() ), new Test_Widget() );
		assert_same( array(), records_from_handler( $handler ), 'A legacy rule without settings must remain disabled.' );

		$custom  = rule( array( 'empty_result' => array( 'enabled' => 1, 'target_selector' => '.energy-tab' ) ) );
		$handler = new Empty_Result_Visibility( new Rule_Repository( array( $custom ) ) );
		$handler->capture_query_result( new \WP_Query( array( 'elgqr_rule_id' => 10 ), array() ), new Test_Widget( '77e3437' ) );
		$records = records_from_handler( $handler );
		assert_same( '.energy-tab', $records[0]['selector'], 'A configured selector must be passed to the frontend unchanged.' );

		$handler = new Empty_Result_Visibility( new Rule_Repository( array( rule() ) ) );
		$handler->capture_query_result( new \WP_Query( array( 'elgqr_rule_id' => 10 ), array() ), new Test_Widget( 'abc123', 'posts' ) );
		assert_same( array(), records_from_handler( $handler ), 'A non-Loop-Grid widget must be ignored.' );

		$handler = new Empty_Result_Visibility( new Rule_Repository( array( rule() ) ) );
		$widget  = new Test_Widget();
		$handler->capture_query_result( new \WP_Query( array( 'elgqr_rule_id' => 10 ), array() ), $widget );
		$handler->capture_query_result( new \WP_Query( array( 'elgqr_rule_id' => 10 ), array( (object) array( 'ID' => 1 ) ) ), $widget );
		assert_same( array(), records_from_handler( $handler ), 'A later non-empty result for the same widget must clear a stale empty record.' );

		$repository = new Rule_Repository( array( rule() ) );
		$handler    = new Empty_Result_Visibility( $repository );
		for ( $index = 0; $index < 100; ++$index ) {
			$handler->capture_query_result( new \WP_Query( array(), array() ), new Test_Widget( 'grid_' . $index ) );
		}
		assert_same( 0, $repository->all_enabled_calls, 'Non-ELGQR Grid events must not initialize the visibility rule map.' );
		assert_same( array(), records_from_handler( $handler ), 'Non-ELGQR Grid events must not create runtime config.' );

		reset_assets();
		$repository = new Rule_Repository( array( rule() ) );
		$handler    = new Empty_Result_Visibility( $repository );
		$handler->enqueue_assets();
		assert_same( true, isset( $GLOBALS['elgqr_enqueued_styles']['elgqr-frontend'] ), 'The small anti-FOUC stylesheet must remain in the reliable head path.' );
		assert_same( false, isset( $GLOBALS['elgqr_enqueued_scripts']['elgqr-frontend'] ), 'A visibility rule alone must not enqueue the footer script.' );
		$handler->capture_query_result( new \WP_Query( array( 'elgqr_rule_id' => 10 ), array( (object) array( 'ID' => 1 ) ) ), new Test_Widget() );
		$handler->print_runtime_config();
		assert_same( false, isset( $GLOBALS['elgqr_enqueued_scripts']['elgqr-frontend'] ), 'A non-empty request must keep the footer script and config at zero.' );
		assert_same( array(), $GLOBALS['elgqr_inline_scripts'], 'A non-empty request must not output runtime config.' );

		reset_assets();
		$handler = new Empty_Result_Visibility( new Rule_Repository( array( rule() ) ) );
		$query   = new \WP_Query( array( 'elgqr_rule_id' => 10 ), array() );
		$widget  = new Test_Widget( 'dedupe' );
		$handler->capture_query_result( $query, $widget );
		$handler->capture_query_result( $query, $widget );
		assert_same( 1, count( records_from_handler( $handler ) ), 'Duplicate widget/selector records must collapse to one config record.' );

		echo "Empty-result visibility contract tests passed.\n";
	}

	try {
		run_tests();
	} catch ( \Throwable $error ) {
		fwrite( STDERR, $error->getMessage() . "\n" );
		exit( 1 );
	}
}
