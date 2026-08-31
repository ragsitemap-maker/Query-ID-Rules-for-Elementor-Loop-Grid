<?php

namespace ELGQR;

use ELGQR\Admin\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {
	private static $instance;

	private $repository;

	private $context_resolver;

	private $registry;

	private $empty_result_visibility;

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {}

	public function boot() {
		$this->repository       = new Rule_Repository();
		$this->context_resolver = new Context_Resolver();
		$this->registry         = new Query_Registry(
			$this->repository,
			new Query_Applier(
				$this->context_resolver,
				$this->repository,
				new Polylang_Adapter( $this->context_resolver )
			)
		);
		$this->empty_result_visibility = new Empty_Result_Visibility( $this->repository );

		add_action( 'init', array( $this->repository, 'register_post_type' ), 5 );
		add_action( 'init', array( $this->registry, 'register_rules' ), 30 );
		add_filter( 'pll_get_post_types', array( $this->repository, 'exclude_from_polylang' ) );

		$this->context_resolver->register_rest_tracking();
		$this->empty_result_visibility->register();

		if ( is_admin() ) {
			( new Admin( $this->repository ) )->register();
			add_action( 'admin_notices', array( $this, 'dependency_notice' ) );
		}
	}

	public function dependency_notice() {
		if ( defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			return;
		}

		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'Query ID Rules for Elementor Loop Grid requires Elementor Pro Loop Grid to apply its rules. Rules can still be configured while Elementor Pro is inactive.', 'query-id-rules-for-elementor-loop-grid' );
		echo '</p></div>';
	}
}
