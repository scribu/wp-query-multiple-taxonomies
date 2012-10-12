<?php

class Taxonomy_Drill_Down_Widget extends scbWidget {

	protected $defaults = array(
		'title' => '',
		'mode' => 'lists',
		'taxonomies' => array(),
	);

	static function init( $file ) {
		parent::init( __CLASS__, $file, 'taxonomy-drill-down' );

		if ( !class_exists( 'Mustache' ) )
			require dirname(__FILE__) . '/mustache/Mustache.php';

		add_action( 'load-widgets.php', array( __CLASS__, '_init' ) );
	}

	static function _init() {
		add_action( 'admin_print_styles', array( __CLASS__, 'add_style' ), 11 );
		add_action( 'admin_footer', array( __CLASS__, 'add_script' ), 11 );
	}

	function __construct() {
		parent::__construct(
			'taxonomy-drill-down',
			__( 'Taxonomy Drill-Down', 'query-multiple-taxonomies' ),
			array(
				'description' => __( 'Display a drill-down navigation based on custom taxonomies', 'query-multiple-taxonomies' )
			)
		);
	}

	function add_style() {
?>
<style type="text/css">
.qmt-taxonomies {
	margin: -.5em 0 0 .5em;
	cursor: move;
}
</style>
<?php
	}

	function add_script() {
?>
<script type="text/javascript">
jQuery(function($){
	$(document).delegate('.qmt-taxonomies', 'mouseenter', function(ev) {
		$(this).sortable();
		$(this).disableSelection();
	});
});
</script>
<?php
	}

	function form( $instance ) {
		if ( empty( $instance ) )
			$instance = $this->defaults;

		$data = array(
			'title-input' => $this->input( array(
				'name'  => 'title',
				'type'  => 'text',
				'desc' => __( 'Title:', 'query-multiple-taxonomies' ),
				'extra' => array( 'class' => 'widefat' )
			), $instance ),

			'mode-input' => $this->input( array(
				'type'   => 'select',
				'name'   => 'mode',
				'values' => array(
					'lists' =>      __( 'lists', 'query-multiple-taxonomies' ),
					'checkboxes' => __( 'checkboxes', 'query-multiple-taxonomies' ),
					'dropdowns' =>  __( 'dropdowns', 'query-multiple-taxonomies' ),
				),
				'text'   => false,
				'desc'   => __( 'Mode:', 'query-multiple-taxonomies' ),
				'extra' => array( 'class' => 'widefat' )
			), $instance ),

			'taxonomies-label' => __( 'Taxonomies:', 'query-multiple-taxonomies' )
		);

		$selected_taxonomies = $instance['taxonomies'];

		// Start with the selected taxonomies
		$tax_list = $selected_taxonomies;

		// Append the other taxonomies
		foreach ( get_taxonomies() as $tax_name ) {
			if ( !in_array( $tax_name, $selected_taxonomies ) )
				$tax_list[] = $tax_name;
		}

		foreach ( $tax_list as $tax_name ) {
			$tax_obj = self::test_tax( $tax_name );

			if ( !$tax_obj )
				continue;

			$data['taxonomies'][] = array(
				'title' => sprintf( _n( 'Post type: %s', 'Post types: %s', count( $tax_obj->object_type ), 'query-multiple-taxonomies' ), implode( ', ', $tax_obj->object_type ) ),
				'input' => $this->input( array(
					'type'   => 'checkbox',
					'name'   => 'taxonomies[]',
					'value'  => $tax_name,
					'checked'=> in_array( $tax_name, $selected_taxonomies ),
					'desc'   => $tax_obj->label,
				) )
			);
		}

		$m = new Mustache;
		echo $m->render( file_get_contents( dirname(__FILE__) . '/widget.html' ), $data );
	}

