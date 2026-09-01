<?php

namespace ELGQR;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Empty_Result_Visibility {
	private $repository;

	private $visibility_rules;

	private $empty_records = array();

	private $result_counts = array();

	public function __construct( Rule_Repository $repository ) {
		$this->repository = $repository;
	}

	public function register() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'elementor/query/query_results', array( $this, 'capture_query_result' ), 20, 2 );
		add_action( 'wp_footer', array( $this, 'print_runtime_config' ), 5 );
	}

	public function enqueue_assets() {
		wp_register_style( 'elgqr-frontend', ELGQR_URL . 'assets/frontend.css', array(), ELGQR_VERSION );
		wp_register_script( 'elgqr-frontend', ELGQR_URL . 'assets/frontend.js', array(), ELGQR_VERSION, true );

		if ( empty( $this->visibility_rules() ) ) {
			return;
		}

		// The hide class must be available before Elementor renders to avoid a
		// flash of a target that will be hidden. The footer script is demand-loaded
		// only after a real empty result is captured.
		wp_enqueue_style( 'elgqr-frontend' );
	}

	public function capture_query_result( $query, $widget ) {
		if ( ! $query instanceof \WP_Query || ! $this->is_loop_grid( $widget ) ) {
			return;
		}

		if ( ! method_exists( $widget, 'get_id' ) ) {
			return;
		}

		$widget_id = sanitize_key( $widget->get_id() );

		if ( ! $widget_id ) {
			return;
		}

		$this->result_counts[ $widget_id ] = array(
			'widgetId' => $widget_id,
			'total'    => $this->normalize_result_total( $query ),
		);

		$rule_id = absint( $query->get( 'elgqr_rule_id' ) );

		if ( ! $rule_id ) {
			return;
		}

		$rules = $this->visibility_rules();

		if ( ! isset( $rules[ $rule_id ] ) ) {
			return;
		}

		if ( ! empty( $query->posts ) ) {
			$this->remove_widget_records( $widget_id );
			return;
		}

		$rule         = $rules[ $rule_id ];
		$empty_result = $this->normalize_empty_result( $rule );

		$record_key = $widget_id . '|' . md5( $empty_result['target_selector'] );
		$this->empty_records[ $record_key ] = array(
			'widgetId' => $widget_id,
			'queryId'  => sanitize_key( $rule['query_id'] ),
			'selector' => $empty_result['target_selector'],
		);

		$this->enqueue_frontend_script();
	}

	public function print_runtime_config() {
		if ( empty( $this->empty_records ) ) {
			return;
		}

		$config = wp_json_encode(
			array(
				'records' => array_values( $this->empty_records ),
				'counts'  => array_values( $this->result_counts ),
			),
			JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
		);

		if ( ! $config ) {
			return;
		}

		$this->enqueue_frontend_script();
		wp_add_inline_script( 'elgqr-frontend', 'window.ELGQR_EMPTY_RESULTS = ' . $config . ';', 'before' );
	}

	private function enqueue_frontend_script() {
		if ( ! wp_script_is( 'elgqr-frontend', 'registered' ) ) {
			wp_register_script( 'elgqr-frontend', ELGQR_URL . 'assets/frontend.js', array(), ELGQR_VERSION, true );
		}

		wp_enqueue_script( 'elgqr-frontend' );
	}

	private function remove_widget_records( $widget_id ) {
		foreach ( $this->empty_records as $record_key => $record ) {
			if ( isset( $record['widgetId'] ) && $widget_id === $record['widgetId'] ) {
				unset( $this->empty_records[ $record_key ] );
			}
		}
	}

	private function visibility_rules() {
		if ( null !== $this->visibility_rules ) {
			return $this->visibility_rules;
		}

		$this->visibility_rules = array();

		foreach ( $this->repository->all_enabled() as $rule ) {
			$empty_result = $this->normalize_empty_result( $rule );

			if ( empty( $empty_result['enabled'] ) || empty( $rule['id'] ) ) {
				continue;
			}

			$this->visibility_rules[ absint( $rule['id'] ) ] = $rule;
		}

		return $this->visibility_rules;
	}

	private function normalize_empty_result( array $rule ) {
		$empty_result = isset( $rule['empty_result'] ) && is_array( $rule['empty_result'] ) ? $rule['empty_result'] : array();

		return array(
			'enabled'         => ! empty( $empty_result['enabled'] ),
			'target_selector' => isset( $empty_result['target_selector'] ) ? (string) $empty_result['target_selector'] : '',
		);
	}

	private function normalize_result_total( \WP_Query $query ) {
		if ( empty( $query->posts ) || $query->get( 'no_found_rows' ) ) {
			return null;
		}

		if ( ! isset( $query->found_posts ) || ! is_int( $query->found_posts ) ) {
			return null;
		}

		$post_count = count( $query->posts );

		return $query->found_posts >= $post_count ? $query->found_posts : null;
	}

	private function is_loop_grid( $widget ) {
		return is_object( $widget )
			&& method_exists( $widget, 'get_name' )
			&& 'loop-grid' === $widget->get_name();
	}
}
