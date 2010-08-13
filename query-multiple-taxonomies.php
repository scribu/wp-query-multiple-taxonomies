<?php
/*
Plugin Name: Query Multiple Taxonomies
Version: 1.3.1-alpha
Description: Filter posts through multiple custom taxonomies
Author: scribu
Author URI: http://scribu.net
Plugin URI: http://scribu.net/wordpress/query-multiple-taxonomies/
*/

require dirname( __FILE__ ) . '/scb/load.php';

function _qmt_init() {
	load_plugin_textdomain( 'taxonomy-drill-down', '', dirname( plugin_basename( __FILE__ ) ) . '/lang' );

	require dirname( __FILE__ ) . '/core.php';
	require dirname( __FILE__ ) . '/tax-api.php';
	require dirname( __FILE__ ) . '/widget.php';

	if ( !is_admin() ) {
		QMT_Query::init();
		QMT_Template::init();
	}

	Taxonomy_Drill_Down_Widget::init();
	scbWidget::init( 'Taxonomy_Drill_Down_Widget', __FILE__, 'taxonomy-drill-down' );
}
scb_init( '_qmt_init' );

