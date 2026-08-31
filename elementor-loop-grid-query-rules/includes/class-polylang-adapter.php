<?php

namespace ELGQR;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Polylang_Adapter {
	private $context_resolver;

	private $translated_term_cache = array();

	public function __construct( Context_Resolver $context_resolver ) {
		$this->context_resolver = $context_resolver;
	}

	public function is_available() {
		return function_exists( 'pll_current_language' )
			&& function_exists( 'pll_get_post_language' )
			&& function_exists( 'pll_get_term' );
	}

	public function apply_current_language( \WP_Query $query, $widget = null ) {
		if ( ! $this->is_available() ) {
			return;
		}

		// Current Query may already contain the language selected by Polylang.
		// Preserve it: a shared Archive Template can belong to another language.
		if ( array_key_exists( 'lang', $query->query_vars ) ) {
			return;
		}

		$tax_query = $query->get( 'tax_query' );
		if ( $this->has_language_taxonomy( $tax_query ) ) {
			return;
		}

		$post_type = $query->get( 'post_type' );
		if ( ! empty( $post_type ) && ! $this->has_translated_post_type( $post_type ) ) {
			return;
		}

		$language = $this->current_language( $widget );
		if ( ! $language ) {
			return;
		}

		$query->set( 'lang', $language );
	}

	public function translate_term_ids( $taxonomy, array $terms, $widget = null ) {
		if (
			! $this->is_available()
			|| ! function_exists( 'pll_is_translated_taxonomy' )
			|| ! pll_is_translated_taxonomy( $taxonomy )
		) {
			return $terms;
		}

		$language = $this->current_language( $widget );
		if ( ! $language ) {
			return $terms;
		}

		$translated = array();
		foreach ( $terms as $term_id ) {
			$term_id   = absint( $term_id );
			$cache_key = $taxonomy . ':' . $term_id . ':' . $language;
			if ( array_key_exists( $cache_key, $this->translated_term_cache ) ) {
				$translated[] = $this->translated_term_cache[ $cache_key ];
				continue;
			}

			$source_term = get_term( $term_id, $taxonomy );

			if ( ! $source_term instanceof \WP_Term ) {
				$this->translated_term_cache[ $cache_key ] = 0;
				$translated[] = 0;
				continue;
			}

			$translated_id = absint( pll_get_term( $source_term->term_id, $language ) );
			if ( ! $translated_id ) {
				$this->translated_term_cache[ $cache_key ] = 0;
				$translated[] = 0;
				continue;
			}

			$translated_term = get_term( $translated_id, $taxonomy );
			if ( ! $translated_term instanceof \WP_Term ) {
				$this->translated_term_cache[ $cache_key ] = 0;
				$translated[] = 0;
				continue;
			}

			$this->translated_term_cache[ $cache_key ] = (int) $translated_term->term_id;
			$translated[] = (int) $translated_term->term_id;
		}

		return $translated;
	}

	public function current_language( $widget = null ) {
		if ( ! $this->is_available() ) {
			return '';
		}

		// The frontend URL/archive context is authoritative. Do not infer the
		// language from a shared Elementor Archive Template document.
		$language = pll_current_language( 'slug' );
		if ( is_string( $language ) && '' !== $language ) {
			return sanitize_key( $language );
		}

		// Elementor REST/editor requests do not always initialize Polylang's
		// current language. Fall back only to an explicit page/request ID.
		$post_id = $this->context_resolver->explicit_post_id();
		if ( $post_id ) {
			$language = pll_get_post_language( $post_id, 'slug' );
			if ( is_string( $language ) && '' !== $language ) {
				return sanitize_key( $language );
			}
		}

		return '';
	}

	private function has_language_taxonomy( $tax_query ) {
		if ( ! is_array( $tax_query ) ) {
			return false;
		}

		foreach ( $tax_query as $clause ) {
			if ( ! is_array( $clause ) ) {
				continue;
			}

			if ( isset( $clause['taxonomy'] ) && 'language' === $clause['taxonomy'] ) {
				return true;
			}

			if ( $this->has_language_taxonomy( $clause ) ) {
				return true;
			}
		}

		return false;
	}

	private function has_translated_post_type( $post_type ) {
		if ( ! function_exists( 'pll_is_translated_post_type' ) ) {
			return true;
		}

		foreach ( (array) $post_type as $type ) {
			if ( 'any' === $type || pll_is_translated_post_type( $type ) ) {
				return true;
			}
		}

		return false;
	}

}
