<?php

/**
 * Lightweight contract tests for Query Rule defaults.
 *
 * Run with: php tests/rule-repository-defaults-contract.php
 */

namespace {
	define( 'ABSPATH', __DIR__ );

	$GLOBALS['elgqr_post_meta'] = array();
	$GLOBALS['elgqr_get_posts_calls'] = 0;
	$GLOBALS['elgqr_meta_reads'] = array();

	function get_posts() {
		++$GLOBALS['elgqr_get_posts_calls'];
		return array_map(
			static function ( $id ) {
				return (object) array( 'ID' => $id );
			},
			array_keys( $GLOBALS['elgqr_post_meta'] )
		);
	}

	function get_post_meta( $post_id, $key, $single = false ) {
		$counter_key = $post_id . ':' . $key;
		$GLOBALS['elgqr_meta_reads'][ $counter_key ] = isset( $GLOBALS['elgqr_meta_reads'][ $counter_key ] )
			? $GLOBALS['elgqr_meta_reads'][ $counter_key ] + 1
			: 1;
		return isset( $GLOBALS['elgqr_post_meta'][ $post_id ][ $key ] )
			? $GLOBALS['elgqr_post_meta'][ $post_id ][ $key ]
			: '';
	}

	function wp_parse_args( $args, $defaults = array() ) {
		return array_merge( $defaults, is_array( $args ) ? $args : array() );
	}
}

namespace ELGQR {
	require_once dirname( __DIR__ ) . '/includes/class-rule-repository.php';

	function assert_same( $expected, $actual, $message ) {
		if ( $expected !== $actual ) {
			throw new \RuntimeException(
				$message . "\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true )
			);
		}
	}

	function set_config( $post_id, array $config ) {
		$GLOBALS['elgqr_post_meta'][ $post_id ][ Rule_Repository::CONFIG_META ] = $config;
	}

	function run_tests() {
		$repository = new Rule_Repository();

		$missing = $repository->get( 10 );
		assert_same( 1, $missing['empty_result']['enabled'], 'A rule without empty-result settings must default to enabled.' );
		assert_same( '', $missing['empty_result']['target_selector'], 'The default selector must remain empty for automatic TAB targeting.' );

		set_config(
			11,
			array(
				'empty_result' => array(
					'enabled'         => 0,
					'target_selector' => '.energy-tab',
				),
			)
		);
		$disabled = $repository->get( 11 );
		assert_same( 0, $disabled['empty_result']['enabled'], 'An explicitly disabled rule must remain disabled.' );
		assert_same( '.energy-tab', $disabled['empty_result']['target_selector'], 'An explicitly saved selector must be preserved.' );

		set_config(
			12,
			array(
				'empty_result' => array(
					'enabled'         => 1,
					'target_selector' => '',
				),
			)
		);
		$enabled = $repository->get( 12 );
		assert_same( 1, $enabled['empty_result']['enabled'], 'An explicitly enabled rule must remain enabled.' );

		$first = $repository->get( 12 );
		$first['query_id'] = 'mutated_locally';
		$second = $repository->get( 12 );
		assert_same( '', $second['query_id'], 'A caller mutation must not alter the request cache.' );
		assert_same( 1, $GLOBALS['elgqr_meta_reads']['12:' . Rule_Repository::CONFIG_META], 'A rule must normalize only once per request.' );

		$repository->all_enabled();
		$repository->all_enabled();
		assert_same( 1, $GLOBALS['elgqr_get_posts_calls'], 'The enabled rule query must run at most once per request.' );

		$GLOBALS['elgqr_post_meta'][12][ Rule_Repository::QUERY_META ] = 'after_forget';
		$repository->forget( 12 );
		assert_same( 'after_forget', $repository->get( 12 )['query_id'], 'forget() must expose freshly saved rule meta.' );
		$repository->all_enabled();
		assert_same( 2, $GLOBALS['elgqr_get_posts_calls'], 'forget() must invalidate the enabled-set cache.' );

		$repository->reset();
		$repository->all_enabled();
		assert_same( 3, $GLOBALS['elgqr_get_posts_calls'], 'reset() must invalidate every request cache.' );

		echo "Rule repository defaults contract tests passed.\n";
	}

	try {
		run_tests();
	} catch ( \Throwable $error ) {
		fwrite( STDERR, $error->getMessage() . "\n" );
		exit( 1 );
	}
}
