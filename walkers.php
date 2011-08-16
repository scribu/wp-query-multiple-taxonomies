<?php

abstract class QMT_Walker extends Walker {
	public $tree_type = 'term';
	public $db_fields = array( 'parent' => 'parent', 'id' => 'term_id' );

	protected $taxonomy;
	protected $selected_terms = array();

	protected $walker_type;

	function __construct( $taxonomy, $walker_type ) {
		$this->taxonomy = $taxonomy;
		$this->selected_terms = explode( '+', qmt_get_query( $taxonomy ) );

		$this->walker_type = $walker_type;
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

	/**
	 * Calculate how many posts exists for each term
	 */
	function get_results_count($term) {
		$old_query = qmt_get_query();

		// Considering previous choices
		if ( array_key_exists( $this->taxonomy, $old_query ) ) {
			$query = $old_query;
			$query[$this->taxonomy] = $query[$this->taxonomy] . "+" . $term->slug;
		} else {
			$query = array_merge( $old_query, array( $this->taxonomy => $term->slug ) );
		}
		$query['posts_per_page'] = '-1';
		$wp_query = new WP_Query( $query );
		$count = $wp_query->post_count;

		return $count;
	}

	function single_el( &$output, $term, $depth, $child_output ) {
		$data = $this->specific_data( $term, $depth );

		$data = array_merge( $data, array(
			'term-name' => $term->name,
			'is-selected' => in_array( $term->slug, $this->selected_terms ) ? array(true) : false,
			'depth' => $depth,
			'count' => $this->get_results_count($term)
		) );

		if ( !empty( $child_output ) ) {
			$data['children']['child-list'] = $child_output;
		}

		$output .= Taxonomy_Drill_Down_Widget::mustache_render( $this->walker_type . '-item.html', $data );
	}

	abstract function specific_data( $term, $depth );
}


class QMT_List_Walker extends QMT_Walker {

	function specific_data( $term, $depth ) {
		$tmp = $this->selected_terms;
		$i = array_search( $term->slug, $tmp );

		if ( false === $i ) {
			$tmp[] = $term->slug;

			$data['title'] = __( 'Add term', 'query-multiple-taxonomies' );
		} else {
			unset( $tmp[$i] );

			$data['title'] = __( 'Remove term', 'query-multiple-taxonomies' );
		}

		$data['url'] = QMT_URL::for_tax( $this->taxonomy, $tmp );

		return $data;
	}
}


class QMT_Dropdown_Walker extends QMT_Walker {

	function specific_data( $term, $depth ) {
		return array(
			'pad' => str_repeat('&nbsp;', $depth * 3),
			'value' => $term->slug,
		);
	}
}


class QMT_Checkboxes_Walker extends QMT_Walker {

	function specific_data( $term, $depth ) {
		return array(
			'name' => "qmt[$this->taxonomy][]",
			'value' => $term->term_id,
		);
	}
}

