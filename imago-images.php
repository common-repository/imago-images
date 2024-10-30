<?php

/**
 * Plugin Name: Imago Images
 * Description: Connects the WordPress Media Library to the Imago Images library. Adds a new Import from Imago tab to the Media Library popup that makes importing images from Imago more convenient.
 * Author: Imago Images
 * Version: 0.8.1
 * Requires PHP: 7.2
 * License: GPLv2 or later
 * License URL: https://www.gnu.org/licenses/gpl-2.0.html
 * Author URI: https://www.imago-images.com
 */

use Imago\API;

if (!defined('ABSPATH')) exit; // Prevent direct access.

define('IMAGO_TERRESQUALL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('IMAGO_TERRESQUALL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('IMAGO_TERRESQUALL_PLUGIN_FILE', __FILE__);

// Load core plugin scripts.
require_once IMAGO_TERRESQUALL_PLUGIN_DIR . 'inc/class-Imago.Core.php';
add_action( 'after_setup_theme', array('\Terresquall\Imago\Core','after_setup_theme') );