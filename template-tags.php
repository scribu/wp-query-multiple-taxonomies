<?php

function is_multitax() {
	global $wp_query;

	return @$wp_query->is_multitax;
}

function qmt_get_query() {
	return QMT_Core::get_actual_query();
}

