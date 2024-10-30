<?php namespace Terresquall\Imago;

if (!defined('ABSPATH')) wp_die('Cannot access this file directly.');

class Settings {
	
    const PAGE_SLUG = 'imago-images';
    const OPTION_GROUP = 'imago_wordpress_options';
	
    const API_USER_OPTION = 'imago_wordpress_api_user';
    const API_PASSWORD_OPTION = 'imago_wordpress_api_password';
	
    const AUTOMATIC_IMAGE_CREDIT_OPTIONS = 'imago_wordpress_automatic_image_credit_options';
    const IMAGO_BACKLINK_CREDIT_TEXT = 'imago_wordpress_backlink_credit_text';
    const IMAGE_AUTHOR_REGEX_OPTION = 'imago_image_author_regex';
    const IGNORE_BLOCKING_IMAGES_BEFORE_OPTION = 'imago_ignore_blocking_images_before';
	
	const IMAGO_IMAGE_EDITOR = 'imago_image_editor';
	const IMAGO_IMAGE_MAX_RESOLUTION = 'imago_image_max_resolution';
	const IMAGO_ATTACHMENT_DEBUG_META = 'imago_image_debug';
	
	const DEFAULT_IMAGO_BACKLINK_CREDIT_TEXT = '<a href="[imago_url]" target="_blank">IMAGO</a>';
	const DEFAULT_IMAGE_AUTHOR_REGEX = '/Copyright:\\s*x([\\S]*?)x(?:\\s+|$)/i';
	
	static $gd, $imagick;
	static $kses_allowed_list = array(
		'input' => array(
			'type' => array(), 'name' => array(), 'id' => array(), 'class' => array(), 'disabled' => array(),
			'value' => array(), 'step' => array(), 'min' => array(), 'max' => array(), 'checked' => array()
		),
		'label' => array('for' => array()),
		'textarea' => array(
			'name' => array(), 'id' => array(), 'placeholder' => array(),
			'rows' => array(), 'cols' => array(), 'class' => array()
		),
		'select' => array('name' => array(), 'id' => array()),
		'option' => array('value' => array(), 'selected' => array(), 'disabled' => array())
	);

	static $hasInitialised = false; // Prevent after_setup_theme from firing multiple times.

    static function after_setup_theme() {
		
		if(self::$hasInitialised) return;
		
		if(is_admin()) {
			add_action('admin_menu', array(__CLASS__, 'add_settings_page'));
			add_action('admin_init', array(__CLASS__, 'register_settings'));
			add_action('admin_head', array(__CLASS__, 'render_css'));
			self::$gd = extension_loaded('gd') && function_exists('gd_info');
			self::$imagick = extension_loaded('imagick');
		}
		
		add_filter('option_' . self::AUTOMATIC_IMAGE_CREDIT_OPTIONS, array(__CLASS__,'option'), 1043, 2);
		$hasInitialised = true;
    }
	
	static function option($value, $option) {
		switch($option) {
			case self::AUTOMATIC_IMAGE_CREDIT_OPTIONS:
			case self::IMAGO_ATTACHMENT_DEBUG_META:
				if(empty($value)) return array();
				break;				
		}
		return $value;
	}

    static function add_settings_page() {
		add_submenu_page(
			'options-general.php',
			esc_html__('Imago Images Settings','imago-images'),
			esc_html__('Imago Images','imago-images'),
			'manage_options', self::PAGE_SLUG, array(__CLASS__, 'render')
		);
    }

    static function render() {
        if (!current_user_can('manage_options'))
            wp_die(esc_html__('You do not have sufficient permissions to access this page.','imago-images')); ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields(self::OPTION_GROUP); ?>
                <?php do_settings_sections(self::PAGE_SLUG); ?>
                <?php submit_button('Save Changes'); ?>
            </form>
        </div><?php
    }
	
