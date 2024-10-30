<?php

namespace Terresquall\Imago\Attachments;

if (!defined('ABSPATH')) wp_die('Cannot access this file directly.');

class Admin {

	const DEBUG_MODE = false;

    static function after_setup_theme() {
		add_action('add_meta_boxes', array(__CLASS__,'add_meta_boxes'));
		add_action('edit_attachment', array(__CLASS__,'edit_attachment'), 10203, 1);
		
		//add_action('wp_ajax_scan_attachment_imago_metadata', array(__CLASS__,'wp_ajax_scan_attachment_imago_metadata'));
    }
	
	// Not used at the moment.
	// Works together with the code in assets/gutenberg-image-block.js to
	// refresh and reload the image metadata.
	static function wp_ajax_scan_attachment_imago_metadata() {
		if(!empty($_POST['attachmentId'])) {
			$attachment_id = intval($_POST['attachmentId']);
			
			if($attachment_id > 0) {
				$imago_id = get_post_meta($attachment_id, \Terresquall\Imago\Core::IMAGO_SOURCE_ID_META_KEY, true);
				if($imago_id) {
					wp_send_json_success( sprintf(
						esc_html__('Image attachment %s already has metadata.','imago-images'),
						$attachment_id
					) );
				} else {
					Core::add_attachment($attachment_id);
					wp_send_json_success( sprintf(
						esc_html__('Found image metadata for attachment %s and added it to the image. Please refresh the page and the caption will automatically apply.','imago-images'),
						$attachment_id
					) );
				}
				exit;
			} 
			
			wp_send_json_error( esc_html__('Invalid attachment ID for scanned image.','imago-images') );
			exit;
		}
		wp_send_json_error( esc_html__('Failed to scan for image metadata.','imago-images') );
		exit;
	}
	
	static function add_meta_boxes() {
		add_meta_box(
			'imago_meta_box', 'Imago WordPress',
			array(__CLASS__,'render_attachment_meta_box'),
			'attachment', 'side', 'low'
		);
		
		// Should we show the debug box in the Attachment page?
		$debug_settings = get_option(\Terresquall\Imago\Settings::IMAGO_ATTACHMENT_DEBUG_META, array());
		if(!empty($debug_settings) && in_array('show-debug-panel',$debug_settings)) {
			add_meta_box(
				'imago_iptc_debug_meta_box', 'Imago IPTC Debug',
				array(__CLASS__,'render_iptc_debug_meta_box'),
				'attachment', 'normal', 'low'
			);
			
			add_action('admin_head', array(__CLASS__,'admin_head'), 103);
		}
	}

	static function admin_head() { ?>
		<style>
		pre { overflow:auto;max-height:50vh;background:#eee; }
		</style><?php
	}
	
	static function render_attachment_meta_box($post) {
		require_once IMAGO_TERRESQUALL_PLUGIN_DIR . 'inc/lib/Terresquall.WP_Form_Renderer.php';
		echo wp_kses(\Terresquall\Imago\WP_Form_Renderer::draw_form_field('text', 'Imago Source ID', array(
			'name' => 'imago_source_id', 'id' => 'imago_source_id',
			'class' => 'widefat', 'value' => get_post_meta($post->ID, 'imago_source_id', true)
		)),array(
			'label' => array('for' => array()),
			'input' => array(
				'type' => array(),
				'name' => array(),
				'id' => array(),
				'class' => array(),
				'value' => array()
			)
		));
	}
	
	static function render_iptc_debug_meta_box($post) {
		
		// If the iptcparse function is not enabled, stop running this.
		if(!function_exists('\iptcparse')) {
			esc_html_e('iptcparse function not available.','imago-images');
			return;
		}
		
		// Check if the uploaded file is an image
		if(\wp_attachment_is_image($post->ID)) {
			
			// Read image metadata.
			$attached_file = EXIF::get_original_image_file($post->ID);
			$size = getimagesize($attached_file, $info);
			//var_dump(file_get_contents($attached_file));
			
			if(empty($info)) {
				esc_html_e('No metadata in image.','imago-images');
			} else { ?>
				<strong>filepath:</strong> <?php echo sanitize_file_name($attached_file); ?><br/>
				<strong>getimagesize():</strong>
				<pre><?php var_dump($size); ?></pre>
				<strong>getimagesize() $info:</strong>
				<pre><?php var_dump($info); ?></pre><?php
				
				if(!empty($info['APP13'])) { ?>
					<strong>IPTC info:</strong>
					<pre><?php 
						$iptc = iptcparse( $info['APP13'] );
						if(empty($iptc)) esc_html_e('None','imago-images');
						else var_dump($iptc);
					?></pre><?php
				} ?>
				<strong>exif_read_data():</strong>
				<pre><?php var_dump(exif_read_data($attached_file)); ?></pre><?php
			}
			return;
		}
		
		esc_html_e('Attachment is not an image.','imago-images');
	}
	
	static function edit_attachment($post_id) {

		// Ignore if user cannot edit this, or if this is an autosave.
        if(!current_user_can('edit_post', $post_id)) return;
        if(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
		
		// If the Imago source ID isn't empty, try saving it if it is an integer.
		if(!empty($_POST['imago_source_id'])) {
			update_post_meta($post_id, 'imago_source_id', sanitize_text_field($_POST['imago_source_id']) );
		}
	}
}