=== WooReer ===
Contributors: sofyansitorus
Tags: woocommerce,distance-based-shipping,shipping-calculator,local-delivery,google-maps
Donate link: https://www.buymeacoffee.com/sofyansitorus?utm_source=wooreer_plugin_page&utm_medium=referral
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 3.0.2
License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

WooReer (formerly WooCommerce Shipping Distance Matrix) calculates shipping rates based on distance via Google Maps, Mapbox, or DistanceMatrix.ai.

== Description ==
WooReer (formerly WooCommerce Shipping Distance Matrix) is a powerful WooCommerce shipping rates calculator that allows you to offer shipping rates based on distance. The plugin features a flexible API provider architecture, supporting Google Maps Routes API, Mapbox Matrix API, and DistanceMatrix.ai, giving you more choice and control over your mapping services.

This plugin is perfect for store owners who want to charge customers for delivery based on the distance from the store. It is ideal for online stores selling flowers, food, or beverages, but is versatile enough for any business that offers local delivery services.

= Key Features =

* **Multi-Provider Support:** Choose between Google Maps, Mapbox, or DistanceMatrix.ai for the most accurate and cost-effective distance calculations. (More providers coming soon!)
* **Flexible Table Rates:** Create unlimited shipping rules with granular control per WooCommerce shipping zone.
* **Advanced Calculation Logic:** Calculate shipping based on distance, with options for progressive or flat rates.
* **Dynamic Pricing Rules:**
    *   Set rates per product, per shipping class, or based on total cart weight/quantity/amount.
    *   Apply fixed or percentage-based surcharges and discounts.
    *   Define minimum and maximum shipping costs.
* **Smart Routing Options:**
    *   **Travel Modes:** Driving, Walking, Bicycling.
    *   **Restrictions:** Avoid Tolls, Highways, Ferries, or Indoor routes.
* **Conditional Shipping:** Restrict shipping based on minimum/maximum order quantity, amount, or distance.
* **Customer Transparency:** Display calculated distance on the cart and checkout pages.
* **Easy Configuration:** Simple and straightforward settings panel.

= Demo =

Please visit the link below for the live demo:

