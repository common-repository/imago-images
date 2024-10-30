=== Imago Images ===
Contributors: terresquall
Donate link: https://paypal.me/terresquall
Tags: imago, image, connector
Requires at least: 5.0
Tested up to: 6.5.3
Stable tag: 0.8.1
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connects the WordPress Media Library to the Imago Images library. Adds a new Import from Imago tab to the Media Library for importing Imago images.

== Description ==

**NOTE:** This plugin is still in BETA. If you find any bug with this plugin, please report it to https://www.imago-images.com/contact and we will fix it as soon as possible.

Official plugin of Imago Images.

This plugin connects the WordPress Media Library to the Imago Images library. Adds a new Import from Imago tab to the Media Library popup that makes importing images from Imago more convenient.

There are 2 ways to use this plugin:

**Route 1: Upload Imago media that is downloaded into your local device (PC / Laptop / Tablets / Mobiles)**
1. While you are writing a new Post, Page or any WordPress Custom Post Type, simply add a new image element into your post (with either the Classic Editor or the new Gutenberg Editor).
2. Add a downloaded Imago media from your device, and this plugin will automatically identify the image using image metadata.

**Route 2: Access through an API Key**
1. Get a set of API keys from Imago Images here: https://www.imago-images.com/contact
2. Enter the API keys into the Settings page of the plugin under Settings > Imago Images.
3. To use this plugin, insert an image into any WordPress page or post using the Classic Editor or the Gutenberg Editor.

*This plugin uses API services from https://api1.imago-images.de to provide its search and import functionality.* By using this plugin, you are implicitly agreeing to [Imago's Terms of Use](https://www.imago-images.com/terms-of-use).

== Frequently Asked Questions ==

= How do I get an API key? =

To begin using the plugin, you will need to have a set of API keys from Imago. You can do so by contacting them here: https://www.imago-images.com/contact

= Does it work with 3rd party WordPress editors, like Elementor or WP Bakery? =

For now, the plugin only works with 3rd party editors that use the WordPress Media Library. If the 3rd party editor does so, this plugin will support it.

== Changelog ==

= 0.8.1 =
* Fixed a bug that caused an image directly uploaded from the Image Gutenberg block to not be scanned for metadata and identified as an Imago image.
* Fixed the image crediting not happening when using the Classic Editor.
* Fixed some instances in the plugin where certain configurations will cause errors that render the Gutenberg Image block unusable.

= 0.8.0 =
* First release of the plugin.

== Upgrade Notice ==

= 0.8.1 =
* To access this version, update the plugin normally via WordPress.

= 0.8.0 =
* First release of the plugin.

== Screenshots ==

1. This plugin adds a new Import from Imago option on the WordPress Media Library.
2. Search for an image, add it to your Media Library and you can start using it!
3. In the Gutenberg Editor, use the Image block normally to add images. To load images from Imago directly, go to the Media Library > Import from Imago.