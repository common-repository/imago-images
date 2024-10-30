<?php

namespace Terresquall\Imago\Attachments;

if (!defined('ABSPATH')) wp_die('Cannot access this file directly.');

class EXIF {

	static $current_file;
	
	static function get_iptc($filepath) {
		
		// Get the file.
		$size = @getimagesize($filepath, $info);
		if(function_exists('\iptcparse') && $size) $iptc = iptcparse($info);
		
		// If IPTC is empty, try other ways to retrieve it.
		if(empty($iptc)) return null;
		return $iptc;
		
	}
	
	static function get_original_image_file($attachment_id, $return_next_best = true) {
		// Get the attachment metadata
		$attachment_metadata = \wp_get_attachment_metadata($attachment_id);
		$filepath = \get_attached_file($attachment_id);

		// Check if metadata exists and if it contains the original file path
		if ($attachment_metadata) {
			
			// If the original image is empty, return the original one.
			if(empty($attachment_metadata['original_image'])) return $filepath;
			
			// Construct the full path to the original image file
			$original_file_path = trailingslashit(dirname($filepath)) . $attachment_metadata['original_image'];
			
			// Check if the original file exists
			if (file_exists($original_file_path)) {
				return $original_file_path;
			}
		}
		
		if($return_next_best)
			return get_attached_file($attachment_id);
		return false;
	}
	
}