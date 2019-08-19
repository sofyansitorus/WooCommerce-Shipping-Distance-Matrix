=== WooReer (formerly WooCommerce Shipping Distance Matrix) ===
Contributors: sofyansitorus
Tags: woocommerce,woocommerce-shipping,local-shipping,private-shipping
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=DGSVXLV7R8BTY
Requires at least: 4.8
Tested up to: 5.2.2
Requires PHP: 5.6
Stable tag: 2.0.8
License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

WooCommerce shipping rates calculator that allows you to easily offer shipping rates based on the distance that calculated using Google Maps Distance Matrix Service API.

== Description ==
WooCommerce shipping rates calculator that allows you to easily offer shipping rates based on the distance that calculated using Google Maps Distance Matrix Service API.

This plugin perfect for the store owner who wants to charge customers for delivery of items based on how far away they are from the store. A perfect example would be online store that selling flowers, food, beverages. It’s not limited to just those, but anything that use local delivery or self delivery bussiness.

= Key Features =

* Set unlimited table rates rows.
* Set flat or flexible distance cost type: Fixed, Per KM/MI.
* Set rule for each rates rows: Maximum Distances.
* Set flat or pregressive total shipping cost for each rates rows: Max, Average, Min, Per Class, Per Product, Per Piece.
* Set surcharge for each rates rows.
* Set custom shipping label for each rates rows.
* Set different shipping rate for each product shipping class.
* Set shipping origin location using Maps Picker.
* Set distances unit: Mile, Kilometer.
* Set travel mode: Driving, Walking, Bicycling.
* Set route restrictions: Avoid Tolls, Avoid Highways, Avoid Ferries, Avoid Indoor.
* Set prefered route: Shortest Distance, Longest Distance, Shortest Duration, Longest Duration.
* Round the distance up to the nearest absolute number.
* Show distance info to customer during checkout.

= Pro Version Features =
* Set rule for each rates rows: Minimum Order Quantity.
* Set rule for each rates rows: Maximum Order Quantity.
* Set rule for each rates rows: Minimum Order Amount.
* Set rule for each rates rows: Maximum Order Amount.
* Set Advanced Math Formula to calculate total shipping cost for each rates rows.
* Use map address picker for customer during checkout.
* Multiple instances within the same shipping zone.

<a href="https://wooreer.com/?utm_source=wordpress.org&amp;utm_medium=link&amp;utm_campaign=plugin-details-from-wordpress.org" target="_blank">Upgrade to the Pro Version Now!</a>

= Dependencies =

