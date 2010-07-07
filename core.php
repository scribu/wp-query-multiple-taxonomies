<?php

class QMT_Core {
	private static $post_ids = array();
	private static $actual_query = array();

	function init() {
		add_action( 'init', array( __CLASS__, 'builtin_tax_fix' ) );

		add_action( 'parse_query', array( __CLASS__, 'query' ) );
		
		add_action( 'template_redirect', array( __CLASS__, 'template' ) );
		add_filter( 'wp_title', array( __CLASS__, 'set_title' ), 10, 3 );

		remove_action( 'template_redirect', 'redirect_canonical' );
	}


//_____Query_____


	function get_actual_query( $tax = '' ) {
		if ( !empty( $tax ) )
			return @self::$actual_query[$tax];

		return self::$actual_query;
	}

	function builtin_tax_fix() {
		$tmp = array( 
			'post_tag' => 'tag',
			'category' => 'category_name' 
		);

		foreach ( get_taxonomies( array( '_builtin' => true ), 'object' ) as $taxname => $taxobj )
			if ( isset( $tmp[$taxname] ) )
				$taxobj->query_var = $tmp[$taxname];
	}

	function query( $wp_query ) {
		global $wpdb;

		$query = array();
		foreach ( get_taxonomies( array('public' => true ) ) as $taxname ) {
			$taxobj = get_taxonomy( $taxname );

			if ( ! $qv = $taxobj->query_var )
				continue;

			if ( ! $value = $wp_query->get( $qv ) )
				continue;

			$value = end( explode( '/', $value ) );

			self::$actual_query[$taxname] = str_replace( ' ', '+', $value );

			foreach ( explode( ' ', $value ) as $value )
				$query[] = wp_tax( $taxname, explode( ',', $value ), 'slug' );
		}

		if ( empty( $query ) )
			return;

		if ( 1 == count(self::$actual_query) ) {
			$term = reset(self::$actual_query);
			
			if ( false === strpos($term, ',') && false === strpos($term, '+') )
				return;
		}

		// maybe filter the post ids later, using $wp_query?
		$query[] = "object_id IN ( SELECT ID FROM $wpdb->posts WHERE post_status = 'publish' )";

		self::$post_ids = $wpdb->get_col( wp_tax_query( wp_tax_group( 'AND', $query ) ) );

		if ( empty( self::$post_ids ) )
			return $wp_query->set_404();

		$wp_query->is_archive = true;
		$wp_query->is_multitax = true;

		$is_feed = $wp_query->is_feed;
		$paged = $wp_query->get( 'paged' );

		$wp_query->init_query_flags();

		$wp_query->is_feed = $is_feed;
		$wp_query->set( 'paged', $paged );

		$wp_query->set( 'post_type', 'any' );
		$wp_query->set( 'post__in', self::$post_ids );
	}

	function get_terms( $tax ) {
		if ( empty( self::$post_ids ) )
			return get_terms( $tax );

		global $wpdb;

		$terms = $wpdb->get_results( $wpdb->prepare( "
			SELECT *, COUNT( * ) as count
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
			$url = add_query_arg( get_taxonomy( $taxonomy )->query_var, $value, $url );

		return $url;
	}

	private static $base_url;

	public function get_base_url() {
		if ( empty( self::$base_url ) )
			self::$base_url = apply_filters( 'qmt_base_url', site_url() );

		return self::$base_url;
	}

//_____Theme integration_____


	function template() {
		if ( is_multitax() && $template = locate_template( array( 'multitax.php' ) ) ) {
			include $template;
			die;
		}
	}

	function set_title( $title, $sep, $seplocation = '' ) {
		if ( !is_multitax() )
			return $title;

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