	static function render_css() {
		$current_screen = get_current_screen();
		if($current_screen->base === 'settings_page_imago-images') {
			?><style>
			td > label[for] {
				display:block;
				padding:0.5em 0;
			}
			.monospace { font-family:monospace !important; }
			table.info-table th { text-align:right;padding:0.3em 1em; }
			</style><?php
		}
	}

    static function register_settings() {
		
		require_once IMAGO_TERRESQUALL_PLUGIN_DIR . 'inc/lib/Terresquall.WP_Form_Renderer.php';
		
        // Register all the settings.
        register_setting(
			self::OPTION_GROUP, self::API_USER_OPTION,
            array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field')
        );

		register_setting(
			self::OPTION_GROUP, self::API_PASSWORD_OPTION,
            array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field')
        );
		
		register_setting(
			self::OPTION_GROUP, self::AUTOMATIC_IMAGE_CREDIT_OPTIONS,
            array('type' => 'array', 'default' => array())
        );
		
		register_setting(
			self::OPTION_GROUP, self::IMAGO_BACKLINK_CREDIT_TEXT,
            array('type' => 'string', 'default' => self::DEFAULT_IMAGO_BACKLINK_CREDIT_TEXT)
        );
		
		register_setting(
			self::OPTION_GROUP, self::IMAGE_AUTHOR_REGEX_OPTION,
            array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => self::DEFAULT_IMAGE_AUTHOR_REGEX)
        );
		
