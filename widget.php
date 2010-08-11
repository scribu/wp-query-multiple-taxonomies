<?php

class Taxonomy_Drill_Down_Widget extends scbWidget {

	protected $defaults = array(
		'title' => '',
		'mode' => 'lists',
		'taxonomies' => array(),
	);

	static function init() {
		add_action( 'load-widgets.php', array( __CLASS__, '_init' ) );
	}

	function _init() {
		add_action( 'admin_print_styles', array( __CLASS__, 'add_style' ), 11 );
	}

	function Taxonomy_Drill_Down_Widget() {
		$widget_ops = array( 'description' => 'Display a drill-down navigation based on custom taxonomies', );

		$this->WP_Widget( 'taxonomy-drill-down', 'Taxonomy Drill-Down', $widget_ops );
	}

	function add_style() {
?>
<style type="text/css">
.qmt-taxonomies { margin: -.5em 0 0 .5em }
</style>
<?php
	}

	function form( $instance ) {
		if ( empty( $instance ) )
			$instance = $this->defaults;

		echo 
		html( 'p',
			$this->input( array(
				'name'  => 'title',
				'type'  => 'text',
				'desc' => __( 'Title:', 'query-multiple-taxonomies' ),
				'extra' => array( 'class' => 'widefat' )
			), $instance )
		);

		echo
		html( 'p',
			$this->input( array(
				'type'   => 'select',
				'name'   => 'mode',
				'values' => array( 
					'lists' => __( 'lists', 'query-multiple-taxonomies' ),
					'dropdowns' => __( 'dropdowns', 'query-multiple-taxonomies' ),
				),
				'text'   => false,
				'desc'   => __( 'Mode:', 'query-multiple-taxonomies' ),
				'extra' => array( 'class' => 'widefat' )
			), $instance )
		);

		echo html( 'p', __( 'Taxonomies:', 'query-multiple-taxonomies' ) );


		$tax_list = array();
		foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $tax_name => $tax_obj )
			if ( qmt_get_query_var( $tax_name ) )
				$tax_list[ $tax_name ] = $tax_obj;

		$list = '';
		foreach ( $tax_list as $tax_name => $tax_obj ) {
			$ptypes = sprintf( _n( 'Post type: %s', 'Post types: %s', count( $tax_obj->object_type ), 'query-multiple-taxonomies' ),
				implode( ', ', $tax_obj->object_type )
			);

			$list .= 
			html( 'li', array( 'title' => $ptypes ), $this->input( array(
				'type'   => 'checkbox',
				'name'   => 'taxonomies[]',
				'value' => $tax_name,
				'checked' => in_array( $tax_name, (array) @$instance['taxonomies'] ),
				'desc'   => $tax_obj->label,
			), $instance ) );
		}
		echo html( 'ul class="qmt-taxonomies"', $list );
	}

	function content( $instance ) {
		extract( $instance );
	
		if ( empty( $taxonomies ) )
			echo html( 'p', __( 'No taxonomies selected!', 'query-multiple-taxonomies' ) );
		else
			call_user_func( array( __CLASS__, "generate_$mode" ), $taxonomies );
	}

	private static function generate_dropdowns( $taxonomies ) {
		$out = '';
		foreach ( $taxonomies as $taxonomy ) {
			$terms = qmt_get_terms( $taxonomy );

			if ( empty( $terms ) )
				continue;

			$out .= 
			html( 'li', 
				 get_taxonomy( $taxonomy )->label . ': ' 
				.scbForms::input( array(
					'type' => 'select',
					'name' => $taxonomy,
					'values' => scbUtil::objects_to_assoc( $terms, 'slug', 'name' ),
					'selected' => qmt_get_query( $taxonomy ),
				) )
			);
		}

		if ( empty( $out ) )
			return;

		$reset_url = apply_filters( 'qmt_reset_url', QMT_URL::get_base() );

		echo 
		html( 'form action="' . QMT_URL::get_base() . '" method="get"',
			 html( 'ul', $out )
			."<input type='submit' value='Submit' />\n"
			.html_link( $reset_url, __( 'Reset', 'query-multiple-taxonomies' ) )
		);
	}

	private static function generate_lists( $taxonomies ) {
		$query = qmt_get_query();

		foreach ( $taxonomies as $taxonomy ) {
			$list = qmt_walk_terms( $taxonomy );

			if ( empty( $list ) )
				continue;

			$title = get_taxonomy( $taxonomy )->label;
			if ( isset( $query[$taxonomy] ) ) {
				$title .= ' ' . html( 'a', array( 
					'href' => QMT_URL::for_tax( $taxonomy, '' ), 
					'title' => __( 'Remove all terms in group', 'query-multiple-taxonomies' ) )
				, '(-)' );
			}
			$title = html( 'h4', $title );
			$title = apply_filters( 'qmt_term_list_title', $title, $taxonomy, $query );

			$out = $title;

			$out .= html( "ul class='term-list'", $list );

			echo html( "div id='term-list-$taxonomy'", $out );
		}
	}
}

function qmt_walk_terms( $taxonomy, $args = '' ) {
	if ( !taxonomy_exists( $taxonomy ) ) {
		trigger_error( "Invalid taxonomy '$taxonomy'", E_USER_WARNING );
		return '';
	}

	$terms = qmt_get_terms( $taxonomy );

	if ( empty( $terms ) )
		return '';

	$walker = new QMT_Term_Walker( $taxonomy );

	$args = wp_parse_args( $args, array( 
		'style' => 'list',
		'use_desc_for_title' => false,
		'addremove' => true, 
	) );

	return $walker->walk( $terms, 0, $args );
}


class QMT_Term_Walker extends Walker_Category {

	public $tree_type = 'term';

	private $taxonomy;
	private $query;

	public $selected_terms = array();

	function __construct( $taxonomy ) {
		$this->taxonomy = $taxonomy;

		$this->selected_terms = explode( '+', qmt_get_query( $taxonomy ) );
	}

	function start_el( &$output, $term, $depth, $args ) {
		extract( $args );

		$class = 'term-item term-item-' . $term->term_id;
		if ( in_array( $term->slug, $this->selected_terms ) )
			$class .= 'current-term';

		$output .= "\t<li class='$class'>" . $this->get_addremove_link( $term ) . "\n";
	}

	private function get_addremove_link( $term ) {
		$tmp = $this->selected_terms;
		$i = array_search( $term->slug, $tmp );

		if ( false === $i ) {
			$tmp[] = $term->slug;
			$new_url = esc_url( QMT_URL::for_tax( $this->taxonomy, $tmp ) );

			$title = __( 'Add term', 'query-multiple-taxonomies' );

			$link = "<a href='$new_url' title='$title'>$term->name</a>";
			$link = apply_filters( 'qmt_add_term_link', $link, $new_url, $term );
		} else {
			unset( $tmp[$i] );
			$new_url = esc_url( QMT_URL::for_tax( $this->taxonomy, $tmp ) );

			$title = __( 'Remove term', 'query-multiple-taxonomies' );

			$link = "$term->name <a href='$new_url' title='$title'>(-)</a>";
			$link = apply_filters( 'qmt_remove_term_link', $link, $new_url, $term );
		}

		return $link;
	}
}

