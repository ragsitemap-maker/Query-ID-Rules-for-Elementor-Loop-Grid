<?php

namespace ELGQR;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Query_Applier {
	private $context_resolver;

	private $repository;

	private $polylang_adapter;

	private $composition_stack = array();

	private $composition_id_cache = array();

	private $allowed_tax_operators = array( 'IN', 'AND', 'NOT IN' );

	private $allowed_meta_compares = array(
		'=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE',
		'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN', 'EXISTS', 'NOT EXISTS',
		'REGEXP', 'NOT REGEXP', 'RLIKE', 'ACF_CONTAINS',
	);

	private $allowed_meta_types = array(
		'CHAR', 'NUMERIC', 'DECIMAL', 'SIGNED', 'UNSIGNED',
		'DATE', 'DATETIME', 'TIME', 'BINARY',
	);

	public function __construct( Context_Resolver $context_resolver, Rule_Repository $repository, Polylang_Adapter $polylang_adapter ) {
		$this->context_resolver = $context_resolver;
		$this->repository       = $repository;
		$this->polylang_adapter  = $polylang_adapter;
	}

	public function apply( $query, $widget, array $rule, $is_composition_child = false ) {
		if ( ! $query instanceof \WP_Query || ! $this->is_loop_grid( $widget ) ) {
			return;
		}

		if ( $query->get( 'elgqr_composition_child' ) && ! $is_composition_child ) {
			return;
		}

		/**
		 * Allows a site integration to prevent a configured rule from running.
		 *
		 * @param bool      $should_apply Whether the rule should run.
		 * @param array     $rule         Normalized rule configuration.
		 * @param \WP_Query $query        Elementor query instance.
		 * @param object    $widget       Elementor widget instance.
		 */
		if ( ! apply_filters( 'elgqr/should_apply_rule', true, $rule, $query, $widget ) ) {
			return;
		}

		$query->set( 'elgqr_rule_id', (int) $rule['id'] );

		if ( ! empty( $rule['post_types'] ) ) {
			$query->set( 'post_type', array_values( array_filter( array_map( 'sanitize_key', (array) $rule['post_types'] ) ) ) );
		}

		$this->polylang_adapter->apply_current_language( $query, $widget );
		$this->apply_tax_filters( $query, $rule );
		$meta_query = array();

		if ( ! $this->is_forced_empty( $query ) ) {
			$meta_query = $this->build_merged_meta_query( $query, $widget, $rule );

			if ( ! empty( $meta_query ) ) {
				$query->set( 'meta_query', $meta_query );
			}
		}

		if ( ! $this->is_forced_empty( $query ) ) {
			$this->apply_rule_composition( $query, $widget, $rule );
		}

		if ( ! $this->is_forced_empty( $query ) ) {
			$meta_query = $query->get( 'meta_query' );
			$meta_query = is_array( $meta_query ) ? $meta_query : array();
			$this->apply_sorting( $query, $meta_query, $rule );

			if ( ! empty( $meta_query ) ) {
				$query->set( 'meta_query', $meta_query );
			}
		}

		/**
		 * Runs after the configured rule has modified the Elementor Loop Grid query.
		 */
		do_action( 'elgqr/after_apply_rule', $query, $widget, $rule );
	}

	private function is_forced_empty( \WP_Query $query ) {
		return array( 0 ) === $query->get( 'post__in' );
	}

