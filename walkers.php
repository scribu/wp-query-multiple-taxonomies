<?php

class QMT_Data_Container {
	private $taxonomy;
	private $term;

	private $data = array();

	function __construct( $taxonomy, $term, $data ) {
		$this->taxonomy = $taxonomy;
		$this->term = $term;

		$this->data = $data;
	}

	function __get( $key ) {
		return $this->data[ $key ];
	}

	function __isset( $key ) {
		return isset( $this->data[ $key ] );
	}

	function count() {
        $old_query = qmt_get_query();

        if ( $this->data['is-selected'] ) {
            return $GLOBALS['wp_query']->post_count;
        }

        $query = array(
            'tax_query' => array(
                'relation' => 'AND'
            )
        );
        $count_filter = array();
        //add info from current item
        $count_filter[$this->taxonomy] = array($this->term->slug);
        // Considering previous choices
        foreach ($old_query as $old_taxonomy => $old_terms){
            $terms = explode('+', $old_terms);
            //put it into the count_filter array which we will use to generate our count query
            if (!isset($count_filter[$old_taxonomy])){
                //Assure we have this array
                $count_filter[$old_taxonomy] = $terms;
            }else{
                $count_filter[$old_taxonomy] = array_merge ($count_filter[$old_taxonomy] , $terms);
            }
        }
        // now use all this for the query

        foreach ($count_filter as $tax => $terms){
            $query['tax_query'][] = array (
                'taxonomy' => $tax,
                'field' => 'slug',
                'terms' => $terms,
                'include_children' => 0,
                'operator' => 'AND'
            );
        }

        return QMT_Count::get( $query );
    }
}


abstract class QMT_Walker extends Walker {
	public $tree_type = 'term';
	public $db_fields = array( 'parent' => 'parent', 'id' => 'term_id' );

	protected $taxonomy;
	protected $selected_terms = array();

	protected $walker_type;

	function __construct( $taxonomy, $walker_type ) {
		$this->taxonomy = $taxonomy;
		$this->walker_type = $walker_type;

		$this->set_selected_terms();
	}

	protected function set_selected_terms() {
		$this->selected_terms = explode( '+', qmt_get_query( $this->taxonomy ) );
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

	function single_el( &$output, $term, $depth, $child_output ) {
		$data = $this->specific_data( $term, $depth );

		$data = array_merge( $data, array(
			'term-name' => $term->name,
			'is-selected' => in_array( $term->slug, $this->selected_terms ) ? array(true) : false,
			'depth' => $depth,
		) );

		if ( !empty( $child_output ) ) {
			$data['children']['child-list'] = $child_output;
		}

		$full_data = new QMT_Data_Container( $this->taxonomy, $term, $data );

		$output .= Taxonomy_Drill_Down_Widget::mustache_render( $this->walker_type . '-item.html', $full_data );
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

	protected function set_selected_terms() {
		$this->selected_terms = explode( ',', qmt_get_query( $this->taxonomy ) );
	}

	function specific_data( $term, $depth ) {
		return array(
			'name' => "qmt[$this->taxonomy][]",
			'value' => $term->term_id,
		);
	}
}

