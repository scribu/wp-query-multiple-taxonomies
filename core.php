<?php

class QMT_Hooks {

	// Transform ?qmt[category][]=5&qmt[category][]=6 into something usable
	function request( $request ) {
		if ( !isset( $_GET['qmt'] ) )
			return $request;

		foreach ( $_GET['qmt'] as $taxonomy => $terms ) {
			$request['tax_query'][] = array(
				'taxonomy' => $taxonomy,
				'terms' => $terms,
				'field' => 'term_id',
				'operator' => 'IN'
			);
		}

		return $request;
	}

	// Add the selected terms to the title
	function wp_title( $title, $sep, $seplocation ) {
		$tax_title = QMT_Template::get_title();

		if ( empty( $tax_title ) )
			return $title;

		if ( 'right' == $seplocation )
			$title = "$tax_title $sep ";
		else
			$title = " $sep $tax_title";

		return $title;
	}

	function wp_head() {
?>
<style type="text/css">
.taxonomy-drilldown-lists p,
.taxonomy-drilldown-checkboxes p,
.taxonomy-drilldown-dropdowns p {
	margin-top: 1em;
}

.taxonomy-drilldown-checkboxes li,
.taxonomy-drilldown-dropdowns li {
	list-style: none;
}

.taxonomy-drilldown-dropdowns select {
	display: block;
}
</style>
<?php
	}
}
scbHooks::add( 'QMT_Hooks' );


class QMT_Terms {

	private static $filtered_ids;

	// Get a list of all the terms attached to all the posts in the current query
	public function get( $tax ) {
		self::set_filtered_ids();

		if ( empty( self::$filtered_ids ) )
			return array();

		$raw_terms = wp_get_object_terms( self::$filtered_ids, $tax );

		// distinct terms
		$terms = array();
		foreach ( $raw_terms as $term )
			$terms[ $term->term_id ] = $term;

		return $terms;
	}

	private function set_filtered_ids() {
		global $wp_query;

		if ( isset( self::$filtered_ids ) )
			return;

		$args = array_merge( $wp_query->query, array(
			'fields' => 'ids',
			'nopaging' => true,
			'no_found_rows' => true,
			'ignore_sticky_post' => true,
			'cache_results' => false,
		) );

		$query = new WP_Query;

		self::$filtered_ids = $query->query( $args );
	}
}


class QMT_Count {

	// Count posts, without getting them
	public function get( $query_vars ) {
		$query_vars = array_merge( $query_vars, array(
			'fields' => 'ids',
			'qmt_count' => true,
			'nopaging' => true
		) );

		$query = new WP_Query( $query_vars );

		if ( !empty( $query->posts ) )
			return $query->posts[0];

		return 0;
	}

	function posts_clauses( $bits, $wp_query ) {
		if ( $wp_query->get( 'qmt_count' ) ) {
			if ( empty( $bits['groupby'] ) ) {
				$what = '*';
			} else {
				$what = 'DISTINCT ' . $bits['groupby'];
				$bits['groupby'] = '';
			}

			$bits['fields'] = "COUNT($what)";
		}

		return $bits;
	}
}
add_filter( 'posts_clauses', array( 'QMT_Count', 'posts_clauses' ), 10, 2 );


class QMT_URL {

	public function for_tax( $taxonomy, $value ) {
		$query = qmt_get_query();

		if ( empty( $value ) )
			unset( $query[ $taxonomy ] );
		else
			$query[ $taxonomy ] = trim( implode( '+', $value ), '+' );

		return self::get( $query );
	}

	public function get( $query = array() ) {
		$url = self::get_base();

		if ( empty($query) )
			return apply_filters( 'qmt_reset_url', $url );

		ksort( $query );

		foreach ( $query as $taxonomy => $value )
			$url = add_query_arg( get_taxonomy( $taxonomy )->query_var, $value, $url );

		return apply_filters( 'qmt_url', $url, $query );
	}

	public function get_base() {
		static $base_url;

		if ( empty( $base_url ) )
			$base_url = apply_filters( 'qmt_base_url', get_bloginfo( 'url' ) );

		return trailingslashit( $base_url );
	}
}


class QMT_Template {

	public function get_title() {
		$title = array();

		foreach ( qmt_get_query() as $tax => $value ) {
			$terms = preg_split( '/[+,]+/', $value );

			$out = array();
			foreach ( $terms as $slug ) {
				$term_obj = get_term_by( 'slug', $slug, $tax );

				if ( $term_obj )
					$out[] = $term_obj->name;
			}

			$tax_obj = get_taxonomy( $tax );
			if ( count( $out ) == 1 )
				$key = $tax_obj->labels->singular_name;
			else
				$key = $tax_obj->labels->name;

			$title[] .= $key . ': ' . implode( ' + ', $out );
		}

		return implode( '; ', $title );
	}
}

/**
 * Wether multiple taxonomies are queried
 * @param array $taxonomies A list of taxonomies to check for (AND).
 *
 * @return bool
 */
function is_multitax( $taxonomies = array() ) {
	$queried = array_keys( qmt_get_query() );
	$count = count( $taxonomies );

	if ( !$count )
		return count( $queried ) > 1;

	return count( array_intersect( $queried, $taxonomies) ) == $count;
}

/**
 * Get the list of selected terms
 *
 * @param string $taxname a certain taxonomy name
 *
 * @return array( taxonomy => query )
 */
function qmt_get_query( $taxname = '' ) {
	global $wp_query;

	$qmt_query = array();

	if ( !is_null( $wp_query->tax_query ) ) {
		foreach ( $wp_query->tax_query->queries as &$tax_query ) {
			$terms = _qmt_get_term_slugs( $tax_query );

			if ( 'AND' == $tax_query['operator'] )
				$qmt_query[ $tax_query['taxonomy'] ] = $terms;

			if ( 'IN' == $tax_query['operator'] )
				$qmt_query[ $tax_query['taxonomy'] ][] = implode( ',', $terms );
		}

		foreach ( $qmt_query as &$value )
			$value = implode( '+', $value );
	}

	if ( $taxname ) {
		if ( isset( $qmt_query[ $taxname ] ) )
			return $qmt_query[ $taxname ];

		return false;
	}

	return $qmt_query;
}

// https://core.trac.wordpress.org/ticket/21684
function _qmt_get_term_slugs( &$tax_query ) {
	if ( 'slug' == $tax_query['field'] )
		return $tax_query['terms'];

	$terms = array();

	foreach ( $tax_query['terms'] as $field ) {
		$term = get_term_by( $tax_query['field'], $field, $tax_query['taxonomy'] );
		if ( $term )
			$terms[] = $term->slug;
	}

	$tax_query['field'] = 'slug';
	$tax_query['terms'] = $terms;

	return $terms;
}

// Deprecated
function qmt_get_terms( $tax ) {
	_deprecated_function( __FUNCTION__, '1.4' );

	if ( is_archive() )
		return QMT_Terms::get( $tax );
	else
		return get_terms( $tax );
}

