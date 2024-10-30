<?php

namespace Terresquall\Imago;

if (!defined('ABSPATH')) wp_die('Cannot access this file directly.');

class API {
	
    static $api_url = 'https://api1.imago-images.de';
    private $api_user;
    private $api_password;

    public function __construct($api_user, $api_password) {
        $this->api_user = $api_user;
        $this->api_password = $api_password;
    }
	
	// Performs a cURL to check if a given Imago ID is stock or sport image.
	static function check_image_type($imago_id) {
		
		// Make a HTTP head request to see if the URL exists.
		$response = wp_remote_head("
			https://imago-images.com/sp/$imago_id",
			array(
				'timeout' => 15, // Set timeout to 15 seconds
				'headers' => array(
					'User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'
				),
				'sslverify' => true // Verify SSL certificate
			)
		);

		// If an error occurs, terminate this early and return false.
		if (is_wp_error($response)) {
			return false;
		}

		// Otherwise, get the response code and determine which database it belongs to.
		$http_code = wp_remote_retrieve_response_code($response);
		if ($http_code >= 200 && $http_code < 300) return 'sp';
		return 'st';
		
	}


    /**
     * Function to handle all API requests
     * @param string $endpoint The API endpoint
     * @param array $args The parameters for the query
     * @return array|false The decoded JSON response or false on failure
     */
    public function query($endpoint, $args, $headers = [], $method = 'post') {
		// If the username or password is empty, terminate the query early.
		if(empty($this->api_user) || empty($this->api_password))
			wp_send_json_error(array(
				'statusCode' => 202,
				'title' => esc_html__('API keys are missing.','imago-images'),
				'message' => esc_html__('Please enter your API keys in the Imago WordPress settings page.','imago-images')
			),202);
		
        $url = self::$api_url . $endpoint;
		
		// Format the params for our request.
		$params = array(
			'headers' => array(
				'Content-Type' => 'application/json',
				'x-api-user' => $this->api_user,
				'x-api-key' => $this->api_password
			),
			'body' => json_encode($args),
			'timeout' => 30
		);
		
		// What method do we use? Defaults to post.
		switch(strtolower($method)) {
			default:
				$response = wp_remote_post($url, $params);
				break;
			case 'get':
				unset($params['body']);
				$response = wp_remote_get($url, $params);
				if(empty($response)) return 'empty response: ' . $endpoint;
				break;
		}
		
		// If it is an error, return the error itself.
        if (is_wp_error($response)) return $response;

		// Attempt to parse the string as a JSON if it is a string.
        if(is_string($response)) $response = json_decode($response, true);
        
        return wp_remote_retrieve_body($response);
    }
	
	// When we call this function, it will try to get
	// all the images from the highest to the lowest.
	// public function check_permissions($args) {
		
	// }

    /**
     * Handle the search endpoint
     * @param $args The array of params to query
     * @return array|false The decoded JSON response or false on failure
     */
    public function search($args, $preview = true) {
		
		// Check if the search value is an integer.
		// If it is, switch over to an image ID.
		$pic_id = intval($args['querystring']);
		if($pic_id > 0) {
			$args['pictureid'] = $pic_id;
			unset($args['querystring']);
		}
		
		// The result.
        $response = json_decode($this->query('/search', $args),true);
		
		// If the response is an error, return it immediately.
		if(isset($response['error'])) return $response;
		
		// Do we include preview image URLs in the search.
        if($preview){
            $img_links = self::preview_response_pictures($response);
            if($img_links) return $img_links;
        }
		
		return $response;
    }