	function content( $instance ) {
		extract( $instance );

		$taxonomies = array_filter( $taxonomies, array( __CLASS__, 'test_tax' ) );

		$query = qmt_get_query();
		$common = array_intersect( $taxonomies, array_keys( $query ) );
		$this->all_terms = empty( $common );

		if ( empty( $taxonomies ) ) {
			echo
			html( 'p', __( 'No taxonomies selected!', 'query-multiple-taxonomies' ) );
		} else {
			echo call_user_func( array( __CLASS__, "generate_$mode" ), $taxonomies, array(
				'reset-text' => __( 'Reset', 'query-multiple-taxonomies' ),
				'reset-url' => QMT_URL::get(),
			) );
		}
	}

	private function get_terms( $tax ) {
		if ( is_taxonomy_hierarchical( $tax ) || $this->all_terms )
			return get_terms( $tax );
		else
			return QMT_Terms::get( $tax );
	}

	private function generate_lists( $taxonomies, $data ) {
		$query = qmt_get_query();

		foreach ( $taxonomies as $taxonomy ) {
			$terms = $this->get_terms( $taxonomy );

			if ( empty( $terms ) )
				continue;

			$walker = new QMT_List_Walker( $taxonomy, 'list' );

			$data_tax = array(
				'taxonomy' => $taxonomy,
				'title' => get_taxonomy( $taxonomy )->label,
				'term-list' => $walker->walk( $terms, 0 ),
				'any-text' =>  __( 'any', 'query-multiple-taxonomies' )
			);

			if ( isset( $query[$taxonomy] ) ) {
				$data_tax['clear'] = array(
					'url' => QMT_URL::for_tax( $taxonomy, '' ),
					'title' => __( 'Remove selected terms in group', 'query-multiple-taxonomies' )
				);
			}

			$data['taxonomy'][] = $data_tax;
		}

		return self::mustache_render( 'lists.html', $data );
	}

	private function generate_dropdowns( $taxonomies, $data ) {
		$data = array_merge( $data, array(
			'base-url' => QMT_URL::get_base(),
			'submit-text' => __( 'Submit', 'query-multiple-taxonomies' ),
			'any-text' => '&mdash; ' . __( 'any', 'query-multiple-taxonomies' ) . ' &mdash;',
		) );

		foreach ( $taxonomies as $taxonomy ) {
			$terms = get_terms( $taxonomy );

			if ( empty( $terms ) )
				continue;

			$walker = new qmt_dropdown_walker( $taxonomy, 'dropdown' );

			$data['taxonomy'][] = array(
				'name' => get_taxonomy( $taxonomy )->query_var,
				'title' => get_taxonomy( $taxonomy )->label,
				'term-list' => $walker->walk( $terms, 0 )
			);
		}

		if ( empty( $data['taxonomy'] ) )
			return '';

		return self::mustache_render( 'dropdowns.html', $data );
	}

	private function generate_checkboxes( $taxonomies, $data ) {
		$data = array_merge( $data, array(
			'base-url' => QMT_URL::get_base(),
			'submit-text' => __( 'Submit', 'query-multiple-taxonomies' ),
		) );

		foreach ( $taxonomies as $taxonomy ) {
			$terms = $this->get_terms( $taxonomy );

			if ( empty( $terms ) )
				continue;

			$walker = new QMT_Checkboxes_Walker( $taxonomy, 'checkbox' );

			$data['taxonomy'][] = array(
				'taxonomy' => $taxonomy,
				'title' => get_taxonomy( $taxonomy )->label,
				'term-list' => $walker->walk( $terms, 0 )
			);
		}

		return self::mustache_render( 'checkboxes.html', $data );
	}

	static function test_tax( $tax_name ) {
		$tax_obj = get_taxonomy( $tax_name );

		if ( $tax_obj && $tax_obj->public && $tax_obj->query_var )
			return $tax_obj;

		return false;
	}

	static function mustache_render( $file, $data ) {
		$template_path = locate_template( 'qmt-templates/' . $file );
		if ( !$template_path )
			$template_path = dirname(__FILE__) . '/templates/' . $file;

		$m = new Mustache;
		return $m->render( file_get_contents( $template_path ), $data );
	}
}