	private function apply_rule_composition( \WP_Query $query, $widget, array $rule ) {
		$included_ids = isset( $rule['included_rule_ids'] ) ? array_values( array_unique( array_filter( array_map( 'absint', (array) $rule['included_rule_ids'] ) ) ) ) : array();
		$rule_id      = isset( $rule['id'] ) ? absint( $rule['id'] ) : 0;

		if ( empty( $included_ids ) ) {
			return;
		}

		if ( $rule_id && in_array( $rule_id, $this->composition_stack, true ) ) {
			return;
		}

		if ( $rule_id ) {
			$this->composition_stack[] = $rule_id;
		}

		$child_rules = array();

		foreach ( $included_ids as $included_id ) {
			if ( in_array( $included_id, $this->composition_stack, true ) ) {
				continue;
			}

			if ( 'publish' !== get_post_status( $included_id ) ) {
				continue;
			}

			$child_rule = $this->repository->get( $included_id );
			if ( empty( $child_rule['enabled'] ) || empty( $child_rule['query_id'] ) ) {
				continue;
			}

			$child_rules[] = $child_rule;
		}

		if ( empty( $child_rules ) ) {
			if ( $rule_id ) {
				array_pop( $this->composition_stack );
			}
			$query->set( 'post__in', array( 0 ) );
			$query->set( 'elgqr_composition_mode', 'empty' );
			return;
		}

		if ( $this->try_compile_composition( $query, $widget, $child_rules ) ) {
			if ( $rule_id ) {
				array_pop( $this->composition_stack );
			}
			return;
		}

		$base_args = $query->query_vars;
		$union_ids = array();
		$diagnostics_enabled = (bool) apply_filters( 'elgqr/enable_composition_diagnostics', false, $query, $rule );
		$diagnostics = $diagnostics_enabled
			? array(
				'mode'              => 'id_union',
				'child_count'       => count( $child_rules ),
				'child_query_count' => 0,
				'child_id_counts'   => array(),
				'final_union_count' => 0,
				'cache_hits'        => 0,
				'cache_misses'      => 0,
			)
			: null;
		$query->set( 'elgqr_composition_mode', 'id_union' );

		foreach ( $child_rules as $child_rule ) {
			$cache_hit = false;
			$child_ids = $this->query_child_rule_ids( $base_args, $widget, $child_rule, $cache_hit );
			$union_ids = array_merge( $union_ids, $child_ids );

			if ( $diagnostics_enabled ) {
				if ( $cache_hit ) {
					++$diagnostics['cache_hits'];
				} else {
					++$diagnostics['cache_misses'];
					++$diagnostics['child_query_count'];
				}

				$diagnostics['child_id_counts'][] = array(
					'rule_id'   => isset( $child_rule['id'] ) ? absint( $child_rule['id'] ) : 0,
					'id_count'  => count( $child_ids ),
					'cache_hit' => $cache_hit,
				);
			}
		}

		if ( $rule_id ) {
			array_pop( $this->composition_stack );
		}

		$union_ids = array_values( array_unique( array_filter( array_map( 'absint', $union_ids ) ) ) );
		$existing  = $query->get( 'post__in' );

		if ( is_array( $existing ) && ! empty( $existing ) ) {
			$union_ids = array_values( array_intersect( array_map( 'absint', $existing ), $union_ids ) );
		}

		if ( $diagnostics_enabled ) {
			$diagnostics['final_union_count'] = count( $union_ids );
			$query->set( 'elgqr_composition_diagnostics', $diagnostics );
			do_action( 'elgqr/composition_diagnostics', $diagnostics, $query, $rule );
		}

		$query->set( 'post__in', $union_ids ? $union_ids : array( 0 ) );
	}

	private function try_compile_composition( \WP_Query $query, $widget, array $child_rules ) {
		$kind         = '';
		$parent_types = $this->normalized_post_types( $query->get( 'post_type' ) );

		foreach ( $child_rules as $child_rule ) {
			if ( ! empty( $child_rule['included_rule_ids'] ) ) {
				return false;
			}

			$child_types = $this->normalized_post_types( isset( $child_rule['post_types'] ) ? $child_rule['post_types'] : array() );
			if ( ! empty( $child_types ) && ( empty( $parent_types ) || $child_types !== $parent_types ) ) {
				return false;
			}

			$has_tax  = ! empty( $child_rule['tax_filters'] );
			$has_meta = ! empty( $child_rule['meta_filters'] );

			if ( $has_tax && $has_meta ) {
				return false;
			}

			if ( ! $has_tax && ! $has_meta ) {
				return true;
			}

			$child_kind = $has_tax ? 'tax' : 'meta';
			if ( $kind && $kind !== $child_kind ) {
				return false;
			}
			$kind = $child_kind;
		}

		if ( 'tax' === $kind ) {
			return $this->compile_tax_composition( $query, $child_rules );
		}

		if ( 'meta' === $kind ) {
			return $this->compile_meta_composition( $query, $widget, $child_rules );
		}

		return false;
	}

	private function compile_tax_composition( \WP_Query $query, array $child_rules ) {
		$branches = array();

		foreach ( $child_rules as $child_rule ) {
			$temporary_query = new \WP_Query();
			$this->apply_tax_filters( $temporary_query, $child_rule );
			$no_results = array( 0 ) === $temporary_query->get( 'post__in' );

			if ( $no_results ) {
				continue;
			}

			$branch = $temporary_query->get( 'tax_query' );

			if ( ! is_array( $branch ) || empty( $branch ) ) {
				return true;
			}
			$branches[] = $branch;
		}

		if ( empty( $branches ) ) {
			$query->set( 'post__in', array( 0 ) );
			return true;
		}

		$this->merge_composition_group( $query, 'tax_query', array_merge( array( 'relation' => 'OR' ), $branches ) );
		$query->set( 'elgqr_composition_mode', 'compiled_tax' );
		return true;
	}

