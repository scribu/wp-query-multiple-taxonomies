<?php
/*
Plugin Name: Query Multiple Taxonomies
Version: 1.1a2
Description: Filter posts through multiple custom taxonomies
Author: scribu
Author URI: http://scribu.net
Plugin URI: http://scribu.net/wordpress/query-multiple-taxonomies/
*/

class QMT_Core {
	private static $post_ids = array();
	private static $actual_query = array();
	private static $url = '';

	function init() {
		add_action('init', array(__CLASS__, 'builtin_tax_fix'));
		add_action('parse_query', array(__CLASS__, 'multiple_tax_query'));
		remove_action('template_redirect', 'redirect_canonical');
	}

	function get_actual_query() {
		return self::$actual_query;
	}

	function get_canonical_url() {
		return self::$url;
	}

	function builtin_tax_fix() {
		$tmp = array(
			'post_tag' => 'tag',
			'category' => 'category_name'
		);

		foreach ( get_taxonomies(array('_builtin' => true), 'object') as $taxname => $taxobj )
			if ( isset($tmp[$taxname]) )
				$taxobj->query_var = $tmp[$taxname];
	}

	function multiple_tax_query($wp_query) {
		self::$url = get_bloginfo('url');

		$query = array();
		foreach ( get_object_taxonomies('post') as $taxname ) {
			$taxobj = get_taxonomy($taxname);

			if ( ! $qv = $taxobj->query_var )
				continue;

			if ( ! $value = $wp_query->get($qv) )
				continue;

			self::$actual_query[$taxname] = $value;
			self::$url = add_query_arg($qv, $value, self::$url);

			foreach ( explode(' ', $value) as $slug )
				$query[] = array($taxname, $slug);
		}

		if ( empty($query) )
			return;

		if ( ! self::find_posts($query) )
			return $wp_query->set_404();

		$wp_query->set('post__in', self::$post_ids);

		// set query_vars so that WP thinks we're querying a single term
		list($term) = explode(' ', $wp_query->get('term'));
		$tax = $wp_query->get('taxonomy');
		$wp_query->set('term', $term);
		$wp_query->set($tax, $term);

		// do the same for $wp_query->query
		$wp_query->query['term'] = $term;
		$wp_query->query[$wp_query->query['taxonomy']] = $term;

		// only work on the first query, so that query_posts() works normally
		remove_action('parse_query', array(__CLASS__, __FUNCTION__));
	}

	private function find_posts($query) {
		global $wpdb;

		// get an initial set of ids, to intersect with the others
		if ( ! $ids = self::get_objects(array_shift($query)) )
			return false;

		foreach ( $query as $qv ) {
			if ( ! $posts = self::get_objects($qv) )
				return false;

			$ids = array_intersect($ids, $posts);
		}

		// select only published posts
		$ids = $wpdb->get_col("
			SELECT ID FROM $wpdb->posts 
			WHERE post_type = 'post' 
			AND post_status = 'publish' 
			AND ID IN (" . implode(',', $ids). ")
		");

		if ( empty($ids) )
			return false;

		self::$post_ids = $ids;

		return true;
	}

	private function get_objects($qv) {

		list($tax, $term_slug) = $qv;

		if ( ! $term = get_term_by('slug', $term_slug, $tax) )
			return false;

		$ids = get_objects_in_term($term->term_id, $tax);

		if ( empty($ids) )
			return false;

		return $ids;
	}

	function get_terms($tax) {
		if ( empty(self::$post_ids) )
			return get_terms($tax);

		global $wpdb;

		$query = $wpdb->prepare("
			SELECT DISTINCT term_id
			FROM $wpdb->term_relationships
			JOIN $wpdb->term_taxonomy USING (term_taxonomy_id)
			WHERE taxonomy = %s
			AND object_id IN (" . implode(',', self::$post_ids) . ")
		", $tax);

		$term_ids = $wpdb->get_col($query);

		return get_terms($tax, array('include' => implode(',', $term_ids)));
	}
}

if ( ! function_exists('get_taxonomies') ) :
// http://core.trac.wordpress.org/ticket/12516/
function get_taxonomies( $args = array(), $output = 'names' ) {
	global $wp_taxonomies;

	$taxonomies = array();
	foreach ( (array) $wp_taxonomies as $taxname => $taxobj )
		if ( empty($args) || array_intersect_assoc((array) $taxobj, $args) )
			$taxonomies[$taxname] = $taxobj;

	if ( 'names' == $output )
		return array_keys($taxonomies);

	return $taxonomies;
}
endif;


function _qmt_init() {
	include dirname(__FILE__) . '/scb/load.php';
	include dirname(__FILE__) . '/widget.php';
	include dirname(__FILE__) . '/debug.php';

	// Load translations
	load_plugin_textdomain('taxonomy-drill-down', '', basename(dirname(__FILE__)) . '/lang');

	QMT_Core::init();

	scbWidget::init('Taxonomy_Drill_Down_Widget', __FILE__, 'taxonomy-drill-down');
}
_qmt_init();