    /**
     * Handle the download endpoint
     * @param $args The array of params to query
     * @return array|false The decoded JSON response or false on failure
     */
    public function download($args, &$iptc = null) {
		
		// If maxres is set, extract and remove it first.
		if(isset($args['maxres'])) {
			$maxres = intval($args['maxres']);
			unset($args['maxres']);
		} else $maxres = 2560;
		
		// Send the query.
		$response = $this->query('/download', $args);
		
		// Check if response is JSON. If it is, return it as it is probably an error.
		$json = json_decode($response, true);
		if($json) return $json;
		
		// Return IPTC data.
		if(function_exists('\iptcparse')) $iptc = iptcparse($response);
		
		// Otherwise we attempt to save the image.
		$finfo = \finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = \finfo_buffer($finfo, $response);
        \finfo_close($finfo);
		
		// Get the image editing option.
		$image_editor = get_option(Settings::IMAGO_IMAGE_EDITOR, 'disabled');
		
		switch($image_editor) {
			case 'gd':
				// If GD is disabled, skip this as well.
				if(!Settings::$gd) break;
			
				// If the image is smaller than $maxres, don't try to edit it.
				$imagesize = getimagesizefromstring($response);
				if($imagesize === false) break;
				if(max($imagesize[0], $imagesize[1]) <= $maxres) break;
				
				if($imagesize[0] > $imagesize[1]) {
					$width = $maxres;
					$ratio = $width / $imagesize[0];
					$height = floor($ratio * $imagesize[1]);
				} else {
					$height = $maxres;
					$ratio = $height / $imagesize[1];
					$width = floor($ratio * $imagesize[0]);
				}
				
				// Downsize the image.
				$orig = imagecreatefromstring($response);
				$resized = imagecreatetruecolor($width,$height);
				if(imagecopyresampled($resized, $orig, 0, 0, 0, 0, $width, $height, imagesx($orig), imagesy($orig))) {
					ob_start();
					
					// Output different results based on image type.
					switch($mimeType) {
						default: case 'image/jpeg': case 'image/jpg':
							imagejpeg($resized, null, 60);
							break;
					}
					
					imagedestroy($resized);
					imagedestroy($orig);
					return ob_get_clean();
				} 

				// Remove all the image resources.
				imagedestroy($orig);
				imagedestroy($resized);
				unset($resized);
				
				break;
				
			case 'imagick':
				// If Imagick is disabled, skip this as well.
				if (!Settings::$imagick) break;
				
				// Resize the image using Imagick.
				$imagick = new \Imagick();
				$imagick->readImageBlob($response);
				$imagick->stripImage(); // Remove any potential profiles or comments.
				
				// If the image's size is less than $maxres, ignore.
				$width = $imagick->getImageWidth();
				$height = $imagick->getImageHeight();
				if(max($width,$height) <= $maxres) break;
				
				// If the image is larger than $maxres, resize it.
				if($width > $height) $imagick->scaleImage($maxres, 0);
				else $imagick->scaleImage(0, $maxres);
				
				// Overwrite the response.
				$response = $imagick->getImageBlob();
				break;
		}
		
		// Return the image response.
        switch($mimeType) {
			case 'image/jpeg': case 'image/jpg':
				return $response;
        }
		
        error_log(print_r($mimeType, true));
        return false; 
    }

    /**
     * Convert the response into an array of image links
     * @param array $response The response
     * @return array|false The array of links or false on failure
     */
    public static function preview_response_pictures($response)
    {
        // Check if response[1]['pictures'] exists
        if (!isset($response[1]['pictures'])) {
            return false;
        }

        $urls = [];
        $picture_data = $response[1]['pictures'];
        foreach ($picture_data as $p_data) {
            // Check if the element has 'pictureid' and 'db'
            if (isset($p_data['pictureid'], $p_data['db'])) {
                $urls[] = self::preview_image_url($p_data['pictureid'], $p_data['db']);
            }
        }

        return $urls;
    }

    /**
     * Generate the url for the preview image
     * @param string $picture_id The 'pictureid' value of the picture response. 
     * @param string $db The 'db' value of the picture response, which is the specific database where the picture is located. Expected to be either 'stock' or 'sport'. 
     * @param string $resolution Picture resolution of the preview image. The values are: thumbnail 192px; small 420px; medium 1000px
     */
    public static function preview_image_url($picture_id, $db, $resolution = 'smalls')
    {
        //The image db accepts only either st (stock) or sp (sport)
        $db = substr($db, 0, 2);
        return self::$api_url . "/$db/$picture_id/$resolution";
    }
	
	// Checks if a response is a failure.
	static function is_response_failure($response) {
		if(empty($response)) return true;
		
		// The following status codes are failures.
		switch($response['statusCode']) {
			case 500: case 405:
				return true;
		}
		
		return false;
	}
}
