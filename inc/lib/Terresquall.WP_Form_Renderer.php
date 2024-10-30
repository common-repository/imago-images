<?php namespace Terresquall\Imago;

/*
Update 24 December 2023:
- Added ability to add attributes (such as disabling it) to select options.

Update 10 October 2023:
- Remove the <br/> in the multiple checkbox / radio rendering.
- Get the multiple checkbox option working with the value you input.

Update 13 June 2023:
- Fixed error in line 282.

Update 22 June 2023:
- Added multiple support for select statements.
- Pass an array to value to select multiple items.

Update 14 June 2023:
- Fixed some display bugs for the fields.
- Made wp_media take a data-library field that will determine what files it takes.
*/

if(!defined('ABSPATH')) wp_die('Cannot access this file directly.');

// Don't load this class more than once.
if(class_exists('\\Terresquall\\WP_Form_Renderer')) return;

// Class for rendering HTML for various elements.
class WP_Form_Renderer {
	
	const VERSION = '0.4.0';
	const LAST_UPDATED = '24 December 2023';
	
	// Internal check to see if we have added the script.
	const WP_MEDIA_OBJECT_CLASS = 'ts-wp-media-object';
	private static $has_wp_media_footer_js = false;
	
	static $form_field_wrapper = '<p>%s</p>';
	static $wp_media_wrapper  = '<div>%s</div>';
	
	static function output_admin_form_table($args, $attributes = array()) {
		// Add the form-table class.
		if(empty($attributes)) $attributes = array('class' => 'form-table');
		elseif(empty($attributes['class'])) $attributes['class'] = 'form-table';
		else $attributes['class'] .= 'form-table';
		
		// Output the HTML.
		if(gettype($args) === 'array') {
			$output = '';
			foreach($args as $v) $output .= $v;
			return sprintf('<table%s>%s</table>', self::_get_associative_array_string($attributes, ' '), $output);
		}
		return false;
	}
	
	// Renders a form field wrapped in a table row for the WP admin.
	// $type - Type / HTML tag of the field.
	// $label - What goes in the label.
	// $attributes - Associative array of all attributes that the element has.
	// $options - Only for dropdowns, checkboxes and radios. Associative array of available options.
	static function draw_admin_form_field($type, $label, $attributes = array(), $options = array()) {
		
		// Add appropriate classes to elements.
		if(empty($attributes['class'])) {
			if($type === 'checkbox') $attributes['class'] = 'checkbox';
		}
		
		$elem = self::draw_form_element($type, $attributes, $options);
		$label_for = array_key_exists('id',$attributes) ? sprintf(' for="%s"',$attributes['id']) : '';
		
		// Return the element wrapped in a table.
		if($type === 'checkbox' && empty($options)) {
			return sprintf('<tr><th scope="row"><label%s>%s</label></th><td>%s</td></tr>',$label_for,$label,$elem);
		} else {
			return sprintf(
				'<tr><th scope="row"><label%s>%s</label></th><td>%s</td></tr>',
				$label_for,$label,$elem
			);
		}
	}
	
	// Renders a form field wrapped in a table row for the WP Elementor frontend.
	// $type - Type / HTML tag of the field.
	// $label - What goes in the label.
	// $attributes - Associative array of all attributes that the element has.
	// $options - For dropdowns, checkboxes and radios, it is an associative array of available options.
	//			  For wp_media window, this will be an array of the files / file types that you want available for selection.
	static function draw_form_field($type, $label, $attributes = array(), $options = array()) {
				
		$elem = self::draw_form_element($type, $attributes, $options);
		$label_for = array_key_exists('id',$attributes) ? sprintf(' for="%s"',$attributes['id']) : '';
		
		// Return the element wrapped in a paragraph element.
		if($type === 'checkbox' && empty($options)) {
			return sprintf(
				self::$form_field_wrapper,
				sprintf('<label%s>%s %s</label>',$label_for,$elem,$label)
			);
		} elseif($type === 'wp_media') {
			$label_for = array_key_exists('id',$attributes) ? sprintf(' for="%s"',$attributes['id'] . '-upload-btn') : '';
			return sprintf(
				self::$wp_media_wrapper,
				sprintf(
					'<label%s>%s</label>%s',
					$label_for,$label,$elem
				)
			);
		} else {
			return sprintf(
				self::$form_field_wrapper,
				sprintf(
					'<label%s>%s</label>%s',
					$label_for,$label,$elem
				)
			);
		}
	}
	
