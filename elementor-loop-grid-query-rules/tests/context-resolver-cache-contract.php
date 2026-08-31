<?php

/**
 * Request-cache and context-isolation contracts for Context_Resolver.
 *
 * Run with: php tests/context-resolver-cache-contract.php
 */

namespace {
	define( 'ABSPATH', __DIR__ );

	$GLOBALS['elgqr_current_post_id'] = 10;
	$GLOBALS['elgqr_meta_reads']      = array();

	function absint( $value ) {
		return abs( (int) $value );
	}

	function wp_unslash( $value ) {
		return $value;
	}

	function is_singular() {
		return true;
	}

	function get_queried_object_id() {
		return $GLOBALS['elgqr_current_post_id'];
	}

	function get_queried_object() {
		return null;
	}

	function wp_doing_ajax() {
		return false;
	}

	function add_filter() {}

	function get_post_meta( $post_id, $key, $single = false ) {
		$counter = 'post:' . $post_id . ':' . $key;
		$GLOBALS['elgqr_meta_reads'][ $counter ] = isset( $GLOBALS['elgqr_meta_reads'][ $counter ] )
			? $GLOBALS['elgqr_meta_reads'][ $counter ] + 1
			: 1;

		if ( 'zero' === $key ) {
			return 0;
		}
		if ( 'empty_array' === $key ) {
			return array();
		}

		return 'post-' . $post_id . '-' . $key;
	}

	function get_term_meta( $term_id, $key, $single = false ) {
		$counter = 'term:' . $term_id . ':' . $key;
		$GLOBALS['elgqr_meta_reads'][ $counter ] = isset( $GLOBALS['elgqr_meta_reads'][ $counter ] )
			? $GLOBALS['elgqr_meta_reads'][ $counter ] + 1
			: 1;
		return 'term-' . $term_id . '-' . $key;
	}

	class WP_REST_Request {
		private $params;

		public function __construct( array $params ) {
			$this->params = $params;
		}

		public function get_param( $key ) {
			return isset( $this->params[ $key ] ) ? $this->params[ $key ] : null;
		}
	}

	class WP_Term {
		public $taxonomy;
		public $term_id;

		public function __construct( $taxonomy, $term_id ) {
			$this->taxonomy = $taxonomy;
			$this->term_id  = $term_id;
		}
	}

	class WP_Query {
		private $term;

		public function __construct( $term = null ) {
			$this->term = $term;
		}

		public function get_queried_object() {
			return $this->term;
		}
	}
}

namespace ELGQR {
	require_once dirname( __DIR__ ) . '/includes/class-context-resolver.php';

	function assert_same( $expected, $actual, $message ) {
		if ( $expected !== $actual ) {
			throw new \RuntimeException(
				$message . "\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true )
			);
		}
	}

	function repeat_value( Context_Resolver $resolver, $source, $key, $query = null ) {
		$value = null;
		for ( $index = 0; $index < 26; ++$index ) {
			$value = $resolver->value( $source, $key, '', null, $query );
		}
		return $value;
	}

	function run_tests() {
		$resolver = new Context_Resolver();
		assert_same( 'post-10-segment', repeat_value( $resolver, 'current_post_meta', 'segment' ), 'Post meta must resolve the active post value.' );
		assert_same( 1, $GLOBALS['elgqr_meta_reads']['post:10:segment'], 'A repeated post context key must read storage once.' );

		assert_same( 0, repeat_value( $resolver, 'current_post_meta', 'zero' ), 'A zero value must be preserved.' );
		assert_same( 1, $GLOBALS['elgqr_meta_reads']['post:10:zero'], 'A cached zero must not be mistaken for a cache miss.' );
		assert_same( array(), repeat_value( $resolver, 'current_post_meta', 'empty_array' ), 'An empty array value must be preserved.' );
		assert_same( 1, $GLOBALS['elgqr_meta_reads']['post:10:empty_array'], 'A cached empty array must not be mistaken for a cache miss.' );

		$GLOBALS['elgqr_current_post_id'] = 11;
		assert_same( 'post-11-segment', repeat_value( $resolver, 'current_post_meta', 'segment' ), 'Different posts must not share cached values.' );
		assert_same( 1, $GLOBALS['elgqr_meta_reads']['post:11:segment'], 'A second post needs one independent storage read.' );

		$term_20 = new \WP_Query( new \WP_Term( 'category', 20 ) );
		$term_21 = new \WP_Query( new \WP_Term( 'category', 21 ) );
		assert_same( 'term-20-segment', repeat_value( $resolver, 'current_term_meta', 'segment', $term_20 ), 'Term meta must resolve the active term.' );
		assert_same( 'term-21-segment', repeat_value( $resolver, 'current_term_meta', 'segment', $term_21 ), 'Different terms must not share cached values.' );
		assert_same( 1, $GLOBALS['elgqr_meta_reads']['term:20:segment'], 'A repeated term key must read storage once.' );
		assert_same( 1, $GLOBALS['elgqr_meta_reads']['term:21:segment'], 'A second term needs one independent storage read.' );

		$resolver->capture_rest_context( null, null, new \WP_REST_Request( array( 'post_id' => 30 ) ) );
		assert_same( 'post-30-segment', repeat_value( $resolver, 'current_post_meta', 'segment' ), 'REST context must take explicit precedence.' );
		assert_same( 1, $GLOBALS['elgqr_meta_reads']['post:30:segment'], 'REST context must reuse a key within its segment.' );
		$resolver->clear_rest_context( null );
		$resolver->capture_rest_context( null, null, new \WP_REST_Request( array( 'post_id' => 30 ) ) );
		repeat_value( $resolver, 'current_post_meta', 'segment' );
		assert_same( 2, $GLOBALS['elgqr_meta_reads']['post:30:segment'], 'A new REST segment must not reuse stale request context state.' );

		echo "Context resolver cache contract tests passed.\n";
	}

	try {
		run_tests();
	} catch ( \Throwable $error ) {
		fwrite( STDERR, $error->getMessage() . "\n" );
		exit( 1 );
	}
}
