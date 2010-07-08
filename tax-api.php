<?php

function wp_tax_and() {
	$args = func_get_args();

	return wp_tax_group( 'AND', $args );
}

function wp_tax_or() {
	$args = func_get_args();

	return wp_tax_group( 'OR', $args );
}

function wp_tax_group( $op, $args ) {
	return "( " . implode( " $op ", $args ) . " )";
}

function wp_tax( $taxonomy, $terms, $field = 'term_id' ) {
	return _wp_tax( $taxonomy, $terms, $field, true );
}

function wp_tax_not( $taxonomy, $terms, $field = 'term_id' ) {
	return _wp_tax( $taxonomy, $terms, $field, false );
}

function _wp_tax( $taxonomy, $terms, $field, $in ) {
	global $wpdb;

	$terms = (array) $terms;

	switch ( $field ) {
		case 'term_taxonomy_id':
			$terms = implode( ',', array_map( 'intval', $terms ) );
		break;

		case 'term_id':
			$terms = implode( ',', array_map( 'intval', $terms ) );
			$terms = $wpdb->prepare( "
				SELECT term_taxonomy_id
				FROM $wpdb->term_taxonomy
				WHERE taxonomy = %s
				AND term_id IN ( $terms )
			", $taxonomy );
		break;

		case 'slug':
		case 'name':
			$terms = "'" . implode( "','", esc_sql( $terms ) ) . "'";
			$terms = $wpdb->prepare( "
				SELECT term_taxonomy_id
				FROM $wpdb->term_taxonomy
				INNER JOIN $wpdb->terms USING ( term_id )
				WHERE taxonomy = %s
				AND $field IN ( $terms )
			", $taxonomy );
	}

	$operator = $in ? 'IN' : 'NOT IN';

	return "object_id IN (
		SELECT object_id FROM $wpdb->term_relationships WHERE term_taxonomy_id $operator ( $terms )
	)";
}

function wp_tax_query( $query ) {
	global $wpdb;
	return "SELECT DISTINCT object_id FROM $wpdb->term_relationships WHERE " . $query;
}

