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

	static function _init() {
		add_action( 'admin_print_styles', array( __CLASS__, 'add_style' ), 11 );
		add_action( 'admin_footer', array( __CLASS__, 'add_script' ), 11 );
	}

	function Taxonomy_Drill_Down_Widget() {
		$widget_ops = array( 'description' => 'Display a drill-down navigation based on custom taxonomies', );

		$this->WP_Widget( 'taxonomy-drill-down', 'Taxonomy Drill-Down', $widget_ops );
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
	$('.qmt-taxonomies').live('mouseenter', function(ev) {
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
//					'checkboxes' => __( 'checkboxes', 'query-multiple-taxonomies' ),
					'dropdowns' => __( 'dropdowns', 'query-multiple-taxonomies' ),
				),
				'text'   => false,
				'desc'   => __( 'Mode:', 'query-multiple-taxonomies' ),
				'extra' => array( 'class' => 'widefat' )
			), $instance )
		);

		echo html( 'p', __( 'Taxonomies:', 'query-multiple-taxonomies' ) );

		$selected_taxonomies = isset( $instance['taxonomies'] ) ? $instance['taxonomies'] : array();

		// Start with the selected taxonomies
		$tax_list = $selected_taxonomies;

		// Append the other taxonomies
		foreach ( get_taxonomies() as $tax_name ) {
			if ( !in_array( $tax_name, $selected_taxonomies ) )
				$tax_list[] = $tax_name;
		}

		// Display the list
		$list = '';
		foreach ( $tax_list as $tax_name ) {
			$tax_obj = self::test_tax( $tax_name );

			if ( !$tax_obj )
				continue;

			$ptypes = sprintf( _n( 'Post type: %s', 'Post types: %s', count( $tax_obj->object_type ), 'query-multiple-taxonomies' ),
				implode( ', ', $tax_obj->object_type )
			);

			$list .= 
			html( 'li', array( 'title' => $ptypes ), $this->input( array(
				'type'   => 'checkbox',
				'name'   => 'taxonomies[]',
				'value' => $tax_name,
				'checked' => in_array( $tax_name, $selected_taxonomies ),
				'desc'   => $tax_obj->label,
			), $instance ) );
		}
		echo html( 'ul class="qmt-taxonomies"', $list );
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
			echo
			html( "div class='taxonomy-drilldown-$mode'",
				call_user_func( array( __CLASS__, "generate_$mode" ), $taxonomies )
			);
		}
	}

	private function get_terms( $tax ) {
		if ( $this->all_terms )
			return get_terms( $tax );
		else
			return QMT_Terms::get( $tax );
	}

	private function generate_lists( $taxonomies ) {
		$query = qmt_get_query();

		$out = '';
		foreach ( $taxonomies as $taxonomy ) {
			$list = qmt_walk_terms( $taxonomy, $this->get_terms( $taxonomy ) );

			if ( empty( $list ) )
				continue;

			$title = get_taxonomy( $taxonomy )->label;
			if ( isset( $query[$taxonomy] ) ) {
				$title .= ' ' . html( 'a', array(
					'href' => QMT_URL::for_tax( $taxonomy, '' ),
					'title' => __( 'Remove selected terms in group', 'query-multiple-taxonomies' ) )
				, '(-)' );
			}
			$title = html( 'h4', $title );
			$title = apply_filters( 'qmt_term_list_title', $title, $taxonomy, $query );

			$out .=
			html( "div id='term-list-$taxonomy'",
				 $title
				.html( "ul class='term-list'", $list )
			);
		}

		$out .= self::reset_button();

		return $out;
	}

	private function generate_checkboxes( $taxonomies ) {
		$query = qmt_get_query();

		$out = '';
		foreach ( $taxonomies as $taxonomy ) {
			$terms = $this->get_terms( $taxonomy );

			if ( empty( $terms ) )
				continue;

			$title = html( 'h4', get_taxonomy( $taxonomy )->label );
			$title = apply_filters( 'qmt_term_list_title', $title, $taxonomy, $query );

			$list = '';
			foreach ( $terms as $term ) {
				$list .=
				html( 'li', scbForms::input( array(
					'type' => 'checkbox',
					'name' => $taxonomy . '[and][]',
					'value' => $term->slug,
					'desc' => $term->name,
					'checked' => in_array( $term->slug, (array) @$query[ $taxonomy ] )
				)));
			}

			$out .=
			html( "div id='term-list-$taxonomy'",
				 $title
				.html( "ul class='term-list'", $list )
			);
		}

		return $this->make_form( $out );
	}

	private function generate_dropdowns( $taxonomies ) {
		$out = '';
		foreach ( $taxonomies as $taxonomy ) {
			$terms = $this->get_terms( $taxonomy );

			if ( empty( $terms ) )
				continue;

			$name = get_taxonomy( $taxonomy )->query_var;

			$out .=
			html( 'li',
				html( 'label', array( 'for' => $name ), 
					get_taxonomy( $taxonomy )->label . ':' 
				),
				' ',
				html( 'select', array( 'name' => $name ),
					'<option></option>',
					walk_category_dropdown_tree( $terms, 0, array(
						'selected' => qmt_get_query( $taxonomy ),
						'show_count' => false,
						'show_last_update' => false,
						'hierarchical' => true,
						'walker' => new QMT_Dropdown_Walker
					) )
				)
			);
		}

		return $this->make_form( html('ul', $out) );
	}

	private function make_form( $out ) {
		if ( empty( $out ) )
			return '';

		return
		html( 'form', array( 'action' => QMT_URL::get_base(), 'method' => 'get' ),
			$out,
			"<input type='submit' value='Submit' />\n",
			self::reset_button()
		);
	}
	
	private function reset_button() {
		return html( 'a', array(
			'href' => QMT_URL::get(), 
			'class' => 'taxonomy-drilldown-reset',
		), __( 'Reset', 'query-multiple-taxonomies' ) );
	}

	static function test_tax( $tax_name ) {
		$tax_obj = get_taxonomy( $tax_name );
		
		if ( $tax_obj && $tax_obj->public && $tax_obj->query_var )
			return $tax_obj;

		return false;
	}
}

