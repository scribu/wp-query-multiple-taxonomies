<?php

class QMT_Query {

	public static function get( $tax = '', $wp_query = null ) {
		if ( !$wp_query )
			$wp_query = $GLOBALS['wp_query'];

		$query = @$wp_query->_qmt_query;

		if ( !empty( $tax ) )
			return @$query[ $tax ];

		return $query;
	}

	function init() {
		add_action( 'pre_get_posts', array( __CLASS__, 'pre_get_posts' ), 9 );
	}

	function pre_get_posts( $wp_query ) {
		self::set_query( $wp_query );

		// Set post type, only if not set explicitly
		$wp_query->query = wp_parse_args( $wp_query->query );
		if ( !isset( $wp_query->query['post_type'] ) )
			$wp_query->set( 'post_type', 'any' );

		// Prevent normal taxonomy processing
		foreach ( array( 'category_name', 'tag' ) as $qv )
			$wp_query->set( $qv, '' );

		$wp_query->parse_query_vars();

		$wp_query->is_tax = false;

		add_filter( 'posts_where', array( __CLASS__, 'posts_where' ), 10, 2 );
	}

	function posts_where( $where, $wp_query ) {
		global $wpdb;

		if ( empty( $wp_query->_qmt_query ) )
			return $where;

		$post_ids = self::get_post_ids( $wp_query );

		if ( !empty( $post_ids ) )
			$where .= " AND $wpdb->posts.ID IN ( " . implode( ', ', $post_ids ) . " )";
		else
			$where = " AND 0 = 1";

		return $where;
	}

	private function set_query( $wp_query ) {
		$query = array();
		foreach ( get_taxonomies( array( 'public' => true ) ) as $taxname ) {
			if ( ! $qv = qmt_get_query_var( $taxname ) )
				continue;

			if ( ! $value = $wp_query->get( $qv ) )
				continue;

			$value = end( explode( '/', $value ) );

			$query[$taxname] = str_replace( ' ', '+', $value );
		}
		$query = array_filter( $query );

		if ( self::is_regular_query( $query ) )
			return;

		$wp_query->_qmt_query = $query;	
	}

	// Wether the current query can be handled natively by WordPress
	private function is_regular_query( $query ) {
		if ( empty( $query ) )
			return true;

		if ( count( $query ) > 1 )
			return false;

		$tax = key( $query );
		$term = reset( $query );

		if ( 'post_tag' == $tax )
			return true;

		return
			in_array( $tax, get_object_taxonomies( 'post' ) ) &&
			false === strpos( $term, ',' ) &&
			false === strpos( $term, '+' );
	}

	private function get_post_ids( $wp_query = null ) {
		global $wpdb;

		if ( !$wp_query )
			$wp_query = $GLOBALS['wp_query'];

		if ( empty( $wp_query->_qmt_query ) )
			return;

		ksort( $wp_query->_qmt_query );
		$cache_key = serialize( $wp_query->_qmt_query );

		$post_ids = wp_cache_get( $cache_key, 'qmt_post_ids' );

		if ( is_array( $post_ids ) )
			return $post_ids;

		$query = array();
		foreach ( $wp_query->_qmt_query as $taxname => $value )
			foreach ( explode( '+', $value ) as $value )
				$query[] = wp_tax( $taxname, explode( ',', $value ), 'slug' );

		$post_ids = $wpdb->get_col( wp_tax_query( wp_tax_group( 'AND', $query ) ) );

		wp_cache_add( $cache_key, $post_ids, 'qmt_post_ids' );

		return $post_ids;
	}
}


function qmt_get_terms( $tax ) {
	if ( is_category() || is_tag() || is_tax() || is_multitax() )
		return QMT_Terms::get( $tax );
	else
		return get_terms( $tax );
}

class QMT_Terms {

	private static $filtered_ids;

