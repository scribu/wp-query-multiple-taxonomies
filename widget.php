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
			$ptypes = implode( ', ', $tax_obj->object_type );
			$ptypes = __( 'Post types:', 'query-multiple-taxonomies' ) . ' ' . $ptypes;

			$list .= html( 'li', $this->input( array(
				'type'   => 'checkbox',
				'name'   => 'taxonomies[]',
				'value' => $tax_name,
				'checked' => in_array( $tax_name, (array) @$instance['taxonomies'] ),
				'desc'   => html( 'span', array( 'title' => $ptypes ), $tax_obj->label ),
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

		echo html( 'form action="' . QMT_URL::get_base() . '" method="get"',
			 html( 'ul', $out )
			."<input type='submit' value='Submit' />\n"
			.html_link( apply_filters( 'qmt_reset_url', QMT_URL::get_base() ), __( 'Reset', 'query-multiple-taxonomies' ) )
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
				$new_url = QMT_URL::for_tax( $taxonomy, '' );
				$title .= ' ' . html( "a class='clear-taxonomy' href='$new_url'", '(-)' );
			}

			$out = '';
			if ( ! empty( $title ) )
				$out .= html( 'h4', $title );

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

		if ( !$use_desc_for_title || empty( $term->description ) )
			$title = sprintf( __( 'View all posts filed under %s', 'query-multiple-taxonomies' ), esc_attr( $term->name ) );
		else
			$title = esc_attr( strip_tags( $term->description ) );

		$link = html( "a href='" . get_term_link( $term, $this->taxonomy ) . "' title='$title'", $term->name );

		if ( isset( $addremove ) && $addremove )
			$link .= $this->get_addremove_link( $term );

		if ( isset( $show_count ) && $show_count )
			$link .= ' ( ' . intval( $term->count ) . ' )';

		if ( 'list' == $style ) {
			$output .= "\t<li";
			$class = 'term-item term-item-'.$term->term_id;
			if ( in_array( $term->slug, $this->selected_terms ) )
				$class .=  ' current-term';
//			elseif ( $term->term_id == $_current_term->parent )
//				$class .=  ' current-term-parent';
			$output .=  ' class="'.$class.'"';
			$output .= ">$link\n";
		} else {
			$output .= "\t$link<br />\n";
		}
	}

	private function get_addremove_link( $term ) {
		$tmp = $this->selected_terms;
		$i = array_search( $term->slug, $tmp );

		if ( false !== $i ) {
			unset( $tmp[$i] );

			$new_url = esc_url( QMT_URL::for_tax( $this->taxonomy, $tmp ) );
			$out = html( "a class='remove-term' href='$new_url'", '(-)' );
		}
		else {
			$tmp[] = $term->slug;

			$new_url = esc_url( QMT_URL::for_tax( $this->taxonomy, $tmp ) );
			$out = html( "a class='add-term' href='$new_url'", '(+)' );
		}

		return ' ' . $out;
	}
}

