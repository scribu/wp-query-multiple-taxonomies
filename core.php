<?php

class QMT_Query {

	public function get( $tax = '', $wp_query = null ) {
		if ( !$wp_query )
			$wp_query = $GLOBALS['wp_query'];

		$query = (array) @$wp_query->_qmt_query;

		if ( !empty( $tax ) )
			return @$query[ $tax ];

		return $query;
	}

	function init() {
		add_action( 'pre_get_posts', array( __CLASS__, 'pre_get_posts' ), 9 );
		add_filter( 'posts_where', array( __CLASS__, 'posts_where' ), 10, 2 );
	}

	function pre_get_posts( $wp_query ) {
		self::find_query( $wp_query );

		if ( $wp_query->_qmt_is_reqular )
			return;

		// Set post type, only if not set explicitly
		$wp_query->query = wp_parse_args( $wp_query->query );
		if ( !isset( $wp_query->query['post_type'] ) )
			$wp_query->set( 'post_type', 'any' );

		// Prevent normal taxonomy processing
		foreach ( array( 'category_name', 'tag' ) as $qv )
			$wp_query->set( $qv, '' );

		$wp_query->parse_query_vars();

		$wp_query->is_tax = false;
	}

	private function find_query( $wp_query ) {
		$query = array();
		foreach ( get_taxonomies( array( 'public' => true ) ) as $taxname ) {
			if ( ! $qv = qmt_get_query_var( $taxname ) )
				continue;

			if ( ! $value = $wp_query->get( $qv ) )
				continue;

			// Trac: #14330
			if ( 'Array' == $value )
				$value = $_REQUEST[	$qv ];

			if ( !is_array( $value ) ) {
				$value = end( explode( '/', $value ) );
				$value = str_replace( ' ', '+', $value );

				if ( false !== strpos($value, '+') )
					$value = array('and' => explode('+', $value));
				elseif ( false !== strpos($value, ',') )
					$value = array('or' => explode(',', $value));
			}

			$query[$taxname] = $value;
		}
		$query = array_filter( $query );

		$wp_query->_qmt_query = $query;
		$wp_query->_qmt_is_reqular = self::is_regular_query( $query );
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

		return !is_array( $term );
	}

	function posts_where( $where, $wp_query ) {
		global $wpdb;

		if ( $wp_query->_qmt_is_reqular )
			return $where;

		$post_ids = self::get_post_ids( $wp_query );

		if ( !empty( $post_ids ) )
			$where .= " AND $wpdb->posts.ID IN ( " . implode( ', ', $post_ids ) . " )";
		else
			$where = " AND 0 = 1";

		return $where;
	}

	private function get_post_ids( $wp_query ) {
		global $wpdb;

		ksort( $wp_query->_qmt_query );
		$cache_key = serialize( $wp_query->_qmt_query );

		$post_ids = wp_cache_get( $cache_key, 'qmt_post_ids' );

		if ( is_array( $post_ids ) )
			return $post_ids;

		$query = array();
		foreach ( $wp_query->_qmt_query as $taxname => $value ) {
			if ( is_array( $value ) ) {
				if ( isset( $value['and'] ) ) {
					foreach ( $value['and'] as $slug ) {
						$query[] = wp_tax( $taxname, $slug, 'slug' );
					}
				}
				if ( isset( $value['or'] ) ) {
					$query[] = wp_tax( $taxname, $value['or'], 'slug' );
				}
			}
			else {
				$query[] = wp_tax( $taxname, $value, 'slug' );
			}
		}

		$post_ids = $wpdb->get_col( wp_tax_query( wp_tax_group( 'AND', $query ) ) );

		wp_cache_add( $cache_key, $post_ids, 'qmt_post_ids' );

		return $post_ids;
	}
}


function qmt_get_terms( $tax ) {
	if ( is_archive() )
		return QMT_Terms::get( $tax );
	else
		return get_terms( $tax );
}

class QMT_Terms {

	private static $filtered_ids;

	// Get a list of all the terms attached to all the posts in the current query
	public function get( $tax ) {
		global $wp_query, $wpdb;

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

		$wp_query->query = wp_parse_args( $wp_query->query );

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

	function posts_fields( $fields ) {
		return 'ID';
	}
}


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
			$url = add_query_arg( qmt_get_query_var( $taxonomy ), $value, $url );

		return apply_filters( 'qmt_url', $url, $query );
	}

	public function get_base() {
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

	function template() {
		global $wp_query;

		if ( $wp_query->_qmt_is_reqular )
			return;

		add_filter( 'wp_title', array( __CLASS__, 'set_title' ), 10, 3 );

		remove_action( 'template_redirect', 'redirect_canonical' );

		if ( $template = locate_template( array( 'taxonomy.php' ) ) ) {
			include $template;
			die;
		}
	}

	function set_title( $title, $sep, $seplocation = '' ) {
		$newtitle[] = self::get_title();
		$newtitle[] = " $sep ";

		if ( !empty( $title ) )
			$newtitle[] = $title;

		if ( 'right' != $seplocation )
			$newtitle = array_reverse( $newtitle );

		return implode( '', $newtitle );
	}

	public function get_title() {
		$title = array();
		foreach ( qmt_get_query() as $tax => $value ) {
			$key = get_taxonomy( $tax )->label;

			if ( is_array( $value ) ) {
				extract( $value );

				if ( isset( $or ) )
					$value = implode( ',', $or );
				elseif ( isset( $and ) )
					$value = implode( '+', $and );
			}

			$title[] .= "$key: $value";
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

