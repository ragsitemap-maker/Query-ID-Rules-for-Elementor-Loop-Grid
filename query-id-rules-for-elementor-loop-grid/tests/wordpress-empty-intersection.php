<?php

/**
 * WordPress integration check for a legitimate empty Current Query intersection.
 *
 * Run inside an installed WordPress instance with this plugin active:
 * wp eval-file tests/wordpress-empty-intersection.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$category_a = wp_insert_term( 'ELGQR Category A', 'category', array( 'slug' => 'elgqr-category-a' ) );
$category_b = wp_insert_term( 'ELGQR Category B', 'category', array( 'slug' => 'elgqr-category-b' ) );

if ( is_wp_error( $category_a ) || is_wp_error( $category_b ) ) {
	throw new RuntimeException( 'Unable to create integration-test categories.' );
}

$post_id = wp_insert_post(
	array(
		'post_title'  => 'ELGQR Current Query Post',
		'post_status' => 'publish',
		'post_type'   => 'post',
	)
);

if ( is_wp_error( $post_id ) || ! $post_id ) {
	throw new RuntimeException( 'Unable to create the integration-test post.' );
}

wp_set_post_categories( $post_id, array( (int) $category_a['term_id'] ) );

$current_args = array(
	'post_type'      => 'post',
	'post_status'    => 'publish',
	'category_name'  => 'elgqr-category-a',
	'posts_per_page' => -1,
);
$current_query = new WP_Query( $current_args );

if ( 1 !== (int) $current_query->found_posts ) {
	throw new RuntimeException( 'The Current Query baseline must contain exactly one post.' );
}

$widget = new class() {
	public function get_name() {
		return 'loop-grid';
	}
};

$context_resolver = new \ELGQR\Context_Resolver();
$applier          = new \ELGQR\Query_Applier(
	$context_resolver,
	new \ELGQR\Rule_Repository(),
	new \ELGQR\Polylang_Adapter( $context_resolver )
);
$rule             = array(
	'id'                => 900001,
	'query_id'          => 'empty_intersection_test',
	'enabled'           => true,
	'post_types'        => array(),
	'tax_relation'      => 'AND',
	'tax_filters'       => array(
		array(
			'taxonomy'         => 'category',
			'terms'            => (string) $category_b['term_id'],
			'operator'         => 'IN',
			'include_children' => 1,
		),
	),
	'meta_relation'     => 'AND',
	'meta_filters'      => array(),
	'included_rule_ids' => array(),
	'sort'              => array( 'enabled' => 0 ),
);

$combined_query             = new WP_Query();
$combined_query->query_vars = $current_args;
$applier->apply( $combined_query, $widget, $rule );
$combined_query->query( $combined_query->query_vars );

if ( 0 !== (int) $combined_query->found_posts || ! empty( $combined_query->posts ) ) {
	throw new RuntimeException( 'Current Query AND an empty Query ID taxonomy result must return zero posts.' );
}

echo "WordPress empty-intersection integration test passed.\n";