This plugin require Google Maps Distance Matrix API Key and service is enabled. [Click here](https://developers.google.com/maps/documentation/distance-matrix/get-api-key) to go to Google API Console to get API Key and to enable the service.

== Installation ==
= Minimum Requirements =

* WordPress 4.8 or later
* WooCommerce 3.0 or later

= AUTOMATIC INSTALLATION =

Automatic installation is the easiest option as WordPress handles the file transfers itself and you don’t even need to leave your web browser. To do an automatic install of WooReer, log in to your WordPress admin panel, navigate to the Plugins menu and click Add New.

In the search field type “WooReer” and click Search Plugins. You can install it by simply clicking Install Now. After clicking that link you will be asked if you’re sure you want to install the plugin. Click yes and WordPress will automatically complete the installation. After installation has finished, click the ‘activate plugin’ link.

= MANUAL INSTALLATION =

1. Download the plugin zip file to your computer
1. Go to the WordPress admin panel menu Plugins > Add New
1. Choose upload
1. Upload the plugin zip file, the plugin will now be installed
1. After installation has finished, click the ‘activate plugin’ link

== Frequently Asked Questions ==

= How to set the plugin settings? =
You can setup the plugin setting from the WooCommerce Shipping Zones settings panel. Please [click here](https://fast.wistia.net/embed/iframe/95yiocro6p) for the video tutorial how to setup the WooCommerce Shipping Zones.

= I got error in the "Store Location" setting field", what should I do? =
The error printed in there is came from the Google API. Click any link printed within the error message to find out the causes and solutions.

= I see message "There are no shipping methods available" in the cart/checkout page, what should I do? =
Please try to switch the WooCommerce Shipping Debug Mode setting to "On". Then open your cart/checkout page. You will see the error printed if there was.

[Click here](https://fast.wistia.net/embed/iframe/9c9008dxnr) for how to switch WooCommerce Shipping Debug Mode.

= Where can I get support or report bug? =
You can create support ticket at plugin support forum:

* [Plugin Support Forum](https://wordpress.org/support/plugin/wcsdm)

= Can I contribute to develop this plugin? =
I always welcome and encourage contributions to this plugin. Please visit the plugin GitHub repository:

* [Plugin GitHub Repository](https://github.com/sofyansitorus/WooCommerce-Shipping-Distance-Matrix)

== Screenshots ==
1. Settings Panel: General
2. Settings Panel: Table Rates
3. Settings Panel: Advanced Rate Settings
4. Cart Page
5. Cart Page - Free Shipping

== Changelog ==

> **WARNING:**
> 
> Upgrading from version 1.x to version 2.x is a major update and has breaking changes. Some **settings data will be lost** and **re-setup the plugin** is required after the upgrade.
> 
> **Please upgrade wisely and carefully.**

= 2.0.8 =
* Enhancement - Fixed Unable to Calculate distnance in some cases
* Enhancement - Improved UI/UX backend area

= 2.0.7 =
* Enhancement - Added minimum cost option in favor removed distance cost type option.
* Enhancement - Improved backend area

= 2.0.6 =
* Fix - Fixed Wrong Shipping Address Format for US Based Address.

= 2.0.5 =
* Fix - Fixed address 1 & 2 fields not displayed when no shipping zone defined.

= 2.0.4 =
* Fix - Fixed the frontend script not loaded.
* Fix - Fixed the annoying scroll to current editing rate row in the admin panel.
* Enhancement - Added Options to Select Origin Type.
* Enhancement - Added filters to enable / disable address 1 and address 2 fields in shipping calculator form.

= 2.0.3 =
* Fix - Fixed Wrong Addres 1 & 2 Field Value in the Shipping Calculator Form.

= 2.0.2 =
* Fix - Fixed JS Error in console that causing shipping calculator form crash when there is no state selected on page load.

= 2.0.1 =
* Fix - Fixed JS Error in console that causing shipping calculator form crash.
* Fix - Fixed typo for "Total Cost Type" settings field description
* Fix - Disabled enqueue frontend scripts when the instance is disabled

= 2.0 =
* Improvements - Renamed the plugin name from WooCommerce Shipping Distance Matrix to WooReer.
* Improvements - Added server side API Key setting field in the setting form.
* Improvements - Added server side API Key setting field in the setting form.
* Improvements - Added browser side API Key setting field in the setting form.
* Improvements - Added address 1 field into the shipping calculator form.
* Improvements - Added address 2 field into the shipping calculator form.
* Improvements - Improved the admin setting form UI/UX. Specially the address picker.
* Fix - Postcode validation not for several country such as Lutvia.
* Fix - Postcode validation only works in uppercase.
* Fix - Failed populating shipping destination info for address field.
* Fix - Failed to calculating for short distance such as 100m.
* Fix - Data cache issue for multiple instance within the same shipping zone.

= 1.4.7 =
* Feature - Added new field to set the shipping title dynamically for each distance ranges.
* Fix - Added destination address validation.
* Improvements - Improved table rates setting sorted automatically by distance.

= 1.4.6 =
* Fix - Rate row not visible when switch free shipping option.
* Fix - Flickering Map address picker when changing API Key.

= 1.4.5 =
* Fix - In rare case, settings panel broken for site using UTF-8 characters language.

= 1.4.4 =
* Improvements - Added new option: Prefered Route
* Improvements - Improved accuration in cart shipping calculator form.

= 1.4.3 =
* Fix - Update minified styles and scripts.
* Improvements - Enable real time API key validation.

= 1.4.3 =
* Fix - Update minified styles and scripts.
* Improvements - Enable real time API key validation.

= 1.4.2 =
* Improvements - Settings Panel
* Improvements - Data cache handler
* Improvements - Free shipping Options

= 1.4.1 =
* Fix - Error in Google Map address picker.

= 1.4 =

* Improvements - Enable Free Shipping Option.

= 1.3.8 =

* Improvements - Added free shipping option.
* Improvements - Added option dynamic cost calculation type for each distance range.
* Fix - Plugin is undetected in WordPress multisite installtion when WooCommerce plugin is network activated.

= 1.3.8 =

* Improvements - Add new option for base fee.
* Improvements - Add new option for round up distance.

= 1.3.7 =

* Improvements - Enhance store location map picker.
* Improvements - Enhance table rates setting field.
* Improvements - Update cache_key on shipping rates settings updated.

= 1.3.6 =

* Improvements - Add new calculation type option: Per product - Charge shipping grouped by product ID.
* Improvements - Add new calculation type option: Per shipping class - Charge shipping grouped by product shipping class.
* Fix - Fix table rates input value issue.

= 1.3.5 =

* Improvements - Add new option: Enable Fallback Request.

= 1.3.4 =

* Improvements - Add new option: Enable Fallback Request.

= 1.3.3 =

* Fix - Change route restriction to single select.
* Improvements - Added fallback to input store location manually on google map error.

= 1.3.2 =

* Fix - Fix issue with localization decimal delimeter.
* Improvements - Added language parameter for Maps API request.

= 1.3.1 =

* Fix - Fix API request URL.
* Improvements - Switched from WP Cache Object to Transient.

= 1.3.0 =

* Improvements - Add Google Maps Picker.

= 1.2.9 =

* Fix - Fix issue when comma as decimal separator.

= 1.2.8 =

* Improvements - More info in debugging mode.
* Fix - Table rates input fields styling.

= 1.2.7 =

* Fix - Remove Maps Place Picker.

= 1.2.6 =

* Fix - Maps picker.

= 1.2.5 =

* Improvements - Add option to charge shipping per distance unit.

= 1.2.4 =

* Improvements - Add "Map Location Picker" for store location setting.
* Improvements - Setting panel UI/UX improved more user friendly.

= 1.2.3 =

* Improvements - Add new filter hooks: woocommerce_wcsdm_shipping_destination_info.
* Improvements - Add new filter hooks: woocommerce_wcsdm_shipping_origin_info.
* Improvements - Tweak settings panel UI and default value.
* Improvements - Add validation for settings field: gmaps_api_key, origin_lat, origin_lng, table_rates.

= 1.2.2 =

* Fix - woocommerce_shipping_wcsdm_is_available filter.

= 1.2.1 =

* Improvements - Add filter to enable city field in shipping calculator form.
* Fix - Filter destination address, address_2, city, postcode fields for shipping calculator request.
* Fix - Change wrong API request param from mode to avoid.

= 1.2.0 =

* Feature - Set visibility distance info to customer.

= 1.1.1 =

* Improvement - Enable WP Object Cache for API HTTP request to improve the speed and reduce request count to API server.
* Fix - Remove unused js code.

= 1.1.0 =

* Feature - Set cost calculation type per order or per item.
* Localization - Update POT file.

= 1.0.0 =

* Feature - Set shipping cost by product shipping class.
* Feature - Set unlimited distances ranges.
* Feature - Set origin info by coordinates.
* Feature - Set distances unit: Mile, Kilometer.
* Feature - Set travel mode: Driving, Walking, Bicycling.
* Feature - Set route restrictions: Avoid Tolls, Avoid Highways, Avoid Ferries, Avoid Indoor.

== Upgrade Notice ==
= 2.0.7 =

WARNING: Upgrading from version 1.x to version 2.x is a major update and has breaking changes. Some settings data will be lost and re-setup the plugin is required after the upgrade. Please upgrade wisely and carefully.