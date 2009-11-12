<?php
/*
Plugin Name: Query Multiple Taxonomies
Version: 1.0
Description: Filter posts through multiple custom taxonomies
Author: scribu
Author URI: http://scribu.net
Plugin URI: http://scribu.net/wordpress/query-multiple-taxonomies
*/

add_action('pre_get_posts', 'multiple_tax_fix');

function multiple_tax_fix($wp_query)
{
	global $wp_taxonomies;

	$query = array();

	foreach ( $wp_taxonomies as $taxonomy => $t )
		if ( $t->query_var )
			if ( $var = $wp_query->get($t->query_var) )
				$query[$taxonomy] = $var;

	if ( count($query) <= 1 )
		return;

	$ids = array();
	foreach ( $query as $tax => $term_slug )
	{
		if ( ! $term = get_term_by('slug', $term_slug, $tax) )
			return $wp_query->set_404();

		$posts = get_objects_in_term( $term->term_id, $tax );
		
		if ( empty($posts) )
			return $wp_query->set_404();

		$ids[] = $posts;
	}

	$ids = call_user_func_array('array_intersect', $ids);

	if ( empty($ids) )
		$wp_query->set_404();

	$wp_query->set('post__in', $ids);
}