	private function compile_meta_composition( \WP_Query $query, $widget, array $child_rules ) {
		$branches = array();

		foreach ( $child_rules as $child_rule ) {
			$temporary_query = new \WP_Query();
			$branch          = $this->build_merged_meta_query( $temporary_query, $widget, $child_rule );
			$no_results      = array( 0 ) === $temporary_query->get( 'post__in' );

			if ( $no_results ) {
				continue;
			}

			if ( empty( $branch ) ) {
				return true;
			}
			$branches[] = $branch;
		}

		if ( empty( $branches ) ) {
			$query->set( 'post__in', array( 0 ) );
			return true;
		}

		$this->merge_composition_group( $query, 'meta_query', array_merge( array( 'relation' => 'OR' ), $branches ) );
		$query->set( 'elgqr_composition_mode', 'compiled_meta' );
		return true;
	}

	private function merge_composition_group( \WP_Query $query, $query_var, array $group ) {
		$existing = $query->get( $query_var );

		if ( is_array( $existing ) && ! empty( $existing ) ) {
			$query->set(
				$query_var,
				array(
					'relation' => 'AND',
					$existing,
					$group,
				)
			);
			return;
		}

		$query->set( $query_var, $group );
	}

	private function normalized_post_types( $post_types ) {
		$post_types = is_array( $post_types ) ? $post_types : array( $post_types );
		$post_types = array_values( array_unique( array_filter( array_map( 'sanitize_key', $post_types ) ) ) );
		sort( $post_types );
		return $post_types;
	}

	private function query_child_rule_ids( array $base_args, $widget, array $child_rule, &$cache_hit = null ) {
		$args = $this->canonical_child_query_args( $base_args );
		$cache_key = md5(
			(int) $child_rule['id'] . '|'
			. $this->context_resolver->current_post_id( $widget ) . '|'
			. maybe_serialize( $args )
		);

		if ( array_key_exists( $cache_key, $this->composition_id_cache ) ) {
			$cache_hit = true;
			return $this->composition_id_cache[ $cache_key ];
		}

		$cache_hit = false;
		$token = wp_generate_uuid4();
		$args['elgqr_composition_child'] = $token;

		if ( isset( $child_rule['sort'] ) && is_array( $child_rule['sort'] ) ) {
			$child_rule['sort']['enabled'] = 0;
		}

		$setup = function ( $child_query ) use ( $token, $widget, $child_rule, &$setup ) {
			if ( $token !== $child_query->get( 'elgqr_composition_child' ) ) {
				return;
			}

			remove_action( 'pre_get_posts', $setup, 1 );
			$this->apply( $child_query, $widget, $child_rule, true );
		};

		add_action( 'pre_get_posts', $setup, 1 );
		$child_query = new \WP_Query( $args );
		remove_action( 'pre_get_posts', $setup, 1 );

		$ids = array_values( array_filter( array_map( 'absint', (array) $child_query->posts ) ) );
		$this->composition_id_cache[ $cache_key ] = $ids;
		return $ids;
	}

	private function canonical_child_query_args( array $base_args ) {
		unset(
			$base_args['orderby'],
			$base_args['order'],
			$base_args['meta_key'],
			$base_args['meta_type'],
			$base_args['paged'],
			$base_args['page'],
			$base_args['offset'],
			$base_args['fields'],
			$base_args['posts_per_page'],
			$base_args['nopaging'],
			$base_args['no_found_rows'],
			$base_args['ignore_sticky_posts'],
			$base_args['update_post_meta_cache'],
			$base_args['update_post_term_cache'],
			$base_args['elgqr_rule_id'],
			$base_args['elgqr_composition_child'],
			$base_args['elgqr_composition_mode'],
			$base_args['elgqr_composition_diagnostics']
		);

		$base_args['fields']                 = 'ids';
		$base_args['posts_per_page']         = -1;
		$base_args['nopaging']               = true;
		$base_args['no_found_rows']          = true;
		$base_args['ignore_sticky_posts']    = true;
		$base_args['update_post_meta_cache'] = false;
		$base_args['update_post_term_cache'] = false;

		ksort( $base_args );
		return $base_args;
	}

	private function is_loop_grid( $widget ) {
		return is_object( $widget )
			&& method_exists( $widget, 'get_name' )
			&& 'loop-grid' === $widget->get_name();
	}

