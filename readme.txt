=== Query Multiple Taxonomies ===
Contributors: scribu
Tags: drill-down, query, widget, navigation, taxonomy
Requires at least: 3.2
Tested up to: 3.4
Stable tag: 1.6.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Filter posts through multiple taxonomies.

== Description ==

This plugin lets you do faceted search using multiple custom taxonomies.

It has a drill-down widget with multiple display modes.

Said widget is easily customizable via template files (no PHP knowledge required).

= Sponsors =
* [Bill Nanson](http://burgundy-report.com)
* [Matthew Porter](http://porterinnovative.com)

= No Support =
I do not offer any support for this plugin (free or paid).

Links: [**Documentation**](http://github.com/scribu/wp-query-multiple-taxonomies/wiki) | [Plugin News](http://scribu.net/wordpress/query-multiple-taxonomies) | [Author's Site](http://scribu.net)

== Screenshots ==

1. Lists mode
2. Checkboxes mode
3. Dropdowns mode
4. Widget configuration

= How can I order the taxonomies in the widget? =

Just drag them around.

= How do I define a custom taxonomy? =

Try the [Simple Taxonomies](http://wordpress.org/extend/plugins/simple-taxonomies) plugin.

= Is there a template tag I can use instead of widgets? =

You can use [the_widget()](http://codex.wordpress.org/Function_Reference/the_widget), like so:

`
the_widget('Taxonomy_Drill_Down_Widget', array(
	'title' => '',
	'mode' => 'dropdowns',
	'taxonomies' => array( 'post_tag', 'color' ) // list of taxonomy names
));
`

'mode' can be one of 'lists' or 'dropdowns'

= How can I customize the display? =

The template hierarchy for multitax queries is taxonomy.php -> archive.php -> index.php.

If you need to get specific, you can use the `is_multitax()` conditional tag, which works similarly to `is_tax()`:

`is_multitax()` -> true if more than one taxonomy was queried

`is_multitax( array('tax_a', 'tax_b') )` -> true if both tax_a and tax_b were queried

= How can I customize the display even further? =

Copy any file from the `templates` directory into a directory called `qmt` in
your theme and modify it at will.

For example to change the checkbox mode display,

copy

`wp-content/plugins/query-multiple-taxonomies/templates/checkboxes.html`

to

`wp-content/themes/YOUR-THEME/qmt/checkboxes.html`

and change it however you want.

== Changelog ==

= 1.6.1 =
* fixed activation error
* fixed error in PHP 5.4

= 1.6 =
* added checkbox mode
* added {{count}} tag. props khakulov
* added Ukranian translation. props Jurko Chervony
* [more info](http://scribu.net/wordpress/query-multiple-taxonomies/qmt-1-6.html)

= 1.5.1 =
* fixed issue with custom post types

= 1.5 =
* easy customization, using Mustache templates
* bring back wp_title filtering
* fix qmt_get_query() not working with the 'AND' tax_query operator
* [more info](http://scribu.net/wordpress/query-multiple-taxonomies/qmt-1-5.html)

= 1.4 =
* WordPress 3.1 compatibility (native taxonomy handling)
* sortable taxonomy list
* widget not affected by non-active taxonomies anymore
* dropdowns display hierarchical taxonomies correctly
* [more info](http://scribu.net/wordpress/query-multiple-taxonomies/qmt-1-4.html)

= 1.3.2 =
* fixed URL problems

= 1.3.1 =
* dropdowns displays hierarchical taxonomies correctly
* various bugfixes

= 1.3 =
* multiple taxonomies per widget
* introduced dropdowns mode
* is_multitax() accepts a list of taxonomies to check
* fixed a lot of query errors
* [more info](http://scribu.net/wordpress/query-multiple-taxonomies/qmt-1-3.html)

= 1.2.3 =
* fixed problems with custom menus
* fixed a problem with categories

= 1.2.2 =
* fixed fatal error
* reverted is_multitax() to previous behaviour

= 1.2.1 =
* fixed issue when site url isn't the same as the wp url
* fixed issue when doing a single taxonomy query

= 1.2 =
* fewer queries
* custom post type support
* load correct template for single category|tag|term archives
* [more info](http://scribu.net/wordpress/query-multiple-taxonomies/qmt-1-2.html)

= 1.1.1 =
* better title generation
* add ro_RO l10n

= 1.1 =
* allow ?tax=foo+bar (AND) queries
* add taxonomy drill-down widget
* [more info](http://scribu.net/wordpress/query-multiple-taxonomies/qmt-1-1.html)

= 1.0 =
* initial release
* [more info](http://scribu.net/wordpress/query-multiple-taxonomies/qmt-1-0.html)

