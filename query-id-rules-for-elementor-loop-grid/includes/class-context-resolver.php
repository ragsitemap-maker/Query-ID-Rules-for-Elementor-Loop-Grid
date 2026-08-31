<?php

namespace ELGQR;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Context_Resolver {
	private $rest_post_id = 0;

	private $value_cache = array();

	private $context_segment = 0;

	public function register_rest_tracking() {
		add_filter( 'rest_request_before_callbacks', array( $this, 'capture_rest_context' ), 10, 3 );
		add_filter( 'rest_request_after_callbacks', array( $this, 'clear_rest_context' ), 10, 3 );
	}

	public function capture_rest_context( $response, $handler, $request ) {
		if ( $request instanceof \WP_REST_Request ) {
			$post_id = absint( $request->get_param( 'post_id' ) );
			if ( $post_id ) {
				$this->reset_value_cache();
				$this->rest_post_id = $post_id;
			}
		}

		return $response;
	}

	public function clear_rest_context( $response ) {
		$this->rest_post_id = 0;
		$this->reset_value_cache();
		return $response;
	}

	public function explicit_post_id() {
		$candidates = array(
			$this->rest_post_id,
			isset( $_REQUEST['post_id'] ) && is_scalar( $_REQUEST['post_id'] ) ? absint( wp_unslash( $_REQUEST['post_id'] ) ) : 0,
			isset( $_GET['elementor-preview'] ) && is_scalar( $_GET['elementor-preview'] ) ? absint( wp_unslash( $_GET['elementor-preview'] ) ) : 0,
			is_singular() ? get_queried_object_id() : 0,
		);

		foreach ( $candidates as $candidate ) {
			if ( $candidate ) {
				return (int) $candidate;
			}
		}

		return 0;
	}

	public function current_post_id( $widget = null ) {
		$post_id = $this->explicit_post_id();
		if ( $post_id ) {
			return $post_id;
		}

		// A normal archive has no current page post. Avoid treating its shared
		// Elementor Archive Template as the page that supplies an ACF value.
		if ( ! $this->is_elementor_request_context() ) {
			return 0;
		}

		$candidates = array();

		if ( $widget && is_object( $widget ) && method_exists( $widget, 'get_document' ) ) {
			$document = $widget->get_document();
			if ( $document && method_exists( $document, 'get_main_id' ) ) {
				$candidates[] = absint( $document->get_main_id() );
			}
		}

		if ( class_exists( '\\Elementor\\Plugin' ) && isset( \Elementor\Plugin::$instance->documents ) ) {
			$document = \Elementor\Plugin::$instance->documents->get_current();
			if ( $document && method_exists( $document, 'get_main_id' ) ) {
				$candidates[] = absint( $document->get_main_id() );
			}
		}

		foreach ( $candidates as $candidate ) {
			if ( $candidate ) {
				return (int) $candidate;
			}
		}

		return 0;
	}

	public function value( $source, $source_key, $static_value, $widget = null, $query = null ) {
		if ( 'static' === $source ) {
			return $static_value;
		}

		if ( in_array( $source, array( 'current_term_acf', 'current_term_meta' ), true ) ) {
			$term = $this->current_term( $query );
			if ( ! $term instanceof \WP_Term || ! $source_key ) {
				return null;
			}

			$cache_key = implode(
				'|',
				array( $this->context_segment, 'term', $term->taxonomy, (int) $term->term_id, $source, $source_key )
			);

			if ( array_key_exists( $cache_key, $this->value_cache ) ) {
				return $this->value_cache[ $cache_key ];
			}

			$this->value_cache[ $cache_key ] = 'current_term_acf' === $source && function_exists( 'get_field' )
				? get_field( $source_key, $term->taxonomy . '_' . $term->term_id, false )
				: get_term_meta( $term->term_id, $source_key, true );

			return $this->value_cache[ $cache_key ];
		}

		$post_id = $this->current_post_id( $widget );
		if ( ! $post_id || ! $source_key ) {
			return null;
		}

		$cache_key = implode(
			'|',
			array( $this->context_segment, 'post', $post_id, $source, $source_key )
		);

		if ( array_key_exists( $cache_key, $this->value_cache ) ) {
			return $this->value_cache[ $cache_key ];
		}

		$this->value_cache[ $cache_key ] = 'current_post_acf' === $source && function_exists( 'get_field' )
			? get_field( $source_key, $post_id, false )
			: get_post_meta( $post_id, $source_key, true );

		return $this->value_cache[ $cache_key ];
	}

	private function reset_value_cache() {
		$this->value_cache = array();
		++$this->context_segment;
	}

	private function current_term( $query = null ) {
		if ( $query instanceof \WP_Query ) {
			$term = $query->get_queried_object();
			if ( $term instanceof \WP_Term ) {
				return $term;
			}
		}

		$term = get_queried_object();
		return $term instanceof \WP_Term ? $term : null;
	}

	private function is_elementor_request_context() {
		if ( $this->rest_post_id || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || wp_doing_ajax() ) {
			return true;
		}

		if (
			isset( $_GET['elementor-preview'] )
			|| ( isset( $_REQUEST['action'] ) && false !== strpos( sanitize_key( wp_unslash( $_REQUEST['action'] ) ), 'elementor' ) )
		) {
			return true;
		}

		return class_exists( '\\Elementor\\Plugin' )
			&& isset( \Elementor\Plugin::$instance->editor )
			&& \Elementor\Plugin::$instance->editor->is_edit_mode();
	}
}