	private function apply_tax_filters( \WP_Query $query, array $rule ) {
		$clauses = array();

		foreach ( (array) $rule['tax_filters'] as $filter ) {
			$taxonomy = isset( $filter['taxonomy'] ) ? sanitize_key( $filter['taxonomy'] ) : '';
			$operator = isset( $filter['operator'] ) ? strtoupper( $filter['operator'] ) : 'IN';
			$operator = in_array( $operator, $this->allowed_tax_operators, true ) ? $operator : 'IN';
			$terms    = $this->split_values( isset( $filter['terms'] ) ? $filter['terms'] : '' );

			if ( ! taxonomy_exists( $taxonomy ) || empty( $terms ) ) {
				// A configured taxonomy row is a required constraint. If it cannot
				// produce a clause, fail closed instead of falling back to the
				// unfiltered Current Query.
				$query->set( 'post__in', array( 0 ) );
				continue;
			}

			$terms = array_values( array_unique( array_filter( array_map( 'absint', $terms ) ) ) );

			if ( empty( $terms ) ) {
				// A configured row containing slugs, names, or invalid IDs must not
				// silently broaden the query by skipping its filter.
				$query->set( 'post__in', array( 0 ) );
				continue;
			}

			$terms = $this->polylang_adapter->translate_term_ids( $taxonomy, $terms );
			$terms = array_values( array_unique( array_filter( array_map( 'absint', $terms ) ) ) );

			if ( empty( $terms ) ) {
				$query->set( 'post__in', array( 0 ) );
				continue;
			}

			$clauses[] = array(
				'taxonomy'         => $taxonomy,
				'field'            => 'term_id',
				'terms'            => $terms,
				'operator'         => $operator,
				'include_children' => ! isset( $filter['include_children'] ) || ! empty( $filter['include_children'] ),
			);
		}

		if ( empty( $clauses ) ) {
			return;
		}

		$new_group = array_merge(
			array( 'relation' => $this->relation( $rule['tax_relation'] ) ),
			$clauses
		);
		$existing = $query->get( 'tax_query' );

		if ( is_array( $existing ) && ! empty( $existing ) ) {
			$query->set(
				'tax_query',
				array(
					'relation' => 'AND',
					$existing,
					$new_group,
				)
			);
			return;
		}

		$query->set( 'tax_query', $new_group );
	}