[http://demo.wooreer.com](http://demo.wooreer.com?utm_source=wooreer_plugin_page)

= Dependencies =

This plugin requires an API Key from your chosen provider (Google Maps, Mapbox, or DistanceMatrix.ai).

**For Google Maps:**
You need to have the **Routes API** enabled.

**For Mapbox:**
You need a Mapbox Access Token with access to the Matrix API.

**For DistanceMatrix.ai:**
You need an API Key from DistanceMatrix.ai.

= Donation =

If you find WooReer useful for your business, please consider supporting its development. Your donation helps ensure the plugin stays up-to-date, secure, and feature-rich.

Every contribution, no matter the size, is deeply appreciated and motivates further improvements.

[Buy me a coffee](https://www.buymeacoffee.com/sofyansitorus?utm_source=wooreer_plugin_page&utm_medium=referral)

== Installation ==

= Minimum Requirements =

* WordPress 6.4 or later
* WooCommerce 8.8 or later

= AUTOMATIC INSTALLATION =

Automatic installation is the easiest option as WordPress handles the file transfers itself and you don’t even need to leave your web browser. To do an automatic install of WooReer, log in to your WordPress admin panel, navigate to the Plugins menu and click Add New.

In the search field type “WooReer” and click Search Plugins. You can install it by simply clicking Install Now. After clicking that link you will be asked if you’re sure you want to install the plugin. Click yes and WordPress will automatically complete the installation. After the installation has finished, click the ‘activate plugin’ link.

= MANUAL INSTALLATION =

1. Download the plugin zip file to your computer
1. Go to the WordPress admin panel menu Plugins > Add New
1. Choose upload
1. Upload the plugin zip file, the plugin will now be installed
1. After the installation has finished, click the ‘activate plugin’ link

== Frequently Asked Questions ==

= Where can I get support or report a bug? =

You can create a support ticket at plugin support forum:

* [Plugin Support Forum](https://wordpress.org/support/plugin/wcsdm)

= Can I contribute to developing this plugin? =

I always welcome and encourage contributions to this plugin. Please visit the plugin GitHub repository:

* [Plugin GitHub Repository](https://github.com/sofyansitorus/WooCommerce-Shipping-Distance-Matrix)

== Screenshots ==

1. General Settings
2. Distance Calculator API Settings
3. Store Location Settings
4. Total Cost Settings
5. Table Rates Settings
6. Edit Rate Item
7. Add New Rate Item

== Changelog ==

= 3.0.2 =

* Fix - Restored missing Distance Unit setting.

= 3.0.1 =

* Fix - Fixed distance display not respecting WooCommerce decimal formatting settings during checkout.
* Fix - Fixed custom shipping labels defined per table rate row not appearing on checkout form.

= 3.0.0 =

* Major - Complete codebase refactoring with improved architecture.
* Enhancement - Migrated frontend JavaScript to TypeScript for better type safety and maintainability.
* Enhancement - Refactored SCSS architecture for better organization and maintainability.
* Enhancement - Added new API provider architecture with support for multiple distance calculation services.
* Enhancement - Introduced utility classes for better code organization and reusability.
* Enhancement - Improved build process with support for both minified and unminified assets.
* Enhancement - Reorganized legacy code into dedicated legacy directory for better code structure.
* Enhancement - Added comprehensive PHPDoc blocks throughout the codebase.
* Enhancement - Added automated testing infrastructure.
* Enhancement - Improved deployment scripts and configuration.

= 2.2.4 =

* Fix - Fix incorrect plugin file when declaring incompatibility with the Cart and Checkout Blocks.

= 2.2.3 =

* Fix - Declare incompatibility with the Cart and Checkout Blocks.

= 2.2.2 =

* Fix - Fixed compatibility issue with PHP 8.

= 2.2.1 =

* Fix - Fixed issue with empty validation error message.


= 2.2.0 =

* Fix - Fixed the broken settings panel in WooCommerce version 8.4.0 and newer.


= 2.1.16 =

* Fix - Fixed compatibility with High-Performance Order Storage.

= 2.1.15 =

* Fix - Fixed error `Loading the Google Maps JavaScript API without a callback is not supported`.

= 2.1.14 =

* Fix - Fixed outdated usage of "ship_to_different_address" POST data.

= 2.1.13 =

* Fix - Fixed incorrect distance conversion always to miles.

= 2.1.12 =

* Enhancement - Added log functionality.
* Fix - Fixed table rates auto sort not works for max distance with decimal value.

= 2.1.11 =

* Enhancement - Improved UI/UX admin settings panel.
* Enhancement - Updated version compatibility.

= 2.1.9 =

* Fix - Fixed surcharge type rate setting as none is calculated as a percentage.
* Fix - Fixed discount type rate setting as none is calculated as a percentage.
* Enhancement - Added new column to table rate settings: Row Number.
* Enhancement - Improved validation of the table rate rows data.

= 2.1.8 =

* Enhancement - Added maximum cost settings.
* Enhancement - Improved admin settings form UI/UX.

= 2.1.7 =

* Enhancement - Added shipping discount options.
* Enhancement - Added shipping discount type options.
* Enhancement - Added shipping surcharge type options.
* Fix - Fixed sort link still visible and interacting while deleting rate rows.
* Fix - Fixed incorrect table rate row fields description for the select field type.

= 2.1.6 =

* Fix - Fixed table rates row not filtered properly when having same max distance value.

= 2.1.5 =

* Fix - Fixed compatibility issue with Checkout Fields Editor plugin.
* Enhancement - Enabled manual sorting for table rates data.
* Enhancement - Enabled client site table rates data validation.

= 2.1.4 =

* Fix - Fixed action buttons not displayed propely after deleting rate items.

= 2.1.3 =
* Fix - Fixed conflict with other shipping plugins in the cart calculate shipping form.

= 2.1.2 =
* Fix - Fixed incorrect settings fields placeholder
* Fix - Added missing minified JS & CSS files

= 2.1.1 =
* Fix - Fixed bulk delete table rates checkbox still checked after delete did
* Fix - Fixed settings fields added by third-party not visible
* Fix - Added missing title attribute fro edit API Key button

= 2.1.0 =
* Enhancement - Added new shipping rule: Minimum Order Quantity
* Enhancement - Added new shipping rule: Maximum Order Quantity
* Enhancement - Added new shipping rule: Minimum Order Amount
* Enhancement - Added new shipping rule: Maximum Order Amount
* Fix - Fixed issue fail to calculate distance when there is pound character in the address data
* Fix - Fixed JS Error in cart page when certain fields is disabled

= 2.0.8 =
* Enhancement - Fixed Unable to Calculate distance in some cases
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
* Fix - Fixed the annoying scroll to the current editing rate row in the admin panel.
* Enhancement - Added Options to Select Origin Type.
* Enhancement - Added filters to enable/disable address 1 and address 2 fields in the shipping calculator form.

= 2.0.3 =
* Fix - Fixed Wrong Adders 1 & 2 Field Value in the Shipping Calculator Form.

= 2.0.2 =
* Fix - Fixed JS Error in the console that causing shipping calculator form crash when there is no state selected on page load.

= 2.0.1 =
* Fix - Fixed JS Error in the console that causing shipping calculator form crash.
* Fix - Fixed typo for "Total Cost Type" settings field description
* Fix - Disabled enqueue frontend scripts when the instance is disabled

= 2.0 =
* Improvements - Renamed the plugin name from WooCommerce Shipping Distance Matrix to WooReer.
* Improvements - Added server-side API Key setting field in the settings form.
* Improvements - Added server-side API Key setting field in the settings form.
* Improvements - Added browser-side API Key setting field in the settings form.
* Improvements - Added address 1 field into the shipping calculator form.
* Improvements - Added address 2 fields into the shipping calculator form.
* Improvements - Improved the admin setting form UI/UX. Especially the address picker.
* Fix - Postcode validation not for several countries such as Latvia.
* Fix - Postcode validation only works in uppercase.
* Fix - Failed populating shipping destination info for the address field.
* Fix - Failed to calculate for a short distance such as 100m.
* Fix - Data cache issue for multiple instances within the same shipping zone.

= 1.4.7 =
* Feature - Added new field to set the shipping title dynamically for each distance ranges.
* Fix - Added destination address validation.
* Improvements - Improved table rates setting sorted automatically by distance.

= 1.4.6 =
* Fix - Rate row not visible when switching free shipping option.
* Fix - Flickering Map address picker when changing API Key.

= 1.4.5 =
* Fix - In rare cases, settings panel broke for the site using UTF-8 characters language.

= 1.4.4 =
* Improvements - Added new option: Preferred Route
* Improvements - Improve inputs in cart shipping calculator form.

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
* Fix - Plugin is undetected in WordPress multisite installation when the WooCommerce plugin is network activated.

= 1.3.8 =

* Improvements - Add new option for the base fee.
* Improvements - Add new option for round-up distance.

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

* Fix - Fix issue with localization decimal delimiter.
* Improvements - Added language parameter for Maps API request.

= 1.3.1 =

* Fix - Fix API request URL.
* Improvements - Switched from WP-Cache Object to Transient.

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

* Improvements - Add filter to enable the city field in the shipping calculator form.
* Fix - Filter destination address, address_2, city, postcode fields for shipping calculator request.
* Fix - Change wrong API request param from mode to avoid.

= 1.2.0 =

* Feature - Set visibility distance info to the customer.

= 1.1.1 =

* Improvement - Enable WP Object Cache for API HTTP request to improve the speed and reduce request count to API server.
* Fix - Remove unused js code.

= 1.1.0 =

* Feature - Set cost calculation type per order or per item.
* Localization - Update POT file.

= 1.0.0 =

* Feature - Set shipping cost by-product shipping class.
* Feature - Set unlimited distances ranges.
* Feature - Set origin info by coordinates.
* Feature - Set distances unit: Mile, Kilometer.
* Feature - Set travel mode: Driving, Walking, Bicycling.
* Feature - Set route restrictions: Avoid Tolls, Avoid Highways, Avoid Ferries, Avoid Indoor.

== Upgrade Notice ==
= 2.1.0 =
WARNING: Upgrading to version 2.1.0 has some breaking changes. Some settings data will be lost and re-setup the plugin after the upgrade is required. Please upgrade wisely and carefully.

= 2.0.0 =
WARNING: Upgrading from version 1.x to version 2.x is a major update and has breaking changes. Some settings data will be lost and re-setup the plugin after the upgrade is required. Please upgrade wisely and carefully.
