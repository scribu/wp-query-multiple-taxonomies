<?php

class Taxonomy_Drill_Down_Widget extends scbWidget {

	function Taxonomy_Drill_Down_Widget() {
		$this->defaults = array(
			'title' => '',
			'taxonomy' => ''
		);

		$widget_ops = array(
			'description' => 'Display a drill-down navigation based on custom taxonomies',
		);

		$this->WP_Widget('taxonomy-drill-down', 'Taxonomy Drill-Down', $widget_ops);
	}

	function form($instance) {
		if ( empty($instance) )
			$instance = $this->defaults;

		echo $this->input(array(
			'title' => __('Title:', 'query-multiple-taxonomies'),
			'name' => 'title',
			'type' => 'text',
		), $instance);

		$out = '';

		$taxonomies = array();
		foreach ( get_object_taxonomies(QMT_Core::$post_type) as $taxonomy ) {
			$tax = get_taxonomy($taxonomy);

			if ( ! empty($tax->label) )
				$taxonomies[$taxonomy] = $tax->label;
		}

		echo $this->input(array(
			'type' => 'select',
			'name' => 'taxonomy',
			'values' => $taxonomies,
			'desc' => __('Taxonomy:', 'query-multiple-taxonomies'),
		), $instance);
	}

	function widget($args, $instance) {
		extract($args);
		extract(wp_parse_args($instance, $this->defaults));

		$list = qmt_walk_terms($taxonomy);

		if ( empty($list) )
			return;

		echo $before_widget;

		if ( empty($taxonomy) ) {
			echo html('p', __('No taxonomy selected.', 'query-multiple-taxonomies'));
		}
		else {
			if ( empty($title) )
				$title = get_taxonomy($instance['taxonomy'])->label;
			$title = apply_filters('widget_title', $title, $instance, $this->id_base);

			$query = QMT_Core::get_actual_query();
			if ( isset($query[$taxonomy]) ) {
				$new_url = QMT_Core::get_url($taxonomy, '');
				$title .= ' ' . html("a class='clear-taxonomy' href='$new_url'", '(-)');
			}

			$out = '';
			if ( ! empty($title) )
				$out .= $before_title . $title . $after_title;

			$out .= html("ul class='term-list'", $list);

			echo html("div id='term-list-$taxonomy'", $out);
		}

		echo $after_widget;
	}
}

function qmt_walk_terms($taxonomy, $args = '') {
	$terms = QMT_Core::get_terms($taxonomy);

	if ( empty($terms) )
		return '';

	$walker = new QMT_Term_Walker($taxonomy);

	$args = wp_parse_args($args, array(
		'style' => 'list',
		'use_desc_for_title' => false,
		'addremove' => true,
	));

	return $walker->walk($terms, 0, $args);
}


class QMT_Term_Walker extends Walker_Category {

	public $tree_type = 'term';

	private $taxonomy;
	private $query;

	public $selected_terms = array();

	function __construct($taxonomy) {
		$this->taxonomy = $taxonomy;
		$this->qv = get_taxonomy($taxonomy)->query_var;

		$this->selected_terms = explode('+', QMT_Core::get_actual_query($taxonomy));
	}

	function start_el(&$output, $term, $depth, $args) {
		extract($args);

		if ( !$use_desc_for_title || empty($term->description) )
			$title = sprintf(__( 'View all posts filed under %s', 'query-multiple-taxonomies'), esc_attr($term->name));
		else
			$title = esc_attr(strip_tags($term->description));

		$link = html("a href='" . get_term_link($term, $this->taxonomy) . "' title='$title'", $term->name);

		if ( isset($addremove) && $addremove )
			$link .= $this->get_addremove_link($term);

		if ( isset($show_count) && $show_count )
			$link .= ' (' . intval($term->count) . ')';

		if ( 'list' == $style ) {
			$output .= "\t<li";
			$class = 'term-item term-item-'.$term->term_id;
			if ( in_array($term->slug, $this->selected_terms) )
				$class .=  ' current-term';
//			elseif ( $term->term_id == $_current_term->parent )
//				$class .=  ' current-term-parent';
			$output .=  ' class="'.$class.'"';
			$output .= ">$link\n";
		} else {
			$output .= "\t$link<br />\n";
		}
	}

	private function get_addremove_link($term) {
		$tmp = $this->selected_terms;
		$i = array_search($term->slug, $tmp);

		if ( false !== $i ) {
			unset($tmp[$i]);

			$new_url = esc_url(QMT_Core::get_url($this->qv, $tmp));
			$out = html("a class='remove-term' href='$new_url'", '(-)');
		}
		else {
			$tmp[] = $term->slug;

			$new_url = esc_url(QMT_Core::get_url($this->qv, $tmp));
			$out = html("a class='add-term' href='$new_url'", '(+)');
		}

		return ' ' . $out;
	}
}

