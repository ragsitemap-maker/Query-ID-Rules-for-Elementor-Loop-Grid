<?php

namespace ELGQR;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rule_Repository {
	const POST_TYPE   = 'elgqr_rule';
	const CONFIG_META = '_elgqr_config';
	const QUERY_META  = '_elgqr_query_id';
	const ENABLED_META = '_elgqr_enabled';

	private $rule_cache = array();

	private $enabled_cache = null;

	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels' => array(
					'name'          => __( 'Query ID Rules', 'query-id-rules-for-elementor-loop-grid' ),
					'singular_name' => __( 'Query ID Rule', 'query-id-rules-for-elementor-loop-grid' ),
					'add_new_item'  => __( 'Add Query ID Rule', 'query-id-rules-for-elementor-loop-grid' ),
					'edit_item'     => __( 'Edit Query ID Rule', 'query-id-rules-for-elementor-loop-grid' ),
					'menu_name'     => __( 'Query ID Rules', 'query-id-rules-for-elementor-loop-grid' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => true,
				'show_in_menu'        => 'tools.php',
				'show_in_rest'        => false,
				'menu_icon'           => 'dashicons-filter',
				'supports'            => array( 'title' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'exclude_from_search' => true,
			)
		);
	}

	public function exclude_from_polylang( $post_types ) {
		return is_array( $post_types )
			? array_diff( $post_types, array( self::POST_TYPE ) )
			: $post_types;
	}

	public function all_enabled() {
		if ( null !== $this->enabled_cache ) {
			return $this->enabled_cache;
		}

		$posts = get_posts(
			array(
				'post_type'              => self::POST_TYPE,
				'post_status'            => 'publish',
				'lang'                   => '',
				'posts_per_page'         => -1,
				'orderby'                => 'menu_order title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'suppress_filters'       => false,
				'update_post_term_cache' => false,
				'meta_query'             => array(
					array(
						'key'   => self::ENABLED_META,
						'value' => '1',
					),
				),
			)
		);

		$rules = array();

		foreach ( $posts as $post ) {
			$rule = $this->get( $post->ID );
			if ( $rule['query_id'] ) {
				$rules[] = $rule;
			}
		}

		$this->enabled_cache = $rules;
		return $this->enabled_cache;
	}

	public function get( $post_id ) {
		$post_id = (int) $post_id;

		if ( array_key_exists( $post_id, $this->rule_cache ) ) {
			return $this->rule_cache[ $post_id ];
		}

		$config = get_post_meta( $post_id, self::CONFIG_META, true );
		$config = is_array( $config ) ? $config : array();

		$this->rule_cache[ $post_id ] = wp_parse_args(
			$config,
			array(
				'id'                => (int) $post_id,
				'query_id'          => (string) get_post_meta( $post_id, self::QUERY_META, true ),
				'enabled'           => '1' === get_post_meta( $post_id, self::ENABLED_META, true ),
				'post_types'        => array(),
				'tax_relation'      => 'AND',
				'tax_filters'       => array(),
				'meta_relation'     => 'AND',
				'meta_filters'      => array(),
				'included_rule_ids' => array(),
				'sort'              => array(),
				'empty_result'      => array(
					'enabled'         => 1,
					'target_selector' => '',
				),
			)
		);

		return $this->rule_cache[ $post_id ];
	}

	public function forget( $post_id ) {
		unset( $this->rule_cache[ (int) $post_id ] );
		$this->enabled_cache = null;
	}

	public function reset() {
		$this->rule_cache    = array();
		$this->enabled_cache = null;
	}

	public function unique_query_id( $query_id, $post_id ) {
		$base      = sanitize_key( $query_id );
		$base      = $base ? $base : 'loop_grid_rule';
		$candidate = $base;
		$index     = 2;

		while ( $this->query_id_exists( $candidate, $post_id ) ) {
			$candidate = $base . '_' . $index;
			++$index;
		}

		return $candidate;
	}

	private function query_id_exists( $query_id, $excluded_post_id ) {
		$ids = get_posts(
			array(
				'post_type'              => self::POST_TYPE,
				'post_status'            => array( 'publish', 'draft', 'pending', 'private', 'trash' ),
				'lang'                   => '',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'post__not_in'           => array( (int) $excluded_post_id ),
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_key'               => self::QUERY_META,
				'meta_value'             => $query_id,
			)
		);

		return ! empty( $ids );
	}
}
