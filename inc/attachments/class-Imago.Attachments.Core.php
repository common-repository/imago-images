<?php

namespace Terresquall\Imago\Attachments;

if (!defined('ABSPATH')) wp_die('Cannot access this file directly.');

class Core {

    static function after_setup_theme() {
		
		require_once 'class-Imago.Attachments.EXIF.php';
		add_action('add_attachment', array(__CLASS__,'add_attachment'), 10203, 1);
		
		// Import the attachments class.
		if(is_admin()) {
			require_once 'class-Imago.Attachments.Admin.php';
			Admin::after_setup_theme();
		}
    }
	
	// Check if the image is an Imago image when it is uploaded.
	// NOTE: Currently doesn't reset image. That only works with the image size.
	static function add_attachment($attachment_id) {
		
		// If the iptcparse function is not enabled, stop running this.
		if(!function_exists('\iptcparse')) return;
		
		// Loads the API class if it is not loaded yet.
		require_once IMAGO_TERRESQUALL_PLUGIN_DIR . 'inc/class-Imago.API.php';
		
		// If the Imago ID is already registered.
		if(get_post_meta($attachment_id, \Terresquall\Imago\Core::IMAGO_SOURCE_ID_META_KEY)) return;
			
		$attached_file = EXIF::get_original_image_file($attachment_id);
		
		// Check if the uploaded file is an image
		if(!empty($attached_file)) {
			
			// Read image metadata
			$size = getimagesize($attached_file, $info);
			$iptc = empty($info['APP13']) ? false : iptcparse( $info['APP13'] );
			
			// If there is IPTC data, then process to see whether it is an Imago image.
			if ($iptc !== false) {
				
				// Retrieve IPTC data.
				$object_name = !empty($iptc['2#005'][0]) ? $iptc['2#005'][0] : false;
				$category = !empty($iptc['2#015'][0]) ? substr($iptc['2#015'][0],0,2) : false;
				
				// Check object_name key to see if it is an Imago image.
				if(!empty($object_name) && !empty($category) && preg_match('@imago (?:images )?([0-9]+)@i', $object_name, $matches)) {
					
					// If the match is empty, return.
					$imago_id = intval($matches[1]);
					if(empty($matches[1])) return;
					
					// Otherwise get the type.
					if(in_array($category, array('st','sp'))) $db = $category;
					else $db = \Terresquall\Imago\API::check_image_type($imago_id);
					update_post_meta($attachment_id, \Terresquall\Imago\Core::IMAGO_SOURCE_ID_META_KEY, $db . '/' . $imago_id);
					
					// If there is a valid caption, add those as well.
					$caption = empty($iptc['2#120'][0]) ? (empty($iptc['2#105'][0]) ? '' : $iptc['2#105'][0]) : $iptc['2#120'][0];
					if(!empty($caption)) {
						update_post_meta($attachment_id, '_wp_attachment_image_alt', $caption);
					}
					
					// If there is a valid description, add those as well.
					$credit = empty($iptc['2#80'][0]) ? (empty($iptc['2#110'][0]) ? '' : $iptc['2#110'][0]) : $iptc['2#80'][0];
					if(!empty($credit)) {
						wp_update_post(array(
							'ID' => $attachment_id,
							'post_content' => $credit
						));
					}
				}	
			}
		}
	}
}