		register_setting(
			self::OPTION_GROUP, self::IGNORE_BLOCKING_IMAGES_BEFORE_OPTION,
            array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => date('Y-m-d',time()))
        );
		
		register_setting(
			self::OPTION_GROUP, self::IMAGO_IMAGE_EDITOR,
            array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'disabled')
        );
		
		register_setting(
			self::OPTION_GROUP, self::IMAGO_IMAGE_MAX_RESOLUTION,
            array('type' => 'integer', 'sanitize_callback' => 'intval', 'default' => 2560)
        );
		
		register_setting(
			self::OPTION_GROUP, self::IMAGO_ATTACHMENT_DEBUG_META,
            array('type' => 'array', 'default' => array())
        );

		// Render the settings pages.
        add_settings_section(
            'imago_wordpress_credentials_section',
            esc_html__('Imago API Credentials','imago-images'),
            function() { 
				?><p><?php
				printf(
					esc_html__('To begin using the plugin, you will need to have a set of API keys from Imago. You can do so by %s.','imago-images'),
					'<a href="https://www.imago-images.com/contact" target="_blank">' . esc_html__('contacting them here','imago-images') . '</a>'
				); ?></p><?php
			},
            self::PAGE_SLUG
        );

        add_settings_field(
            self::API_USER_OPTION,
            esc_html__('API User','imago-images'),
            array(__CLASS__, 'render_api_user_field'),
            self::PAGE_SLUG,
            'imago_wordpress_credentials_section'
        );

        add_settings_field(
            self::API_PASSWORD_OPTION,
            esc_html__('API Password','imago-images'),
            array(__CLASS__, 'render_api_password_field'),
            self::PAGE_SLUG,
            'imago_wordpress_credentials_section'
        );
		
		add_settings_section(
            'imago_wordpress_credits_section',
            esc_html__('Image Credits','imago-images'),
            function() { 
				?><p><?php
				esc_html_e('Here are settings for enabling or disabling the crediting of Imago images.','imago-images');
				?></p><?php
			},
            self::PAGE_SLUG
        );
		
		add_settings_field(
            self::AUTOMATIC_IMAGE_CREDIT_OPTIONS,
            esc_html__('Automatic Image Crediting','imago-images'),
            array(__CLASS__, 'render_image_crediting_checkboxes'),
            self::PAGE_SLUG,
            'imago_wordpress_credits_section'
        );
		
		add_settings_field(
            self::IMAGO_BACKLINK_CREDIT_TEXT,
            esc_html__('Backlink Credit Text','imago-images'),
            array(__CLASS__, 'render_backlink_credit_text'),
            self::PAGE_SLUG,
            'imago_wordpress_credits_section'
        );
		
		add_settings_field(
            self::IMAGE_AUTHOR_REGEX_OPTION,
            esc_html__('Image Author Regex','imago-images'),
            array(__CLASS__, 'render_author_regex_field'),
            self::PAGE_SLUG,
            'imago_wordpress_credits_section'
        );
		
		add_settings_field(
            self::IGNORE_BLOCKING_IMAGES_BEFORE_OPTION,
            esc_html__('Ignore Blocking Images Before','imago-images'),
            array(__CLASS__, 'render_ignore_blocking_images_before'),
            self::PAGE_SLUG,
            'imago_wordpress_credits_section'
        );
		
		add_settings_section(
            'imago_wordpress_optimisation_section',
            esc_html__('Image Optimisation','imago-images'),
            function() {
				?><p><?php
				esc_html_e('Here are the options to automatically downsize downloaded images from Imago. This will help you save space on your server, but it requires either Imagick or GD to be installed and enabled on PHP.','imago-images');
				?></p><?php
			},
            self::PAGE_SLUG
        );
		
		add_settings_field(
            self::IMAGO_IMAGE_EDITOR,
            esc_html__('Image Editor','imago-images'),
            array(__CLASS__, 'render_image_editor_option'),
            self::PAGE_SLUG,
            'imago_wordpress_optimisation_section'
        );
		
		add_settings_field(
            self::IMAGO_IMAGE_MAX_RESOLUTION,
            esc_html__('Default Max Resolution','imago-images'),
            array(__CLASS__, 'render_max_resolution'),
            self::PAGE_SLUG,
            'imago_wordpress_optimisation_section'
        );
		
		add_settings_section(
            'imago_wordpress_debugging_section',
            esc_html__('Debugging','imago-images'),
            function() {
				?><p><?php
				esc_html_e("Here are the options for enabling debugging on your downloaded images. You normally won't need to turn these on.",'imago-images');
				?></p><?php
			},
            self::PAGE_SLUG
        );
		
		add_settings_field(
            self::IMAGO_ATTACHMENT_DEBUG_META,
            esc_html__('Show Debug Panel','imago-images'),
            array(__CLASS__, 'render_imago_attachment_debug'),
            self::PAGE_SLUG,
            'imago_wordpress_debugging_section'
        );
    }

    static function render_api_user_field() {
        echo wp_kses(WP_Form_Renderer::draw_form_element(
            'text',
            array(
                'name' => self::API_USER_OPTION,
                'id' => self::API_USER_OPTION,
                'value' => get_option(self::API_USER_OPTION)
            )
        ), self::$kses_allowed_list);
    }

    static function render_api_password_field() {
        echo wp_kses(WP_Form_Renderer::draw_form_element(
            'text',
            array(
                'name' => self::API_PASSWORD_OPTION,
                'id' => self::API_PASSWORD_OPTION,
                'value' => get_option(self::API_PASSWORD_OPTION)
            )
        ), self::$kses_allowed_list);
    }
	
	static function render_image_crediting_checkboxes() {
		echo wp_kses(WP_Form_Renderer::draw_form_element(
            'checkbox',
            array(
                'name' => self::AUTOMATIC_IMAGE_CREDIT_OPTIONS,
                'id' => self::AUTOMATIC_IMAGE_CREDIT_OPTIONS,
				'value' => get_option(self::AUTOMATIC_IMAGE_CREDIT_OPTIONS)
            ),
			array(
				'no-imago-credits' => esc_html__("Disable automatic Imago backlinking on Imago images",'imago-images'),
				'require-author-crediting' => esc_html__("Require author credit on image description on downloaded Imago images before use",'imago-images'),
				'auto-extract-author-credit' => esc_html__("Automatically extract image copyright into image description",'imago-images')
			)
        ), self::$kses_allowed_list);
	}
	
	static function render_backlink_credit_text() {
		echo wp_kses(WP_Form_Renderer::draw_form_element(
			'textarea',
            array(
                'name' => self::IMAGO_BACKLINK_CREDIT_TEXT,
                'id' => self::IMAGO_BACKLINK_CREDIT_TEXT,
                'value' => htmlentities(get_option(self::IMAGO_BACKLINK_CREDIT_TEXT, self::DEFAULT_IMAGO_BACKLINK_CREDIT_TEXT)),
                'placeholder' => htmlentities(self::DEFAULT_IMAGO_BACKLINK_CREDIT_TEXT),
				'rows' => '5', 'cols' => '80', 'class' => 'monospace'
            )
        ), self::$kses_allowed_list);
    }
	
	static function render_author_regex_field() {
		echo wp_kses(WP_Form_Renderer::draw_form_element(
			'text',
            array(
                'name' => self::IMAGE_AUTHOR_REGEX_OPTION,
                'id' => self::IMAGE_AUTHOR_REGEX_OPTION,
                'value' => get_option(self::IMAGE_AUTHOR_REGEX_OPTION, self::DEFAULT_IMAGE_AUTHOR_REGEX),
                'placeholder' => self::DEFAULT_IMAGE_AUTHOR_REGEX,
				'class' => 'widefat monospace'
            )
        ), self::$kses_allowed_list);
    }
	
	static function render_ignore_blocking_images_before() {
		echo wp_kses(WP_Form_Renderer::draw_form_element(
			'date',
            array(
                'name' => self::IGNORE_BLOCKING_IMAGES_BEFORE_OPTION,
                'id' => self::IGNORE_BLOCKING_IMAGES_BEFORE_OPTION,
                'value' => get_option(self::IGNORE_BLOCKING_IMAGES_BEFORE_OPTION, date('Y-m-d',time()))
            )
        ), self::$kses_allowed_list);
    }
	
	static function render_image_editor_option() {
		$options = array('disabled' => esc_html__('Disabled','imago-images'));
		
		// Is the GD extension enabled?
		if(self::$gd) $options['gd'] = esc_html__('GD (Graphics Draw)','imago-images');
		else
			$options['gd'] = array(esc_html__('GD (Graphics Draw) (unavailable)','imago-images'), 'disabled' => 'y');
		
		if(self::$imagick) $options['imagick'] = esc_html__('Imagick','imago-images');
		else
			$options['imagick'] = array(esc_html__('Imagick (unavailable)','imago-images'), 'disabled' => 'y');
			
		echo wp_kses(WP_Form_Renderer::draw_form_element(
			'select',
            array(
                'name' => self::IMAGO_IMAGE_EDITOR,
                'id' => self::IMAGO_IMAGE_EDITOR,
                'value' => get_option(self::IMAGO_IMAGE_EDITOR, 'disabled')
            ),
			$options
        ), self::$kses_allowed_list);
	}
	
	static function render_max_resolution() {
		echo wp_kses(WP_Form_Renderer::draw_form_element(
			'number',
            array(
                'name' => self::IMAGO_IMAGE_MAX_RESOLUTION,
                'id' => self::IMAGO_IMAGE_MAX_RESOLUTION,
                'value' => strval(get_option(self::IMAGO_IMAGE_MAX_RESOLUTION, 2560)),
				'step' => '1', 'max' => '2560', 'min' => '1'
            )
        ) . ' ' . esc_html__('pixels','imago-images'), self::$kses_allowed_list);
	}
	
	static function render_imago_attachment_debug() {
		echo wp_kses(WP_Form_Renderer::draw_form_element(
            'checkbox',
            array(
                'name' => self::IMAGO_ATTACHMENT_DEBUG_META,
                'id' => self::IMAGO_ATTACHMENT_DEBUG_META,
				'value' => get_option(self::IMAGO_ATTACHMENT_DEBUG_META)
            ),
			array(
				'show-debug-panel' => esc_html__('Show debug panel on Attachment page','imago-images')
			)
        ), self::$kses_allowed_list);
	}
}