	// Generate a form element without any labels.
	// $type - Type / HTML tag of the field.
	// $attributes - Associative array of all attributes that the element has.
	// $options - Only for dropdowns, checkboxes and radios. Associative array of available options.
	static function draw_form_element($type, $attributes = array(), $options = array()) {
		
		// Construct the element string.
		switch($type) {
			default:
				return sprintf(
					'<input type="%s"%s/>', $type,
					self::_get_associative_array_string($attributes,' ')
				);
				
			case 'textarea':
				// Extract the value attribute and remove it if it exists.
				$val = '';
				if(array_key_exists('value',$attributes)) {
					$val = $attributes['value'];
					unset($attributes['value']);
				}
				
				return sprintf(
					'<textarea%s>%s</textarea>',
					self::_get_associative_array_string($attributes,' '), $val
				);

			case 'select':
				// Extract all the options and construct a string.
				$optStr = '';
				$value = null;
				
				// Save and remove the value attribute, as the code is output in the <option> tag.
				if(!empty($attributes['value'])) {	
					$value = $attributes['value'];
					unset($attributes['value']);
				}
				
				// Do we have multiple selected options?
				$multiple = isset($attributes['multiple']) && is_array($value);
				
				if(self::_is_associative_array($options)) {
					foreach($options as $k => $v) {
						// Process selection differently if there are multiple options.
						if($multiple && in_array($k,$value)) {
							$selected = ' selected="y"';
						} else if($value === $k) {
							$selected = ' selected="y"';
						} else $selected = '';
						
						// If the value is an array, compile the attributes into a string.
						if(is_array($v)) {
							// Convert the array into an attribute string.
							if(empty($v[0])) $val = $k;
							else {
								$val = $v[0];
								unset($v[0]);
							}
							
							$optStr .= sprintf('<option value="%s"%s>%s</option>',$k,$selected . ' ' . self::_get_associative_array_string($v,' '),$val);
						} else 
							$optStr .= sprintf('<option value="%s"%s>%s</option>',$k,$selected,$v);
					}
				} else {
					foreach($options as $v) {
						if($value === $v) $selected = ' selected="y"';
						else $selected = '';
						$optStr .= sprintf('<option%s>%s</option>',$selected,$v);
					}
				}
				
				return sprintf(
					'<select%s>%s</select>',
					self::_get_associative_array_string($attributes, ' '),$optStr
				);

			case 'checkbox': case 'radio':
				
				if($type === 'checkbox' && empty($options)) {
					if(!array_key_exists('value',$attributes))
						$attributes['value'] = '1';
					
					return sprintf(
						'<input type="checkbox"%s/>',
						self::_get_associative_array_string($attributes,' ')
					);
				} else {
					// Prepare the name and ID attributes for multiple checkboxes / radios.
					$name = 'checkbox_elem';
					if(array_key_exists('name',$attributes)) {
						$name = $attributes['name'];
						unset($attributes['name']);
					}
					unset($attributes['id']);
					
					// Get the value attribute.
					$val = '';
					if(array_key_exists('value',$attributes)) {
						$val = $attributes['value'];
						unset($attributes['value']);
					}
					
					// Draw the element.
					$elem = '';
					$attrbStr = self::_get_associative_array_string($attributes,' ');
					foreach($options as $k => $v) {
						// Determines if the $checked should be empty.
						$checked = '';
						if(!empty($val)) {
							if(is_array($val)) $checked = in_array($k, $val) ? ' checked="y" ' : '';
							else $checked = $k === $val ? ' checked="y" ' : ''; 
						}
						
						$elem .= sprintf(
							'<label for="%1$s"><input type="%2$s" name="%3$s[]" id="%1$s" value="%1$s"%5$s%6$s/> %4$s</label>',
							$k, $type, $name, $v, $attrbStr, $checked
						);
					}
				}
				
				return $elem;
				
			case 'wp_media':
				// Get the value.
				$val = '-1';
				$img_src = '';
				$filename = esc_html__('Loading…','terresquall-wp-form-renderer');
				if(array_key_exists('value',$attributes)) {
					$val = $attributes['value'];
					unset($attributes['value']);
					
					$id = intval($val);
					if($id > -1) {
						$attachment = wp_get_attachment_image_src($id,'thumbnail',true);
						$img_src = sprintf(
							'src="%s" ',
							$attachment[0]
						);
						$filename = basename(wp_get_attachment_url($id));
					}
				}
				
				// Get the ID.
				$id = 'wp_media_upload';
				if(array_key_exists('id',$attributes)) {
					$id = $attributes['id'];
					unset($attributes['id']);
				}
				
				// Get the name.
				$name = 'wp_media_upload';
				if(array_key_exists('name',$attributes)) {
					$name = $attributes['name'];
					unset($attributes['name']);
				}
				
				// Remove the class attribute and form the attribute string.
				unset($attributes['class']);
				$attrbStr = self::_get_associative_array_string($attributes,' ');
			
				$elem = sprintf(
					'<input type="hidden" size="36" name="%1$s" id="%2$s" value="%3$s"%4$s/><input type="button" name="%1$s-upload-btn" id="%2$s-upload-btn" class="button" value="%5$s"/>',
					$name, $id, $val, $attrbStr,
					esc_html__('Browse Media Library','terresquall-wp-form-renderer')
				);
				$preview = sprintf(
					'<figure id="%s-preview-container" style="margin:0.3em 0;padding:0;"><a href="javascript:" data-action="open" style="display:inline-block;"><img %sstyle="max-width:150px;border:1px solid #eee;border-radius:2px;padding:4px;"/></a><figcaption>%s</figcaption><a href="javascript:" data-action="remove">%s</a></figure>',
					$id, $img_src, $filename, esc_html__('Remove','terresquall-wp-form-renderer')
				);
				
				// Hook supporting actions to support.
				add_action('admin_print_footer_scripts', array(__CLASS__,'wp_media_admin_footer_script'));
				
				return sprintf(
					'<section id="%s-upload-container" class="%s"%s>%s%s</section>',
					$id, self::WP_MEDIA_OBJECT_CLASS,
					empty($options) ? '' : sprintf(' data-library="%s"',implode('|',$options)),
					$preview, $elem
				);
		}
	}
	