	// Get a list of all the terms attached to all the posts in the current query
	public static function get( $tax ) {
		global $wp_query, $wpdb;

		self::set_filtered_ids();

		if ( empty( self::$filtered_ids ) )
			return array();

		$terms = $wpdb->get_results( $wpdb->prepare( "
			SELECT *, COUNT( * ) as count
			FROM $wpdb->term_relationships
			JOIN $wpdb->term_taxonomy USING ( term_taxonomy_id )
			JOIN $wpdb->terms USING ( term_id )
			WHERE taxonomy = %s
			AND object_id IN ( " . implode( ',', self::$filtered_ids ) . " )
			GROUP BY term_taxonomy_id
		", $tax ) );

		if ( empty( $terms ) )
			return array();

		return $terms;
	}

	private static function set_filtered_ids() {
		global $wp_query;

		if ( isset( self::$filtered_ids ) )
			return;

		$args = array_merge( $wp_query->query, array(
			'nopaging' => true,
			'caller_get_posts' => true,
			'cache_results' => false,
		) );

		add_filter( 'posts_fields', array( __CLASS__, 'posts_fields' ) );

		$query = new WP_Query();
		$posts = $query->query( $args );

		remove_filter( 'posts_fields', array( __CLASS__, 'posts_fields' ) );

		foreach ( $posts as &$post )
			$post = $post->ID;

		self::$filtered_ids = $posts;
	}

	static function posts_fields( $fields ) {
		return 'ID';
	}
}


class QMT_URL {

	public static function for_tax( $taxonomy, $value ) {
		$query = qmt_get_query();

		if ( empty( $value ) )
			unset( $query[ $taxonomy ] );
		else
			$query[ $taxonomy ] = trim( implode( '+', $value ), '+' );

		return self::get( $query );
	}

	public static function get( $query = array() ) {
		ksort( $query );

		$url = self::get_base();
		foreach ( $query as $taxonomy => $value )
			$url = add_query_arg( qmt_get_query_var( $taxonomy ), $value, $url );

		return apply_filters( 'qmt_url', $url, $query );
	}

	public static function get_base() {
		static $base_url;

		if ( empty( $base_url ) )
			$base_url = apply_filters( 'qmt_base_url', get_bloginfo( 'url' ) );

		return $base_url;
	}
}


class QMT_Template {

	function init() {
		add_action( 'template_redirect', array( __CLASS__, 'template' ), 9 );
	}

	static function template() {
		if ( !is_multitax() )
			return;

		add_filter( 'wp_title', array( __CLASS__, 'set_title' ), 10, 3 );

		remove_action( 'template_redirect', 'redirect_canonical' );

		if ( $template = locate_template( array( 'multitax.php' ) ) ) {
			include $template;
			die;
		}
	}

	static function set_title( $title, $sep, $seplocation = '' ) {
		$newtitle[] = self::get_title();
		$newtitle[] = " $sep ";

		if ( !empty( $title ) )
			$newtitle[] = $title;

		if ( 'right' != $seplocation )
			$newtitle = array_reverse( $newtitle );

		return implode( '', $newtitle );
	}

	public static function get_title() {
		$title = array();
		foreach ( qmt_get_query() as $tax => $value ) {
			$key = get_taxonomy( $tax )->label;

			$value = explode( '+', $value );
			foreach ( $value as &$slug ) {
				if ( $term = get_term_by( 'slug', $slug, $tax ) )
					$slug = $term->name;
			}
			$value = implode( '+', $value );

			$title[] .= "$key: $value";
		}

		return implode( '; ', $title );
	}
}


/**
 * Wether multiple taxonomies are queried
 *
 * @return bool
 */
function is_multitax() {
	return count( qmt_get_query() ) > 1;
}

/**
 * Get the list of selected terms
 *
 * @param string $taxname a certain taxonomy name
 *
 * @return array( taxonomy => query )
 */
function qmt_get_query( $taxname = '' ) {
	return QMT_Query::get( $taxname );
}

/**
 * Get the query var, even for built-in taxonomies
 */
function qmt_get_query_var( $taxname ) {
	$taxobj = get_taxonomy( $taxname );

	if ( $taxobj->query_var )
		return $taxobj->query_var;

	$tmp = array(
		'post_tag' => 'tag',
		'category' => 'category_name'
	);

	if ( isset( $tmp[ $taxname ] ) )
		return $tmp[ $taxname ];

	return false;
}

