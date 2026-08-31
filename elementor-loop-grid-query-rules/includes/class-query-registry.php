<?php

namespace ELGQR;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Query_Registry {
	private $repository;

	private $applier;

	public function __construct( Rule_Repository $repository, Query_Applier $applier ) {
		$this->repository = $repository;
		$this->applier    = $applier;
	}

	public function register_rules() {
		$registered = array();

		foreach ( $this->repository->all_enabled() as $rule ) {
			if ( isset( $registered[ $rule['query_id'] ] ) ) {
				continue;
			}

			$registered[ $rule['query_id'] ] = true;
			$hook    = 'elementor/query/' . $rule['query_id'];
			$applier = $this->applier;

			add_action(
				$hook,
				static function ( $query, $widget = null ) use ( $applier, $rule ) {
					$applier->apply( $query, $widget, $rule );
				},
				10,
				2
			);
		}
	}
}