function qmt_walk_terms( $taxonomy, $terms, $args = '' ) {
	if ( empty( $terms ) )
		return '';

	$walker = new QMT_List_Walker( $taxonomy );

	$args = wp_parse_args( $args, array( 
		'style' => 'list',
		'use_desc_for_title' => false,
		'addremove' => true, 
	) );

	return $walker->walk( $terms, 0, $args );
}


class QMT_List_Walker extends Walker_Category {

	public $tree_type = 'term';

	private $taxonomy;
	private $selected_terms = array();

	function __construct( $taxonomy ) {
		$this->taxonomy = $taxonomy;

		$this->selected_terms = explode( '+', qmt_get_query( $taxonomy ) );
	}

	function start_el( &$output, $term, $depth, $args ) {
		extract( $args );

		$class = 'term-item term-item-' . $term->term_id;
		if ( in_array( $term->slug, $this->selected_terms ) )
			$class .= ' current-term';

		$output .= "\t<li class='$class'>" . $this->get_addremove_link( $term ) . "\n";
	}

	private function get_addremove_link( $term ) {
		$tmp = $this->selected_terms;
		$i = array_search( $term->slug, $tmp );

		if ( false === $i ) {
			$tmp[] = $term->slug;
			$new_url = esc_url( QMT_URL::for_tax( $this->taxonomy, $tmp ) );

			$title = __( 'Add term', 'query-multiple-taxonomies' );

			$link = "<a class='add-term' href='$new_url' title='$title'>$term->name (+)</a>";

			$link = apply_filters( 'qmt_add_term_link', $link, $new_url, $term );
		} else {
			unset( $tmp[$i] );
			$new_url = esc_url( QMT_URL::for_tax( $this->taxonomy, $tmp ) );

			$title = __( 'Remove term', 'query-multiple-taxonomies' );

			$link = "<a class='remove-term' href='$new_url' title='$title'>$term->name (-)</a>";

			$link = apply_filters( 'qmt_remove_term_link', $link, $new_url, $term );
		}

		return $link;
	}
}

class QMT_Dropdown_Walker extends Walker_CategoryDropdown {

	function start_el(&$output, $category, $depth, $args) {
		$pad = str_repeat('&nbsp;', $depth * 3);

		$cat_name = apply_filters( 'list_cats', $category->name, $category );
		$output .= "\t<option class=\"level-$depth\" value=\"".$category->slug."\"";
		if ( $category->slug == $args['selected'] )
			$output .= ' selected="selected"';
		$output .= '>';
		$output .= $pad.$cat_name;
		if ( $args['show_count'] )
			$output .= '&nbsp;&nbsp;('. $category->count .')';
		if ( $args['show_last_update'] ) {
			$format = 'Y-m-d';
			$output .= '&nbsp;&nbsp;' . gmdate($format, $category->last_update_timestamp);
		}
		$output .= "</option>\n";
	}
}

