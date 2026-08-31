<?php

namespace ELGQR\Admin;

use ELGQR\Rule_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin {
	private $repository;

	public function __construct( Rule_Repository $repository ) {
		$this->repository = $repository;
	}

	public function register() {
		add_action( 'add_meta_boxes_' . Rule_Repository::POST_TYPE, array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . Rule_Repository::POST_TYPE, array( $this, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'manage_' . Rule_Repository::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . Rule_Repository::POST_TYPE . '_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
		add_filter( 'plugin_action_links_' . plugin_basename( ELGQR_FILE ), array( $this, 'plugin_action_links' ) );
	}

	public function plugin_action_links( $links ) {
		$manage_url = admin_url( 'edit.php?post_type=' . Rule_Repository::POST_TYPE );
		$manage     = '<a href="' . esc_url( $manage_url ) . '">' . esc_html__( 'Settings / Query ID Rules', 'query-id-rules-for-elementor-loop-grid' ) . '</a>';

		array_unshift( $links, $manage );
		return $links;
	}

	public function add_meta_boxes() {
		add_meta_box(
			'elgqr-identity',
			__( 'Query ID', 'query-id-rules-for-elementor-loop-grid' ),
			array( $this, 'render_identity' ),
			Rule_Repository::POST_TYPE,
			'normal',
			'high'
		);
		add_meta_box(
			'elgqr-taxonomy',
			__( 'Taxonomy filters', 'query-id-rules-for-elementor-loop-grid' ),
			array( $this, 'render_taxonomy' ),
			Rule_Repository::POST_TYPE,
			'normal',
			'default'
		);
		add_meta_box(
			'elgqr-composition',
			__( 'Query ID Rule composition', 'query-id-rules-for-elementor-loop-grid' ),
			array( $this, 'render_composition' ),
			Rule_Repository::POST_TYPE,
			'normal',
			'high'
		);
		add_meta_box(
			'elgqr-meta',
			__( 'ACF / custom field filters', 'query-id-rules-for-elementor-loop-grid' ),
			array( $this, 'render_meta' ),
			Rule_Repository::POST_TYPE,
			'normal',
			'default'
		);
		add_meta_box(
			'elgqr-empty-result',
			__( 'Empty results visibility', 'query-id-rules-for-elementor-loop-grid' ),
			array( $this, 'render_empty_result' ),
			Rule_Repository::POST_TYPE,
			'normal',
			'default'
		);
		add_meta_box(
			'elgqr-enabled',
			__( 'Enable this rule', 'query-id-rules-for-elementor-loop-grid' ),
			array( $this, 'render_enabled' ),
			Rule_Repository::POST_TYPE,
			'side',
			'core'
		);
		add_meta_box(
			'elgqr-sort',
			__( 'Sorting', 'query-id-rules-for-elementor-loop-grid' ),
			array( $this, 'render_sort' ),
			Rule_Repository::POST_TYPE,
			'side',
			'default'
		);
		add_meta_box(
			'elgqr-post-attributes',
			__( 'Post attributes', 'query-id-rules-for-elementor-loop-grid' ),
			array( $this, 'render_post_attributes' ),
			Rule_Repository::POST_TYPE,
			'side',
			'low'
		);
	}

	public function enqueue_assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || Rule_Repository::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style( 'elgqr-admin', ELGQR_URL . 'assets/admin.css', array(), ELGQR_VERSION );
		wp_enqueue_script( 'elgqr-admin', ELGQR_URL . 'assets/admin.js', array(), ELGQR_VERSION, true );
	}

	public function render_identity( $post ) {
		$rule = $this->repository->get( $post->ID );
		wp_nonce_field( 'elgqr_save_rule', 'elgqr_nonce' );
		?>
		<label for="elgqr-query-id"><strong><?php esc_html_e( 'Elementor Query ID', 'query-id-rules-for-elementor-loop-grid' ); ?></strong></label>
		<div class="elgqr-copy-row">
			<input id="elgqr-query-id" class="widefat code" type="text" name="elgqr[query_id]" value="<?php echo esc_attr( $rule['query_id'] ); ?>" placeholder="my_loop_filter" pattern="[a-z0-9_-]+">
			<button type="button" class="button" data-elgqr-generate><?php esc_html_e( 'Generate', 'query-id-rules-for-elementor-loop-grid' ); ?></button>
			<button type="button" class="button" data-elgqr-copy><?php esc_html_e( 'Copy', 'query-id-rules-for-elementor-loop-grid' ); ?></button>
		</div>
		<p class="description"><?php esc_html_e( 'Paste this value into Loop Grid → Query → Query ID. It is made unique automatically when saved.', 'query-id-rules-for-elementor-loop-grid' ); ?></p>
		<?php
	}

	public function render_enabled( $post ) {
		$rule = $this->repository->get( $post->ID );
		?>
		<label>
			<input type="checkbox" name="elgqr[enabled]" value="1" <?php checked( ! empty( $rule['enabled'] ) ); ?>>
			<strong><?php esc_html_e( 'Enable this rule', 'query-id-rules-for-elementor-loop-grid' ); ?></strong>
		</label>
		<p class="description"><?php esc_html_e( 'Only published and enabled rules are registered.', 'query-id-rules-for-elementor-loop-grid' ); ?></p>
		<?php
	}

	public function render_empty_result( $post ) {
		$rule         = $this->repository->get( $post->ID );
		$empty_result = wp_parse_args(
			isset( $rule['empty_result'] ) && is_array( $rule['empty_result'] ) ? $rule['empty_result'] : array(),
			array(
				'enabled'         => 1,
				'target_selector' => '',
			)
		);
		?>
		<p>
			<label>
				<input type="checkbox" name="elgqr[empty_result][enabled]" value="1" <?php checked( ! empty( $empty_result['enabled'] ) ); ?>>
				<strong><?php esc_html_e( 'Hide a target when this Query ID returns no results', 'query-id-rules-for-elementor-loop-grid' ); ?></strong>
			</label>
		</p>
		<p>
			<label for="elgqr-empty-target-selector"><strong><?php esc_html_e( 'Target CSS selector (optional)', 'query-id-rules-for-elementor-loop-grid' ); ?></strong></label>
			<input id="elgqr-empty-target-selector" class="widefat code" type="text" name="elgqr[empty_result][target_selector]" value="<?php echo esc_attr( $empty_result['target_selector'] ); ?>" placeholder=".my-empty-tab">
		</p>
		<p class="description">
			<?php esc_html_e( 'Leave the selector empty to automatically hide the Elementor Nested Tabs button whose panel contains this Loop Grid. Enter a selector only when another element should be hidden. The rule runs after Elementor finishes the initial query.', 'query-id-rules-for-elementor-loop-grid' ); ?>
		</p>
		<?php
	}

	public function render_post_attributes( $post ) {
		$rule = $this->repository->get( $post->ID );
		?>
		<p><strong><?php esc_html_e( 'Loop Grid post type', 'query-id-rules-for-elementor-loop-grid' ); ?></strong></p>
		<p class="description"><?php esc_html_e( 'Leave all unchecked to preserve the Post Type selected in Elementor Loop Grid.', 'query-id-rules-for-elementor-loop-grid' ); ?></p>
		<div class="elgqr-post-types elgqr-post-types--side">
			<?php foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $post_type ) : ?>
				<?php if ( 'attachment' === $post_type->name ) { continue; } ?>
				<label>
					<input type="checkbox" name="elgqr[post_types][]" value="<?php echo esc_attr( $post_type->name ); ?>" <?php checked( in_array( $post_type->name, (array) $rule['post_types'], true ) ); ?>>
					<?php echo esc_html( $post_type->labels->singular_name ); ?>
				</label>
			<?php endforeach; ?>
		</div>
		<?php
	}

	public function render_taxonomy( $post ) {
		$rule       = $this->repository->get( $post->ID );
		$taxonomies = get_taxonomies( array( 'show_ui' => true ), 'objects' );
		$rows       = ! empty( $rule['tax_filters'] ) ? $rule['tax_filters'] : array( array() );
		?>
		<div class="elgqr-toolbar">
			<label><?php esc_html_e( 'Relationship between taxonomy rows', 'query-id-rules-for-elementor-loop-grid' ); ?>
				<select name="elgqr[tax_relation]">
					<option value="AND" <?php selected( $rule['tax_relation'], 'AND' ); ?>>AND</option>
					<option value="OR" <?php selected( $rule['tax_relation'], 'OR' ); ?>>OR</option>
				</select>
			</label>
			<button type="button" class="button button-secondary" data-elgqr-add="tax"><?php esc_html_e( 'Add taxonomy filter', 'query-id-rules-for-elementor-loop-grid' ); ?></button>
		</div>
		<div class="elgqr-rows" data-elgqr-rows="tax">
			<?php foreach ( $rows as $index => $row ) { $this->taxonomy_row( $index, $row, $taxonomies ); } ?>
		</div>
		<template data-elgqr-template="tax"><?php $this->taxonomy_row( '__INDEX__', array(), $taxonomies ); ?></template>
		<p class="description"><?php esc_html_e( 'Enter Term IDs only, separated with commas. IN means any selected term; AND means every selected term. With Polylang, enter the canonical/default-language Term IDs and they are translated automatically.', 'query-id-rules-for-elementor-loop-grid' ); ?></p>
		<?php
	}

	public function render_composition( $post ) {
		$rule          = $this->repository->get( $post->ID );
		$selected_ids  = array_map( 'absint', (array) $rule['included_rule_ids'] );
		$available_ids = get_posts(
			array(
				'post_type'      => Rule_Repository::POST_TYPE,
				'post_status'    => 'publish',
				'lang'           => '',
				'posts_per_page' => -1,
				'post__not_in'   => array( (int) $post->ID ),
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		?>
		<p><strong><?php esc_html_e( 'Include the results of other Query ID Rules', 'query-id-rules-for-elementor-loop-grid' ); ?></strong></p>
		<p class="description">
			<?php esc_html_e( 'Selected rules are combined as a union (OR). This rule\'s own post type, taxonomy, and ACF conditions remain the common conditions (AND). Example: TAB_ALL = common main-category conditions AND (TAB_A OR TAB_B).', 'query-id-rules-for-elementor-loop-grid' ); ?>
		</p>
		<?php if ( empty( $available_ids ) ) : ?>
			<p><?php esc_html_e( 'Publish TAB_A and TAB_B rules first; they will then appear here.', 'query-id-rules-for-elementor-loop-grid' ); ?></p>
		<?php else : ?>
			<div class="elgqr-rule-picker">
				<?php foreach ( $available_ids as $available_id ) : ?>
					<?php $available_rule = $this->repository->get( $available_id ); ?>
					<label>
						<input type="checkbox" name="elgqr[included_rule_ids][]" value="<?php echo esc_attr( $available_id ); ?>" <?php checked( in_array( $available_id, $selected_ids, true ) ); ?>>
						<strong><?php echo esc_html( get_the_title( $available_id ) ); ?></strong>
						<code><?php echo esc_html( $available_rule['query_id'] ); ?></code>
						<?php if ( empty( $available_rule['enabled'] ) ) : ?><span class="elgqr-disabled"><?php esc_html_e( 'Disabled', 'query-id-rules-for-elementor-loop-grid' ); ?></span><?php endif; ?>
					</label>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<?php
	}

	private function taxonomy_row( $index, $row, $taxonomies ) {
		$row = wp_parse_args(
			$row,
			array(
				'taxonomy'         => '',
				'terms'            => '',
				'operator'         => 'IN',
				'include_children' => 1,
			)
		);
		$name = 'elgqr[tax_filters][' . $index . ']';
		?>
		<div class="elgqr-row elgqr-row--tax">
			<label><?php esc_html_e( 'Taxonomy', 'query-id-rules-for-elementor-loop-grid' ); ?>
				<select name="<?php echo esc_attr( $name ); ?>[taxonomy]">
					<option value=""><?php esc_html_e( 'Select…', 'query-id-rules-for-elementor-loop-grid' ); ?></option>
					<?php foreach ( $taxonomies as $taxonomy ) : ?>
						<option value="<?php echo esc_attr( $taxonomy->name ); ?>" <?php selected( $row['taxonomy'], $taxonomy->name ); ?>><?php echo esc_html( $taxonomy->label . ' (' . $taxonomy->name . ')' ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label class="elgqr-grow"><?php esc_html_e( 'Term IDs', 'query-id-rules-for-elementor-loop-grid' ); ?>
				<input type="text" inputmode="numeric" name="<?php echo esc_attr( $name ); ?>[terms]" value="<?php echo esc_attr( $row['terms'] ); ?>" placeholder="144, 165, 162">
			</label>
			<label><?php esc_html_e( 'Operator', 'query-id-rules-for-elementor-loop-grid' ); ?>
				<select name="<?php echo esc_attr( $name ); ?>[operator]">
					<?php foreach ( array( 'IN', 'AND', 'NOT IN' ) as $operator ) : ?>
						<option value="<?php echo esc_attr( $operator ); ?>" <?php selected( $row['operator'], $operator ); ?>><?php echo esc_html( $operator ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label class="elgqr-check">
				<input type="hidden" name="<?php echo esc_attr( $name ); ?>[include_children]" value="0">
				<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[include_children]" value="1" <?php checked( ! empty( $row['include_children'] ) ); ?>>
				<?php esc_html_e( 'Children', 'query-id-rules-for-elementor-loop-grid' ); ?>
			</label>
			<button type="button" class="button-link-delete" data-elgqr-remove aria-label="<?php esc_attr_e( 'Remove row', 'query-id-rules-for-elementor-loop-grid' ); ?>">×</button>
		</div>
		<?php
	}

	public function render_meta( $post ) {
		$rule = $this->repository->get( $post->ID );
		$rows = ! empty( $rule['meta_filters'] ) ? $rule['meta_filters'] : array( array() );
		?>
		<div class="elgqr-toolbar">
			<label><?php esc_html_e( 'Relationship between field rows', 'query-id-rules-for-elementor-loop-grid' ); ?>
				<select name="elgqr[meta_relation]">
					<option value="AND" <?php selected( $rule['meta_relation'], 'AND' ); ?>>AND</option>
					<option value="OR" <?php selected( $rule['meta_relation'], 'OR' ); ?>>OR</option>
				</select>
			</label>
			<button type="button" class="button button-secondary" data-elgqr-add="meta"><?php esc_html_e( 'Add ACF / field filter', 'query-id-rules-for-elementor-loop-grid' ); ?></button>
		</div>
		<div class="elgqr-rows" data-elgqr-rows="meta">
			<?php foreach ( $rows as $index => $row ) { $this->meta_row( $index, $row ); } ?>
		</div>
		<template data-elgqr-template="meta"><?php $this->meta_row( '__INDEX__', array() ); ?></template>
		<p class="description"><?php esc_html_e( 'Current page reads from a singular page/post. Current archive term reads from the taxonomy term being viewed and works with Current Query. ACF serialized contains is intended for Checkbox, Relationship, and other serialized arrays.', 'query-id-rules-for-elementor-loop-grid' ); ?></p>
		<?php
	}

	private function meta_row( $index, $row ) {
		$row = wp_parse_args(
			$row,
			array(
				'target_key'     => '',
				'source'         => 'static',
				'value'          => '',
				'source_key'     => '',
				'compare'        => '=',
				'type'           => 'CHAR',
				'empty_behavior' => 'skip',
			)
		);
		$name = 'elgqr[meta_filters][' . $index . ']';
		?>
		<div class="elgqr-row elgqr-row--meta">
			<label><?php esc_html_e( 'Target field', 'query-id-rules-for-elementor-loop-grid' ); ?>
				<input type="text" name="<?php echo esc_attr( $name ); ?>[target_key]" value="<?php echo esc_attr( $row['target_key'] ); ?>" placeholder="target_field_name">
			</label>
			<label><?php esc_html_e( 'Value source', 'query-id-rules-for-elementor-loop-grid' ); ?>
				<select name="<?php echo esc_attr( $name ); ?>[source]" data-elgqr-source>
					<option value="static" <?php selected( $row['source'], 'static' ); ?>><?php esc_html_e( 'Fixed value', 'query-id-rules-for-elementor-loop-grid' ); ?></option>
					<option value="current_post_acf" <?php selected( $row['source'], 'current_post_acf' ); ?>><?php esc_html_e( 'Current page ACF', 'query-id-rules-for-elementor-loop-grid' ); ?></option>
					<option value="current_post_meta" <?php selected( $row['source'], 'current_post_meta' ); ?>><?php esc_html_e( 'Current page meta', 'query-id-rules-for-elementor-loop-grid' ); ?></option>
					<option value="current_term_acf" <?php selected( $row['source'], 'current_term_acf' ); ?>><?php esc_html_e( 'Current archive term ACF', 'query-id-rules-for-elementor-loop-grid' ); ?></option>
					<option value="current_term_meta" <?php selected( $row['source'], 'current_term_meta' ); ?>><?php esc_html_e( 'Current archive term meta', 'query-id-rules-for-elementor-loop-grid' ); ?></option>
				</select>
			</label>
			<label class="elgqr-source-value" data-elgqr-static><?php esc_html_e( 'Fixed value', 'query-id-rules-for-elementor-loop-grid' ); ?>
				<input type="text" name="<?php echo esc_attr( $name ); ?>[value]" value="<?php echo esc_attr( $row['value'] ); ?>" placeholder="filter value">
			</label>
			<label class="elgqr-source-value" data-elgqr-dynamic><?php esc_html_e( 'Source field', 'query-id-rules-for-elementor-loop-grid' ); ?>
				<input type="text" name="<?php echo esc_attr( $name ); ?>[source_key]" value="<?php echo esc_attr( $row['source_key'] ); ?>" placeholder="source_field_name">
			</label>
			<label><?php esc_html_e( 'Compare', 'query-id-rules-for-elementor-loop-grid' ); ?>
				<select name="<?php echo esc_attr( $name ); ?>[compare]">
					<?php foreach ( $this->meta_compare_options() as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $row['compare'], $value ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label><?php esc_html_e( 'Type', 'query-id-rules-for-elementor-loop-grid' ); ?>
				<select name="<?php echo esc_attr( $name ); ?>[type]">
					<?php foreach ( array( 'CHAR', 'NUMERIC', 'DECIMAL', 'SIGNED', 'UNSIGNED', 'DATE', 'DATETIME', 'TIME', 'BINARY' ) as $type ) : ?>
						<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $row['type'], $type ); ?>><?php echo esc_html( $type ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label><?php esc_html_e( 'If source is empty', 'query-id-rules-for-elementor-loop-grid' ); ?>
				<select name="<?php echo esc_attr( $name ); ?>[empty_behavior]">
					<option value="skip" <?php selected( $row['empty_behavior'], 'skip' ); ?>><?php esc_html_e( 'Skip this row', 'query-id-rules-for-elementor-loop-grid' ); ?></option>
					<option value="no_results" <?php selected( $row['empty_behavior'], 'no_results' ); ?>><?php esc_html_e( 'Return no results', 'query-id-rules-for-elementor-loop-grid' ); ?></option>
				</select>
			</label>
			<button type="button" class="button-link-delete" data-elgqr-remove aria-label="<?php esc_attr_e( 'Remove row', 'query-id-rules-for-elementor-loop-grid' ); ?>">×</button>
		</div>
		<?php
	}

	private function meta_compare_options() {
		return array(
			'='             => '=',
			'!='            => '!=',
			'>'             => '>',
			'>='            => '>=',
			'<'             => '<',
			'<='            => '<=',
			'LIKE'          => 'LIKE',
			'NOT LIKE'      => 'NOT LIKE',
			'IN'            => 'IN',
			'NOT IN'        => 'NOT IN',
			'BETWEEN'       => 'BETWEEN',
			'NOT BETWEEN'   => 'NOT BETWEEN',
			'EXISTS'        => 'EXISTS',
			'NOT EXISTS'    => 'NOT EXISTS',
			'ACF_CONTAINS'  => __( 'ACF serialized contains', 'query-id-rules-for-elementor-loop-grid' ),
		);
	}

	public function render_sort( $post ) {
		$rule = $this->repository->get( $post->ID );
		$sort = wp_parse_args(
			(array) $rule['sort'],
			array(
				'enabled'  => 0,
				'key'      => '',
				'type'     => 'NUMERIC',
				'order'    => 'DESC',
				'missing'  => 'last',
				'fallback' => 'date',
			)
		);
		?>
		<p><label><input type="checkbox" name="elgqr[sort][enabled]" value="1" <?php checked( ! empty( $sort['enabled'] ) ); ?>> <?php esc_html_e( 'Enable custom-field sorting', 'query-id-rules-for-elementor-loop-grid' ); ?></label></p>
		<p><label><?php esc_html_e( 'Field name', 'query-id-rules-for-elementor-loop-grid' ); ?><input class="widefat" type="text" name="elgqr[sort][key]" value="<?php echo esc_attr( $sort['key'] ); ?>" placeholder="custom_order_field"></label></p>
		<p><label><?php esc_html_e( 'Value type', 'query-id-rules-for-elementor-loop-grid' ); ?>
			<select class="widefat" name="elgqr[sort][type]">
				<?php foreach ( array( 'NUMERIC', 'DECIMAL', 'SIGNED', 'UNSIGNED', 'CHAR', 'DATE', 'DATETIME' ) as $type ) : ?>
					<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $sort['type'], $type ); ?>><?php echo esc_html( $type ); ?></option>
				<?php endforeach; ?>
			</select>
		</label></p>
		<p><label><?php esc_html_e( 'Direction', 'query-id-rules-for-elementor-loop-grid' ); ?>
			<select class="widefat" name="elgqr[sort][order]"><option value="DESC" <?php selected( $sort['order'], 'DESC' ); ?>>DESC</option><option value="ASC" <?php selected( $sort['order'], 'ASC' ); ?>>ASC</option></select>
		</label></p>
		<p><label><?php esc_html_e( 'Posts without a value', 'query-id-rules-for-elementor-loop-grid' ); ?>
			<select class="widefat" name="elgqr[sort][missing]"><option value="last" <?php selected( $sort['missing'], 'last' ); ?>><?php esc_html_e( 'Keep, after valued posts', 'query-id-rules-for-elementor-loop-grid' ); ?></option><option value="exclude" <?php selected( $sort['missing'], 'exclude' ); ?>><?php esc_html_e( 'Exclude', 'query-id-rules-for-elementor-loop-grid' ); ?></option></select>
		</label></p>
		<p><label><?php esc_html_e( 'Fallback order', 'query-id-rules-for-elementor-loop-grid' ); ?>
			<select class="widefat" name="elgqr[sort][fallback]">
				<?php foreach ( array( 'date' => __( 'Date', 'query-id-rules-for-elementor-loop-grid' ), 'title' => __( 'Title', 'query-id-rules-for-elementor-loop-grid' ), 'ID' => 'ID', 'menu_order' => __( 'Menu order', 'query-id-rules-for-elementor-loop-grid' ) ) as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $sort['fallback'], $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</label></p>
		<?php
	}

	public function save( $post_id, $post ) {
		if ( ! isset( $_POST['elgqr_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['elgqr_nonce'] ) ), 'elgqr_save_rule' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$input = isset( $_POST['elgqr'] ) && is_array( $_POST['elgqr'] ) ? wp_unslash( $_POST['elgqr'] ) : array();
		$title = $post instanceof \WP_Post ? $post->post_title : '';
		$query_id = isset( $input['query_id'] ) ? $input['query_id'] : $title;
		$query_id = $this->repository->unique_query_id( $query_id, $post_id );

		$config = array(
			'id'            => (int) $post_id,
			'query_id'      => $query_id,
			'enabled'       => ! empty( $input['enabled'] ),
			'post_types'    => $this->sanitize_post_types( isset( $input['post_types'] ) ? $input['post_types'] : array() ),
			'tax_relation'  => $this->sanitize_relation( isset( $input['tax_relation'] ) ? $input['tax_relation'] : 'AND' ),
			'tax_filters'   => $this->sanitize_tax_filters( isset( $input['tax_filters'] ) ? $input['tax_filters'] : array() ),
			'meta_relation' => $this->sanitize_relation( isset( $input['meta_relation'] ) ? $input['meta_relation'] : 'AND' ),
			'meta_filters'  => $this->sanitize_meta_filters( isset( $input['meta_filters'] ) ? $input['meta_filters'] : array() ),
			'included_rule_ids' => $this->sanitize_rule_ids( isset( $input['included_rule_ids'] ) ? $input['included_rule_ids'] : array(), $post_id ),
			'sort'          => $this->sanitize_sort( isset( $input['sort'] ) ? $input['sort'] : array() ),
			'empty_result'  => $this->sanitize_empty_result( isset( $input['empty_result'] ) ? $input['empty_result'] : array() ),
		);

		update_post_meta( $post_id, Rule_Repository::CONFIG_META, $config );
		update_post_meta( $post_id, Rule_Repository::QUERY_META, $query_id );
		update_post_meta( $post_id, Rule_Repository::ENABLED_META, $config['enabled'] ? '1' : '0' );
		$this->repository->forget( $post_id );
	}

	private function sanitize_post_types( $values ) {
		$values = is_array( $values ) ? $values : array();
		$values = array_values( array_unique( array_filter( array_map( 'sanitize_key', $values ) ) ) );
		return $values;
	}

	private function sanitize_tax_filters( $rows ) {
		$clean = array();
		foreach ( (array) $rows as $row ) {
			if ( ! is_array( $row ) || empty( $row['taxonomy'] ) ) {
				continue;
			}
			$operator = isset( $row['operator'] ) ? strtoupper( $row['operator'] ) : 'IN';
			$term_ids = preg_split( '/[\s,]+/', isset( $row['terms'] ) ? (string) $row['terms'] : '', -1, PREG_SPLIT_NO_EMPTY );
			$term_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $term_ids ) ) ) );
			$clean[] = array(
				'taxonomy'         => sanitize_key( $row['taxonomy'] ),
				'field'            => 'term_id',
				'terms'            => ! empty( $term_ids ) ? implode( ', ', $term_ids ) : '0',
				'operator'         => in_array( $operator, array( 'IN', 'AND', 'NOT IN' ), true ) ? $operator : 'IN',
				'include_children' => ! empty( $row['include_children'] ) ? 1 : 0,
			);
		}
		return $clean;
	}

	private function sanitize_meta_filters( $rows ) {
		$clean            = array();
		$allowed_compares = array_keys( $this->meta_compare_options() );
		$allowed_types    = array( 'CHAR', 'NUMERIC', 'DECIMAL', 'SIGNED', 'UNSIGNED', 'DATE', 'DATETIME', 'TIME', 'BINARY' );
		$allowed_sources  = array( 'static', 'current_post_acf', 'current_post_meta', 'current_term_acf', 'current_term_meta' );

		foreach ( (array) $rows as $row ) {
			if ( ! is_array( $row ) || empty( $row['target_key'] ) ) {
				continue;
			}
			$compare = isset( $row['compare'] ) ? strtoupper( $row['compare'] ) : '=';
			$type    = isset( $row['type'] ) ? strtoupper( $row['type'] ) : 'CHAR';
			$source  = isset( $row['source'] ) ? sanitize_key( $row['source'] ) : 'static';

			$clean[] = array(
				'target_key'     => sanitize_text_field( $row['target_key'] ),
				'source'         => in_array( $source, $allowed_sources, true ) ? $source : 'static',
				'value'          => isset( $row['value'] ) ? sanitize_text_field( $row['value'] ) : '',
				'source_key'     => isset( $row['source_key'] ) ? sanitize_text_field( $row['source_key'] ) : '',
				'compare'        => in_array( $compare, $allowed_compares, true ) ? $compare : '=',
				'type'           => in_array( $type, $allowed_types, true ) ? $type : 'CHAR',
				'empty_behavior' => isset( $row['empty_behavior'] ) && 'no_results' === $row['empty_behavior'] ? 'no_results' : 'skip',
			);
		}
		return $clean;
	}

	private function sanitize_sort( $sort ) {
		$sort = is_array( $sort ) ? $sort : array();
		$type = isset( $sort['type'] ) ? strtoupper( $sort['type'] ) : 'NUMERIC';
		return array(
			'enabled'  => ! empty( $sort['enabled'] ) ? 1 : 0,
			'key'      => isset( $sort['key'] ) ? sanitize_text_field( $sort['key'] ) : '',
			'type'     => in_array( $type, array( 'NUMERIC', 'DECIMAL', 'SIGNED', 'UNSIGNED', 'CHAR', 'DATE', 'DATETIME' ), true ) ? $type : 'NUMERIC',
			'order'    => isset( $sort['order'] ) && 'ASC' === strtoupper( $sort['order'] ) ? 'ASC' : 'DESC',
			'missing'  => isset( $sort['missing'] ) && 'exclude' === $sort['missing'] ? 'exclude' : 'last',
			'fallback' => isset( $sort['fallback'] ) && in_array( $sort['fallback'], array( 'date', 'title', 'ID', 'menu_order' ), true ) ? $sort['fallback'] : 'date',
		);
	}

	private function sanitize_empty_result( $empty_result ) {
		$empty_result = is_array( $empty_result ) ? $empty_result : array();
		$selector     = isset( $empty_result['target_selector'] ) ? sanitize_text_field( $empty_result['target_selector'] ) : '';
		$selector     = substr( trim( $selector ), 0, 500 );

		return array(
			'enabled'         => ! empty( $empty_result['enabled'] ) ? 1 : 0,
			'target_selector' => $selector,
		);
	}

	private function sanitize_rule_ids( $ids, $post_id ) {
		$clean = array();

		foreach ( array_unique( array_map( 'absint', (array) $ids ) ) as $rule_id ) {
			if ( ! $rule_id || (int) $post_id === $rule_id || Rule_Repository::POST_TYPE !== get_post_type( $rule_id ) ) {
				continue;
			}
			$clean[] = $rule_id;
		}

		return $clean;
	}

	private function sanitize_relation( $value ) {
		return 'OR' === strtoupper( (string) $value ) ? 'OR' : 'AND';
	}

	public function columns( $columns ) {
		$columns['elgqr_query_id'] = __( 'Query ID', 'query-id-rules-for-elementor-loop-grid' );
		$columns['elgqr_status']   = __( 'Rule status', 'query-id-rules-for-elementor-loop-grid' );
		return $columns;
	}

	public function column_content( $column, $post_id ) {
		$rule = $this->repository->get( $post_id );
		if ( 'elgqr_query_id' === $column ) {
			echo '<code>' . esc_html( $rule['query_id'] ) . '</code>';
		}
		if ( 'elgqr_status' === $column ) {
			echo ! empty( $rule['enabled'] ) ? esc_html__( 'Enabled', 'query-id-rules-for-elementor-loop-grid' ) : esc_html__( 'Disabled', 'query-id-rules-for-elementor-loop-grid' );
		}
	}
}
