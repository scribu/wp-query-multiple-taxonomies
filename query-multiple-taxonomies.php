<?php
/*
Plugin Name: Query Multiple Taxonomies
Version: 1.6.1
Description: Filter posts through multiple custom taxonomies using a widget.
Author: scribu
Author URI: http://scribu.net
Plugin URI: http://scribu.net/wordpress/query-multiple-taxonomies
Text Domain: query-multiple-taxonomies
Domain Path: /lang
*/

require dirname( __FILE__ ) . '/scb/load.php';

function _qmt_init() {
	load_plugin_textdomain( 'query-multiple-taxonomies', '', basename( dirname( __FILE__ ) ) . '/lang' );

	require_once dirname( __FILE__ ) . '/core.php';
	require_once dirname( __FILE__ ) . '/walkers.php';
	require_once dirname( __FILE__ ) . '/widget.php';

	Taxonomy_Drill_Down_Widget::init( __FILE__ );
}
scb_init( '_qmt_init' );

