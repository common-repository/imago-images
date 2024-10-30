<?php

namespace Terresquall\Imago;

if (!defined('ABSPATH')) wp_die('Cannot access this file directly.');

class Core {

	const IMAGO_SOURCE_ID_META_KEY = 'imago_source_id';
	const IMAGO_IMAGE_BASE_URL = 'https://www.imago-images.com/';
	
	const IMAGO_IMAGE_NOT_ALLOWED_RELATIVE_URL = 'assets/images/no-copyright-image.jpg';

    static function after_setup_theme() {
		
		// Register imago_source_id for Gutenberg.
		register_post_meta('attachment', self::IMAGO_SOURCE_ID_META_KEY, array(
			'auth_callback' => '__return_true',
			'show_in_rest' => true,
			'single' => true,
			'type' => 'string'
		));

		// Hook attachment for handling settings.
		require_once IMAGO_TERRESQUALL_PLUGIN_DIR . 'inc/class-Imago.Settings.php';
		Settings::after_setup_theme();

        // This plugin only needs to handle the admin side of things
        if(is_admin()) {
			require_once 'class-Imago.Admin.php';
			Admin::after_setup_theme();
		} else {
			add_filter('get_the_excerpt',array(__CLASS__,'get_the_excerpt'), 4834, 2);
			add_filter('wp_get_attachment_url',array(__CLASS__,'wp_get_attachment_url'), 4834, 2);
		}
		
		// Hook actions for handling attachments.
		require_once 'attachments/class-Imago.Attachments.Core.php';
		Attachments\Core::after_setup_theme();
    }
	
	static function wp_get_attachment_url($url, $attachment) {
		
		// Don't do any processing if it is not an attachment.
		if(get_post_field('post_type',$attachment) !== 'attachment') return $url;
		
		// Get the options.
		$options = get_option(Settings::AUTOMATIC_IMAGE_CREDIT_OPTIONS);
		if(!in_array('require-author-crediting',$options)) return $url;
		
		// Ignore images that come before us.
		$date_before = get_option(Settings::IGNORE_BLOCKING_IMAGES_BEFORE_OPTION);
		if(strtotime($date_before) > strtotime(get_post_field('post_date',$attachment))) return $url;
		
		// See if the attachment has an Imago source.
		$imago_id = get_post_meta( get_post_field('ID',$attachment), self::IMAGO_SOURCE_ID_META_KEY, true);
		
		// If there is an Imago source, then intercept the URL if the image description is empty.
		if($imago_id) {
			$post_content = get_post_field('post_content',$attachment);
			if(empty($post_content))
				return trailingslashit(IMAGO_TERRESQUALL_PLUGIN_URL) . Core::IMAGO_IMAGE_NOT_ALLOWED_RELATIVE_URL;
		}
		
		return $url;
	}
	
	static function get_the_excerpt($caption, $attachment) {
		
		// Don't do any processing if it is not an attachment.
		if(get_post_field('post_type',$attachment) !== 'attachment') return $caption;
		
		// See if the attachment has an Imago source.
		$imago_id = get_post_meta( get_post_field('ID',$attachment), self::IMAGO_SOURCE_ID_META_KEY, true);
		if($imago_id) {

			// Compatibility with Imago IDs from older versions.
			if(is_numeric($imago_id)) {
				$imago_id = 'sp/' . $imago_id;
			}

			// Get the options.
			require_once IMAGO_TERRESQUALL_PLUGIN_DIR . 'inc/class-Imago.Settings.php';
			$options = get_option(Settings::AUTOMATIC_IMAGE_CREDIT_OPTIONS);
			$noImageCrediting = in_array('no-imago-credits',$options);
			
			// Content to append at the end.
			$appendix = array();
			
			$post_content = get_post_field('post_content',$attachment);
			if(!empty($post_content)) {
				array_push($appendix, $post_content);
				
				// The other filters in here will automatically fill in the caption.
				// So we have to remove it if it matches with the description.
				if($post_content === $caption) $caption = '';
			}
			
			if(!$noImageCrediting) {
				$credit = get_option(Settings::IMAGO_BACKLINK_CREDIT_TEXT, Settings::DEFAULT_IMAGO_BACKLINK_CREDIT_TEXT);
				array_push($appendix, str_replace('[imago_url]',self::IMAGO_IMAGE_BASE_URL . $imago_id,$credit));
			}
			
			// Don't add the separator if the caption is empty.
			$imago_credit = '';
			if(!empty(trim($caption))) $imago_credit .= ' | ';
			$imago_credit .= implode(', ',$appendix);
			return sprintf('%s<span class="imago-caption">%s</span>', $caption, $imago_credit);
		}
		
		return $caption;
	}
}