	private function build_merged_meta_query( \WP_Query $query, $widget, array $rule ) {
		$clauses = array();

		foreach ( (array) $rule['meta_filters'] as $filter ) {
			$target_key = isset( $filter['target_key'] ) ? sanitize_text_field( $filter['target_key'] ) : '';
			$compare    = isset( $filter['compare'] ) ? strtoupper( $filter['compare'] ) : '=';
			$compare    = in_array( $compare, $this->allowed_meta_compares, true ) ? $compare : '=';
			$type       = isset( $filter['type'] ) ? strtoupper( $filter['type'] ) : 'CHAR';
			$type       = in_array( $type, $this->allowed_meta_types, true ) ? $type : 'CHAR';
			$source     = isset( $filter['source'] ) ? sanitize_key( $filter['source'] ) : 'static';
			$source_key = isset( $filter['source_key'] ) ? sanitize_text_field( $filter['source_key'] ) : '';
			$value      = $this->context_resolver->value(
				$source,
				$source_key,
				isset( $filter['value'] ) ? $filter['value'] : '',
				$widget,
				$query
			);

			if ( ! $target_key ) {
				continue;
			}

			if ( in_array( $compare, array( 'EXISTS', 'NOT EXISTS' ), true ) ) {
				$clauses[] = array(
					'key'     => $target_key,
					'compare' => $compare,
					'type'    => $type,
				);
				continue;
			}

			if ( $this->is_empty_value( $value ) ) {
				if ( isset( $filter['empty_behavior'] ) && 'no_results' === $filter['empty_behavior'] ) {
					$query->set( 'post__in', array( 0 ) );
				}
				continue;
			}

			if ( in_array( $compare, array( 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN' ), true ) ) {
				$value = is_array( $value ) ? array_values( $value ) : $this->split_values( $value );
			}

			if ( 'ACF_CONTAINS' === $compare ) {
				$acf_values = is_array( $value ) ? $value : array( $value );
				$acf_group  = array( 'relation' => 'OR' );

				foreach ( $acf_values as $acf_value ) {
					$acf_group[] = array(
						'key'     => $target_key,
						'value'   => '"' . trim( (string) $acf_value, '"' ) . '"',
						'compare' => 'LIKE',
						'type'    => $type,
					);
				}

				$clauses[] = $acf_group;
				continue;
			}

			$clauses[] = array(
				'key'     => $target_key,
				'value'   => $value,
				'compare' => $compare,
				'type'    => $type,
			);
		}

		$existing = $query->get( 'meta_query' );
		$groups   = array();

		if ( is_array( $existing ) && ! empty( $existing ) ) {
			$groups[] = $existing;
		}

		if ( ! empty( $clauses ) ) {
			$groups[] = array_merge(
				array( 'relation' => $this->relation( $rule['meta_relation'] ) ),
				$clauses
			);
		}

		if ( empty( $groups ) ) {
			return array();
		}

		if ( 1 === count( $groups ) ) {
			return $groups[0];
		}

		return array_merge( array( 'relation' => 'AND' ), $groups );
	}

	private function apply_sorting( \WP_Query $query, array &$meta_query, array $rule ) {
		$sort = isset( $rule['sort'] ) && is_array( $rule['sort'] ) ? $rule['sort'] : array();

		if ( empty( $sort['enabled'] ) ) {
			return;
		}

		$key      = isset( $sort['key'] ) ? sanitize_text_field( $sort['key'] ) : '';
		$type     = isset( $sort['type'] ) ? strtoupper( $sort['type'] ) : 'NUMERIC';
		$type     = in_array( $type, $this->allowed_meta_types, true ) ? $type : 'NUMERIC';
		$order    = isset( $sort['order'] ) && 'ASC' === strtoupper( $sort['order'] ) ? 'ASC' : 'DESC';
		$missing  = isset( $sort['missing'] ) ? $sort['missing'] : 'last';
		$fallback = isset( $sort['fallback'] ) && in_array( $sort['fallback'], array( 'date', 'title', 'ID', 'menu_order' ), true ) ? $sort['fallback'] : 'date';

		if ( ! $key ) {
			return;
		}

		if ( 'exclude' === $missing ) {
			$sort_clause = array(
				'elgqr_sort_value' => array(
					'key'     => $key,
					'compare' => 'EXISTS',
					'type'    => $type,
				),
			);
		} else {
			$sort_clause = array(
				'relation'          => 'OR',
				'elgqr_sort_value'   => array(
					'key'     => $key,
					'compare' => 'EXISTS',
					'type'    => $type,
				),
				'elgqr_sort_missing' => array(
					'key'     => $key,
					'compare' => 'NOT EXISTS',
				),
			);
		}

		if ( empty( $meta_query ) ) {
			$meta_query = array( $sort_clause );
		} else {
			$meta_query = array(
				'relation' => 'AND',
				$meta_query,
				$sort_clause,
			);
		}

		if ( 'exclude' === $missing ) {
			$query->set( 'orderby', array( 'elgqr_sort_value' => $order, $fallback => 'DESC' ) );
			return;
		}

		$query->set(
			'orderby',
			array(
				'elgqr_sort_value' => $order,
				$fallback          => 'DESC',
			)
		);

		$this->force_missing_values_last( $query );
	}

	private function force_missing_values_last( \WP_Query $target_query ) {
		$filter = null;
		$filter = static function ( $clauses, $current_query ) use ( $target_query, &$filter ) {
			if ( $current_query !== $target_query ) {
				return $clauses;
			}

			remove_filter( 'posts_clauses', $filter, 20 );

			if ( ! isset( $current_query->meta_query ) || ! $current_query->meta_query instanceof \WP_Meta_Query ) {
				return $clauses;
			}

			$meta_clauses = $current_query->meta_query->get_clauses();
			if ( empty( $meta_clauses['elgqr_sort_value']['alias'] ) ) {
				return $clauses;
			}

			$alias = preg_replace( '/[^a-zA-Z0-9_]/', '', $meta_clauses['elgqr_sort_value']['alias'] );
			if ( ! $alias ) {
				return $clauses;
			}

			$clauses['orderby'] = "CASE WHEN {$alias}.meta_id IS NULL THEN 1 ELSE 0 END ASC, " . $clauses['orderby'];
			return $clauses;
		};

		add_filter( 'posts_clauses', $filter, 20, 2 );
	}

	private function split_values( $value ) {
		if ( is_array( $value ) ) {
			return array_values( array_filter( array_map( 'strval', $value ), 'strlen' ) );
		}

		$parts = preg_split( '/[\r\n,]+/', (string) $value );
		return array_values( array_filter( array_map( 'trim', $parts ), 'strlen' ) );
	}

	private function relation( $value ) {
		return 'OR' === strtoupper( (string) $value ) ? 'OR' : 'AND';
	}

	private function is_empty_value( $value ) {
		return null === $value || '' === $value || array() === $value;
	}
}
