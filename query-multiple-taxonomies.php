<?php
/*
Plugin Name: Query Multiple Taxonomies
Version: 1.1a
Description: Filter posts through multiple custom taxonomies
Author: scribu
Author URI: http://scribu.net
Plugin URI: http://scribu.net/wordpress/query-multiple-taxonomies/
*/

class QMT_Core {
	private static $post_ids = array();

	function init() {
		add_action('init', array(__CLASS__, 'builtin_tax_fix'));
		add_action('pre_get_posts', array(__CLASS__, 'multiple_tax_fix'));
		remove_action('template_redirect', 'redirect_canonical');
	}
	
	// WP < 3.0
	function builtin_tax_fix() {
		global $wp_taxonomies;

		$tmp = array(
			'post_tag' => 'tag',
			'category' => 'category_name'
		);

		foreach ( $wp_taxonomies as $taxonomy => $t )
			if ( $t->_builtin && isset($tmp[$taxonomy]) )
			 $t->query_var = $tmp[$taxonomy];
	}

	function multiple_tax_fix($wp_query) {
		global $wp_taxonomies;

		$query = array();
		foreach ( $wp_taxonomies as $taxonomy => $t )
			if ( $t->query_var )
				if ( $var = $wp_query->get($t->query_var) )
					$query[$taxonomy] = $var;

		if ( empty($query) )
			return;

		$first_tax = key($query);
		$first_term_slug = reset($query);

		array_shift($query);

		$ids = self::get_posts_in_term($first_term_slug, $first_tax);
		foreach ( $query as $tax => $term_slug ) {
			if ( ! $posts = self::get_posts_in_term($term_slug, $tax) )
				return $wp_query->set_404();

			$ids = array_intersect($ids, $posts);
		}

		if ( empty($ids) )
			$wp_query->set_404();

		$wp_query->set('post__in', $ids);

		self::$post_ids = $ids;
	}

	private function get_posts_in_term($term_slug, $tax) {
debug($term_slug, $tax);
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

QMT_Core::init();

