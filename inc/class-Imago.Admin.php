<?php

namespace Terresquall\Imago;

if (!defined('ABSPATH')) wp_die('Cannot access this file directly.');

class Admin {

	static $plugin_data;
    static $api;

    static function after_setup_theme() {

        // This class only needs to handle the admin side of things
        require_once IMAGO_TERRESQUALL_PLUGIN_DIR . 'inc/class-Imago.API.php';
		
		// Import the attachments class.
		require_once IMAGO_TERRESQUALL_PLUGIN_DIR . 'inc/attachments/class-Imago.Attachments.Admin.php';
		Attachments\Admin::after_setup_theme();

        if (!function_exists('get_plugin_data')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        self::$plugin_data = \get_plugin_data(IMAGO_TERRESQUALL_PLUGIN_FILE);

        //Create Settings page
        $api_user = get_option(Settings::API_USER_OPTION);
        $api_password = get_option(Settings::API_PASSWORD_OPTION);

        add_filter('plugin_action_links_' . plugin_basename(IMAGO_TERRESQUALL_PLUGIN_FILE), [__CLASS__, 'plugin_links']);
		
        // Create a new API instance
        self::$api = new API($api_user, $api_password);
		add_filter('register_block_type_args', array(__CLASS__,'register_block_type_args'), 9203, 2);
        add_action('admin_enqueue_scripts', array(__CLASS__, 'admin_enqueue_scripts'), 9898);
        add_filter('image_add_caption_text', array(__CLASS__,'image_add_caption_text'), 9898, 2);
        add_action('wp_ajax_imago_query', array(__CLASS__, 'wp_ajax_imago_query'));
        add_action('wp_ajax_imago_add_to_media_library', array(__CLASS__, 'wp_ajax_imago_add_to_media_library'));
		
		// Add templates for media library.
		add_action('admin_footer', array(__CLASS__, 'admin_footer'));
    }
	
	// Automatic captioning for shortcode in a non-Gutenberg context.
	static function image_add_caption_text($caption, $id) {
		
		// If this is not an Imago image, abort.
		$imago_source_id = get_post_meta($id, Core::IMAGO_SOURCE_ID_META_KEY, true);
		if(empty($imago_source_id)) return $caption;
		
		// Otherwise retrieve the credits.
		$creditSettings = get_option(Settings::AUTOMATIC_IMAGE_CREDIT_OPTIONS, array());
		$imagoCredit = get_option(Settings::IMAGO_BACKLINK_CREDIT_TEXT, Settings::DEFAULT_IMAGO_BACKLINK_CREDIT_TEXT);
		$appendix = array();
		
		// Check for the description.
		$description = get_post_field('post_content', $id);
		if(!empty($description)) array_push($appendix, $description);
		
		// Add the image credit.
		if(!in_array('no-image-credits', $creditSettings)) {
			array_push($appendix, str_replace('[IMAGO_URL]', Core::IMAGO_IMAGE_BASE_URL . $imago_source_id, $imagoCredit));
		}
		
		// Output the credit.
		return sprintf('%s<span role="imago-caption">%s%s</span>', $caption, empty($description) ? '' : ' | ', implode(', ', $appendix));
	}
	
	static function register_block_type_args($args, $type) {
		if($type === 'core/image' && isset($args['attributes'])) {
			$args['attributes']['imagoId'] = array('type' => 'number');
		}
		return $args;
	}

    static function plugin_links($links){
        $url = esc_url( add_query_arg(
            'page',
            Settings::PAGE_SLUG,
            get_admin_url() . 'options-general.php'
        ) );

        // Adds the link to the end of the array.
        array_push(
            $links,
			sprintf('<a href="%s">%s</a>', $url, esc_html__('Settings','imago-images'))
        );
        return $links;
    }
	
	static function admin_footer() {
		require_once IMAGO_TERRESQUALL_PLUGIN_DIR . '/views/templates.php';
	}

    static function admin_enqueue_scripts() {
		if ( wp_script_is( 'media-views', 'enqueued' ) ) {
			wp_enqueue_style('imago-tab', IMAGO_TERRESQUALL_PLUGIN_URL . 'assets/css/imagotab.css', null, self::$plugin_data['Version'], 'all');
			wp_enqueue_script('imago-tab', IMAGO_TERRESQUALL_PLUGIN_URL . 'assets/js/imagotab.js', array('jquery', 'backbone', 'underscore'), self::$plugin_data['Version'], true);
			wp_localize_script('imago-tab', 'imagoTabSettings', array(
				'ajaxurl' => admin_url('admin-ajax.php')
			));

			wp_enqueue_script('imago-gutenberg-image-block', IMAGO_TERRESQUALL_PLUGIN_URL . 'assets/js/gutenberg-image-block.js', array('wp-blocks','wp-editor'), self::$plugin_data['Version'], true);
			wp_localize_script('imago-gutenberg-image-block', 'gutenbergImageBlockSettings', array(
				'notAllowed' => trailingslashit(IMAGO_TERRESQUALL_PLUGIN_URL) . Core::IMAGO_IMAGE_NOT_ALLOWED_RELATIVE_URL,
				'imagoCredit' => get_option(Settings::IMAGO_BACKLINK_CREDIT_TEXT, Settings::DEFAULT_IMAGO_BACKLINK_CREDIT_TEXT),
				'creditSettings' => get_option(Settings::AUTOMATIC_IMAGE_CREDIT_OPTIONS, array()),
				'imagoBaseUrl' => Core::IMAGO_IMAGE_BASE_URL
			));
		}
    }

    // Ajax callback to download the imago image and then upload it to the site.
    static function wp_ajax_imago_add_to_media_library() {

        // Required: Picture id and db 
        $id = intval($_POST['pictureid']);
        $db = sanitize_text_field($_POST['db']);

        if (!isset($id, $db))
            wp_send_json_error('You need a <pictureid> and <db> in post data to download.');

		// Set the filename of the file we are saving.
        $filename = isset($_POST['filename']) ? sanitize_file_name($_POST['filename']) : $id;
		
        // Download the image.
		$iptc = null;
        $response = self::$api->download(array(
            'pictureid' => $id,
            'db' => $db,
            'res' => empty($_POST['res']) ? 9 : max(intval($_POST['res']), 1),
			'maxres' => empty($_POST['maxres']) ? 2560 : max(intval($_POST['maxres']),1)
		), $iptc);
		
        if ($response == false) 
			wp_send_json_error( sprintf(esc_html__('Empty response received.','imago-images')) );
		elseif(isset($response['error']))
			wp_send_json_error( implode(': ',$response['error']) );

		// If the response is an error, send an error.
        if (isset($response['error'])) {
            wp_send_json_error($response['error']);
            return;
        }

        // Check if the response has any errors
        $img = wp_upload_bits($filename . '.jpg', null, $response);
		$caption = isset($_POST['caption']) ? sanitize_text_field($_POST['caption']) : '';
		$description = array();
		
		// Attempt to extract the image author from the caption.
		if(!empty($caption)) {
			
			// $options = get_option(Settings::AUTOMATIC_IMAGE_CREDIT_OPTIONS, array());
			
			// if(is_array($options) && in_array('auto-extract-author-credit',$options)) {
				// // Extract the author based on the regex and put it in the descriptions.
				// $regex = get_option(Settings::IMAGE_AUTHOR_REGEX_OPTION);
				// if(preg_match($regex,$caption,$match)) {
					// if(!empty($match[1])) array_push($description, $match[1]);
					// $caption = str_replace($match[0],'',$caption);
				// }
			// }
			
			// If there is a valid description, add those as well.
			if(!empty($iptc)) {
				$credit = empty($iptc['2#80'][0]) ? (empty($iptc['2#110'][0]) ? '' : $iptc['2#110'][0]) : $iptc['2#80'][0];
				if(!empty($credit)) {
					array_push($description, $credit);
				}
			}
		}
		
		// If there is an image source from Imago, add that as well.
		if(!empty($_POST['source'])) array_push($description, sanitize_text_field($_POST['source']));
		
        //Insert the image to the media library
        $attachment = array(
            'guid'           => $img['url'],
            'post_mime_type' => $img['type'],
            'post_title'     => $filename,
            'post_content'   => implode(' / ', $description),
            'post_excerpt'  => $caption,
            'post_status'    => 'inherit'
        );

        $attach_id = wp_insert_attachment($attachment, $img['file']);

        // Set image metadata (e.g. caption)
        $metadata = wp_generate_attachment_metadata($attach_id, $img['file']);
        wp_update_attachment_metadata($attach_id, $metadata);

        // Set alt text
        update_post_meta($attach_id, '_wp_attachment_image_alt', $caption);
        update_post_meta($attach_id, Core::IMAGO_SOURCE_ID_META_KEY, substr($db, 0, 2) . '/' . $id);

        wp_send_json_success($attach_id);
    }

	static function wp_ajax_imago_query() {
		$api = self::$api;
		
		// Query data (can be used as result later too).
		$data = array(
			'per_page' => 20,
			'pagination' => isset($_POST['page']) ? intval($_POST['page']) : 0,
			'search_value' => isset($_POST['search_value']) ? sanitize_text_field($_POST['search_value']) : ''
		);
	
		// Run the API and get a response.
		$response = $api->search(array(
            'querystring' => str_replace("'",' ',$data['search_value']),
            'size' => $data['per_page'],
            'sortby' => 'datecreated',
            'sort' => 'desc',
            'from' => $data['pagination'] * $data['per_page']
        ), false);
		
		// If the response is an error, return it right away.
		if(!empty($response['error'])) {
			wp_send_json_error($response);
			return;
		}
		
		// Grab the image data to be processed.
		if (isset($response[1]['pictures'])) {
			$data['image_data'] = $response[1]['pictures'];
			
			foreach($data['image_data'] as $k => $v) {
				if(isset($v['pictureid'], $v['db']))
					$data['image_data'][$k]['url'] = $api->preview_image_url($v['pictureid'], $v['db']);
			}
		} else $data['image_data'] = $response;
		
		$data['total_results'] = isset($response[0]['total']) ? $response[0]['total']: $data['per_page']; 
        $data['total_pages'] = ceil($data['total_results'] / $data['per_page']);
		
		wp_send_json_success($data);
	}
}
