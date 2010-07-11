<?php

class QMT_Core {
	private static $post_ids = null;
	private static $actual_query = array();

	function init() {
		add_action( 'parse_query', array( __CLASS__, 'parse_query' ) );
	}


//_____Query_____


	function get_actual_query( $tax = '' ) {
		if ( !empty( $tax ) )
			return @self::$actual_query[$tax];

		return self::$actual_query;
	}

	function parse_query( $wp_query ) {
		global $wpdb;

		if ( $wp_query !== $GLOBALS['wp_query'] )
			return;

		foreach ( get_taxonomies( array( 'public' => true ) ) as $taxname ) {
			if ( ! $qv = self::get_query_var($taxname) )
				continue;

			if ( ! $value = $wp_query->get( $qv ) )
				continue;

			$value = end( explode( '/', $value ) );

			self::$actual_query[$taxname] = str_replace( ' ', '+', $value );
		}

		if ( empty( self::$actual_query ) )
			return;

		$wp_query->is_multitax = true;

		if ( self::is_regular_query() )
			return;

		self::set_post_ids();

		if ( empty( self::$post_ids ) )
			return $wp_query->set_404();

		$wp_query->is_archive = true;

		$is_feed = $wp_query->is_feed;
		$paged = $wp_query->get( 'paged' );

		$wp_query->init_query_flags();

		$wp_query->is_feed = $is_feed;
		$wp_query->set( 'paged', $paged );

		$wp_query->set( 'post_type', 'any' );
		$wp_query->set( 'post__in', self::$post_ids );

		// Theme integration
		add_action( 'template_redirect', array( __CLASS__, 'template' ) );
		add_filter( 'wp_title', array( __CLASS__, 'set_title' ), 10, 3 );

		remove_action( 'template_redirect', 'redirect_canonical' );
	}

	private static function get_query_var($taxname) {
		$taxobj = get_taxonomy( $taxname );
		
		if ( $taxobj->query_var )
			return $taxobj->query_var;

		$tmp = array(
			'post_tag' => 'tag',
			'category' => 'category_name'
		);

		if ( isset( $tmp[$taxname] ) )
			return $tmp[$taxname];
		
		return false;
	}

	static function is_regular_query() {
		if ( count( self::$actual_query ) > 1 )
			return false;

		$tax = key( self::$actual_query );
		$term = reset( self::$actual_query );

		return in_array( $tax, get_object_taxonomies( 'post' ) ) && false === strpos( $term, ',' ) && false === strpos( $term, '+' );
	}

	private static function set_post_ids() {
		global $wpdb;

		if ( !is_null( self::$post_ids ) )
			return;

		if ( empty( self::$actual_query ) )
			self::$post_ids = array();

		$query = array();
		foreach ( self::$actual_query as $taxname => $value )
			foreach ( explode( '+', $value ) as $value )
				$query[] = wp_tax( $taxname, explode( ',', $value ), 'slug' );

		$query[] = "object_id IN ( SELECT ID FROM $wpdb->posts WHERE post_status = 'publish' )";

		self::$post_ids = $wpdb->get_col( wp_tax_query( wp_tax_group( 'AND', $query ) ) );
	}

	function get_terms( $tax ) {
		global $wpdb;

		if ( empty( self::$actual_query ) )
			return get_terms( $tax );

		self::set_post_ids();

		if ( empty( self::$post_ids ) )
			return array();

		$terms = $wpdb->get_results( $wpdb->prepare( "
			SELECT *, COUNT(*) as count
			FROM $wpdb->term_relationships
			JOIN $wpdb->term_taxonomy USING ( term_taxonomy_id )
			JOIN $wpdb->terms USING ( term_id )
			WHERE taxonomy = %s
			AND object_id IN ( " . implode( ',', self::$post_ids ) . " )
			GROUP BY term_taxonomy_id
		", $tax ) );

		if ( empty( $terms ) )
			return array();

		return $terms;
	}


//_____URLs_____


	public function get_url( $taxonomy, $value ) {
		$query = self::$actual_query;

		if ( empty( $value ) )
			unset( $query[$taxonomy] );
		else
			$query[$taxonomy] = trim( implode( '+', $value ), '+' );

		$url = self::get_canonical_url( $query );

		return apply_filters( 'qmt_url', $url, $query );
	}

	public function get_canonical_url( $query = array() ) {
		if ( empty( $query ) )
			$query = self::$actual_query;

		ksort( $query );

		$url = self::get_base_url();
		foreach ( $query as $taxonomy => $value )
			$url = add_query_arg( self::get_query_var( $taxonomy ), $value, $url );

		return $url;
	}

	private static $base_url;

	public function get_base_url() {
		if ( empty( self::$base_url ) )
			self::$base_url = apply_filters( 'qmt_base_url', get_bloginfo('url') );

		return self::$base_url;
	}


//_____Theme integration_____


	function template() {
		if ( $template = locate_template( array( 'multitax.php' ) ) ) {
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

	function get_title() {
		$title = array();
		foreach ( self::$actual_query as $tax => $value ) {
			$key = get_taxonomy( $tax )->label;

			// attempt to replace slug with name
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