	// Check if an array is associative.
	private static function _is_associative_array($arr) {
		if (array() === $arr) return false;
		return array_keys($arr) !== range(0, count($arr) - 1);
	}
	
	// Convert an associate array to a set of HTML attributes.
	private static function _get_associative_array_string($attributes, $prefix = '') {
		if(!is_array($attributes) || count($attributes) <= 0) return '';
		
		$result = $prefix;
		foreach($attributes as $k => $v) {
			if(gettype($v) !== 'string') continue;
			$result .= sprintf('%s="%s" ',$k,$v);
		}
		return rtrim($result);
	}
	
	static function wp_media_admin_footer_script() { ?>
<script>
(function($) {
	'use strict';
	
	// Catch errors.
	if(typeof wp === 'undefined' || typeof wp.media === 'undefined')
		console.error('wp.media is undefined. Please call wp_enqueue_media() in the PHP backend first, or the WordPress media popup will not work.');
	
	if(typeof Backbone === 'undefined')
		console.error('Backbone is not defined. Please load Backbone on WordPress.');
	
	// Declare the class.
	var WPMediaInputView = Backbone.View.extend({
		'initialize': function(options) {
			_.bindAll(this,'openMedia','handleLinkClick','onMediaSelected');
			
			// Retrieve configs from the tag.
			this.mediaArgs = {
				'title': this.el.getAttribute('data-title') ? this.el.getAttribute('data-title') : 'Select a file',
				'button': {
					'text': this.el.getAttribute('data-button') ? this.el.getAttribute('data-button') : 'Choose file'
				},
				'multiple': false
			};
			if(this.el.getAttribute('data-library'))
				this.mediaArgs['library'] = { 'type': this.el.getAttribute('data-library').split('|') };
			
			// Initialise the uploader.
			this.wpMedia = wp.media(this.mediaArgs).on('select',this.onMediaSelected);
			
			// Get the inputs.
			var $inputs = this.$el.children('input');
			this.$inputValue = $inputs.filter('[type="hidden"]');
			this.$inputButton = $inputs.filter('[type="button"]').on('click', this.openMedia);
			
			// Get the fields to input.
			this.$figure = this.$el.children('figure');
			this.$img = this.$figure.children('img');
			this.$caption = this.$figure.children('figcaption');
			
			// Hide the figure if there is no image, otherwise show it.
			var value = this.$inputValue.prop('value');
			if(parseInt(value) <= -1) this.$figure.hide();
		},
		
		'events': {
			'click a': 'handleLinkClick',
			'click input[type="button"]': 'openMedia'
		},
		
		'openMedia': function(evt) {
			this.wpMedia.open();
			
			// Select the currently selected file.
			var selection = this.wpMedia.state().get('selection');
			if(selection) {
				var idx = parseInt(this.$inputValue.val());
				if(idx > -1)
					selection.add( wp.media.attachment(idx) );
				else
					selection.reset();
			}
		},
		
		'handleLinkClick': function(evt) {
			switch(evt.currentTarget.getAttribute('data-action')) {
				case 'remove':
					this.$figure.hide();
					this.$inputValue.val("-1");
					break;
				case 'open':
					this.openMedia(evt);
					break;
			}
		},
		
		// Callback for when a media file gets selected.
		'onMediaSelected': function() {
			var attachment = this.wpMedia.state().get('selection').first().toJSON();
			
			// Show the image and update the ID.
			this.$figure.show();
			this.$caption.show().text(attachment.filename);
			this.$img.show().prop('src',attachment.icon);
			this.$inputValue.val(attachment.id);
		}
	});
	
	$('.<?php echo self::WP_MEDIA_OBJECT_CLASS; ?>').each(function(i,e) {
		new WPMediaInputView({'el': e});
	});
})(jQuery);
</script><?php
	}
	
}