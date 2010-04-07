=== Query Multiple Taxonomies ===
Contributors: scribu
Donate link: http://scribu.net/paypal
Tags: drill-down, query, widget, navigation, taxonomy
Requires at least: 2.9
Tested up to: 3.0
Stable tag: trunk

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


== Frequently Asked Questions ==

= How do I define a custom taxonomy? =

Try the [Simple Taxonomies](http://wordpress.org/extend/plugins/simple-taxonomies) plugin.

= How can I customize the display? =

Firstly, you have the `is_multitax()` conditional tag, that works similarly to `is_tax()`.

Secondly, you can create a `multitax.php` template file in your theme.


== Changelog ==

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

