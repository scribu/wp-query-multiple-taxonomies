=== Query Multiple Taxonomies ===
Contributors: scribu
Donate link: http://scribu.net/paypal
Tags: drill-down, query, widget, navigation, taxonomy
Requires at least: 3.0
Tested up to: 3.0
Stable tag: 1.3

Filter posts through multiple taxonomies

== Description ==

Say you have two custom taxnomies defined: genre and artist.

Currently, you can do the following types of queries on your site:

`?genre=jazz`

`?genre=jazz&cat=1`

But you can't do this:

`?genre=jazz&artist=chet-baker`

WordPress will just ignore one of the parameters. This plugin fixes that.

It also comes with a drill-down navigation widget.

= Sponsors =
* [Bill Nanson](http://burgundy-report.com)
* [Matthew Porter](http://porterinnovative.com)


== Screenshots ==

1. Drill-down navigation widgets

2. Drill-down widgets in the admin


== Frequently Asked Questions ==

= Error on activation: "Parse error: syntax error, unexpected T_CLASS..." =

Make sure your host is running PHP 5. Add this line to wp-config.php to check:

`var_dump(PHP_VERSION);`

= How do I define a custom taxonomy? =

Try the [Simple Taxonomies](http://wordpress.org/extend/plugins/simple-taxonomies) plugin.

= How can I customize the display? =

The template hierarchy for multitax queries is taxonomy.php -> archive.php -> index.php.

If you need to get specific, you can use the `is_multitax()` conditional tag, which works similarly to `is_tax()`:

`is_multitax()` -> true if more than one taxonomy was queried

`is_multitax( array('tax_a', 'tax_b') )` -> true if both tax_a and tax_b were queried

== Changelog ==

= 1.4 =
* dropdowns displays hierarchical taxonomies correctly

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

