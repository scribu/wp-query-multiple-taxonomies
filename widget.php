<?php

class Taxonomy_Drill_Down_Widget extends scbWidget {

	private static $ptype_list;
	private static $tax_lists;

	static function init() {
		add_action( 'load-widgets.php', array( __CLASS__, '_init' ) );
	}

	function _init() {
		$ptype_list = array();
		$tax_lists = array();

		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $ptype_name => $ptype_obj ) {
			foreach ( get_object_taxonomies( $ptype_name, 'objects' ) as $tax_name => $tax_obj )
				if ( QMT_Core::get_query_var( $tax_name ) )
					$tax_lists[ $ptype_name ][ $tax_name ] = $tax_obj->label;

			if ( isset( $tax_lists[ $ptype_name ] ) )
				$ptype_list[ $ptype_name ] = $ptype_obj->label;
		}

		self::$ptype_list = $ptype_list;
		self::$tax_lists = $tax_lists;

		add_action( 'admin_footer', array( __CLASS__, 'add_script' ), 11 );
	}

	function Taxonomy_Drill_Down_Widget() {
		$this->defaults = array(
			'title' => '',
			'post_type' => 'post',
			'taxonomies' => array(),
		);

		$widget_ops = array( 'description' => 'Display a drill-down navigation based on custom taxonomies', );

		$this->WP_Widget( 'taxonomy-drill-down', 'Taxonomy Drill-Down', $widget_ops );
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

		$out = 
		html( 'p class="qmt-post-type"',
			$this->input( array(
				'type'   => 'select',
				'name'   => 'post_type',
				'values' => self::$ptype_list,
				'text'   => false,
				'desc'   => __( 'Post Type:', 'query-multiple-taxonomies' ),
			), $instance )
		);

		$list = '';
		foreach ( self::$tax_lists[$instance['post_type']] as $tax_name => $tax_label ) {
			$list .= html( 'li', $this->input( array(
				'type'   => 'checkbox',
				'name'   => 'taxonomies[]',
				'value' => $tax_name,
				'checked' => in_array( $tax_name, $instance['taxonomies'] ),
				'desc'   => $tax_label,
			), $instance ) );
		}

		$out .= html( 'ul class="qmt-taxonomies"', $list );

		echo html( 'div class="qmt-dropdowns"', $out );
	}

	function add_script() {
		global $pagenow;

		if ( 'widgets.php' != $pagenow )
			return;

?>
<script type="text/javascript">
jQuery(document).ready(function($) {
	var tax_lists = <?php echo json_encode( self::$tax_lists ); ?>

	$(document).delegate('.qmt-post-type select', 'change', function() {
		var $that = $(this),
			new_ptype = $that.find(':selected').val(),
			$list = $that.parents('.qmt-dropdowns').find('.qmt-taxonomies'),
			checkbox_name = $list.find('input').attr('name');

		$list.html('');
		$.each(tax_lists[new_ptype], function(val, label) {
			$list.append($('<li>')
				.html($('<input>').attr({
					'type': 'checkbox', 
					'name': checkbox_name,
					'value': val,
					'checked': 'checked'
				}))
				.append(' ' + label)
			);
		});
	});
});
</script>
<?php
	}

	function content( $instance ) {
		extract( $instance );
	
		if ( empty( $taxonomies ) ) {
			echo html( 'p', __( 'No taxonomies selected.', 'query-multiple-taxonomies' ) );
		}
		else {
			$query = QMT_Core::get_query();

			foreach ( $taxonomies as $taxonomy ) {
				$list = qmt_walk_terms( $taxonomy );

				if ( empty( $list ) )
					continue;

				$title = get_taxonomy( $taxonomy )->label;

				if ( isset( $query[$taxonomy] ) ) {
					$new_url = QMT_Core::get_url( $taxonomy, '' );
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
}

function qmt_walk_terms( $taxonomy, $args = '' ) {
	if ( !taxonomy_exists( $taxonomy ) ) {
		trigger_error( "Invalid taxonomy '$taxonomy'", E_USER_WARNING );
		return '';
	}

	$terms = QMT_Core::get_terms( $taxonomy );

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

		$this->selected_terms = explode( '+', QMT_Core::get_query( $taxonomy ) );
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

			$new_url = esc_url( QMT_Core::get_url( $this->taxonomy, $tmp ) );
			$out = html( "a class='remove-term' href='$new_url'", '(-)' );
		}
		else {
			$tmp[] = $term->slug;

			$new_url = esc_url( QMT_Core::get_url( $this->taxonomy, $tmp ) );
			$out = html( "a class='add-term' href='$new_url'", '(+)' );
		}

		return ' ' . $out;
	}
}

