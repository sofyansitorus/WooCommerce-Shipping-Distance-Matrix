=== WooCommerce Shipping Distance Matrix ===
Contributors: sofyansitorus
Tags: woocommerce shipping,local shipping,private shipping,gojek shipping,grab shipping
Requires at least: 4.8
Tested up to: 4.9.1
Requires PHP: 5.6
Stable tag: trunk
License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

WooCommerce shipping rates calculator based on products shipping class and distances that calculated using Google Maps Distance Matrix API.

== Description ==
WooCommerce shipping rates calculator based on products shipping class and distances that calculated using Google Maps Distance Matrix API.

This plugin perfect for online store that use local shipping or private shipping such as selling flower, food & beverage but not limited to.

For online store that located in Indonesia can use this plugin to use shipping via GOJEK and GRAB. Please note that this plugin is not using GOJEK/GRAB API. The plugin just estimate the distance and then calculating the cost using the table rates defined in the settings.

= Features =

* Set shipping cost by product shipping class.
* Set unlimited distances ranges.
* Set origin info by coordinates.
* Set distances unit: Mile, Kilometre.
* Set travel mode: Driving, Walking, Bicycling.
* Set route restrictions: Avoid Tolls, Avoid Highways, Avoid Ferries, Avoid Indoor.
* Set cost calculation type per order or per item.
* Set visibility distance info to customer.
* Translation ready.

== Installation ==
= Minimum Requirements =

* WordPress 4.8 or later
* WooCommerce 3.0 or later

= AUTOMATIC INSTALLATION =

Automatic installation is the easiest option as WordPress handles the file transfers itself and you don’t even need to leave your web browser. To do an automatic install of WooCommerce Shipping Distance Matrix, log in to your WordPress admin panel, navigate to the Plugins menu and click Add New.

In the search field type “WooCommerce Shipping Distance Matrix” and click Search Plugins. You can install it by simply clicking Install Now. After clicking that link you will be asked if you’re sure you want to install the plugin. Click yes and WordPress will automatically complete the installation. After installation has finished, click the ‘activate plugin’ link.

= MANUAL INSTALLATION =

1. Download the plugin zip file to your computer
1. Go to the WordPress admin panel menu Plugins > Add New
1. Choose upload
1. Upload the plugin zip file, the plugin will now be installed
1. After installation has finished, click the ‘activate plugin’ link

== Screenshots ==
1. Settings panel top area
2. Settings panel bottom area

== Changelog ==

= 1.2.1 - 2018-01-06 =

* Improvement - Add filter to enable city field in shipping calculator form.
* Fix - Filter destination address, address_2, city, postcode fields for shipping calculator request.
* Fix - Change wrong API request param from mode to avoid.

= 1.2.0 - 2018-01-06 =

* Feature - Set visibility distance info to customer.

= 1.1.1 - 2018-01-06 =

* Improvement - Enable WP Object Cache for API HTTP request to improve the speed and reduce request count to API server.
* Fix - Remove unused js code.

= 1.1.0 - 2018-01-05 =

* Feature - Set cost calculation type per order or per item.
* Localization - Update POT file.

= 1.0.0 - 2018-01-05 =

* Feature - Set shipping cost by product shipping class.
* Feature - Set unlimited distances ranges.
* Feature - Set origin info by coordinates.
* Feature - Set distances unit: Mile, Kilometre.
* Feature - Set travel mode: Driving, Walking, Bicycling.
* Feature - Set route restrictions: Avoid Tolls, Avoid Highways, Avoid Ferries, Avoid Indoor.