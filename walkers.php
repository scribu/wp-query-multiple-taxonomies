<?php

class QMT_Walker extends Walker {
	public $tree_type = 'term';

	public $db_fields = array( 'parent' => 'parent', 'id' => 'term_id' );

	protected $taxonomy;
	protected $selected_terms = array();

	function __construct( $taxonomy ) {
		$this->taxonomy = $taxonomy;
		$this->selected_terms = explode( '+', qmt_get_query( $taxonomy ) );
	}

	// Make start_el() and end_el() unnecessary
	function display_element( $element, &$children_elements, $max_depth, $depth=0, $args, &$output ) {

		if ( !$element )
			return;

		$id_field = $this->db_fields['id'];

		$id = $element->$id_field;

		$child_output = '';

		// descend only when the depth is right and there are childrens for this element
		if ( ($max_depth == 0 || $max_depth > $depth+1 ) && isset( $children_elements[$id]) ) {

			foreach ( $children_elements[ $id ] as $child ) {
				$this->display_element( $child, $children_elements, $max_depth, $depth + 1, $args, $child_output );
			}

			unset( $children_elements[ $id ] );
		}

		$this->single_el( $output, $element, $depth, $child_output );
	}
}


class QMT_List_Walker extends QMT_Walker {

	function single_el( &$output, $term, $depth, $child_output ) {
		$tmp = $this->selected_terms;
		$i = array_search( $term->slug, $tmp );

		if ( false === $i ) {
			$tmp[] = $term->slug;

			$data = array(
				'title' => __( 'Add term', 'query-multiple-taxonomies' ),
			);
		} else {
			unset( $tmp[$i] );

			$data = array(
				'title' => __( 'Remove term', 'query-multiple-taxonomies' ),
				'is-selected' => array( true )
			);
		}

		$data = array_merge( $data, array(
			'url' => QMT_URL::for_tax( $this->taxonomy, $tmp ),
			'name' => $term->name,
		) );

		if ( !empty( $child_output ) ) {
			$data['children']['child-list'] = $child_output;
		}

		$output .= Taxonomy_Drill_Down_Widget::mustache_render( 'list-item.html', $data );
	}
}


class QMT_Dropdown_Walker extends QMT_Walker {

	function single_el( &$output, $term, $depth, $child_output ) {
		$data = array(
			'pad' => str_repeat('&nbsp;', $depth * 3),
			'depth' => $depth,
			'is-selected' => in_array( $term->slug, $this->selected_terms ) ? array(true) : false,
			'slug' => $term->slug,
			'name' => apply_filters( 'list_cats', $term->name, $term ),
		);

#		if ( $args['show_count'] )
#			$output .= '&nbsp;&nbsp;('. $term->count .')';

		if ( !empty( $child_output ) ) {
			$data['children']['child-list'] = $child_output;
		}

		$output .= Taxonomy_Drill_Down_Widget::mustache_render( 'dropdown-item.html', $data );
	}
}

