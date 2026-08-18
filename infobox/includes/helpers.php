<?php

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

class Infobox_Helper
{

    private static $instance;

    /**
     * Registers the plugin.
     */
    public static function register()
    {
        if (null === self::$instance) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    /**
     * The Constructor.
     */
    public function __construct()
    {
        add_action('admin_enqueue_scripts', array($this, 'enqueues'));
    }

    /**
     * Load fonts.
     *
     * @access public
     */
    public function enqueues($hook)
    {
        /**
         * Only for Admin Add/Edit Pages
         */
        $query_string = isset($_SERVER['QUERY_STRING']) ? sanitize_text_field(wp_unslash($_SERVER['QUERY_STRING'])) : '';

        // `str_contains()` is PHP 8.0+, above this plugin's 7.4 floor; `strpos()` covers 7.4+.
        if ($hook == 'post-new.php' || $hook == 'post.php' || $hook == 'site-editor.php' || ($hook == 'themes.php' && !empty($query_string) && strpos($query_string, 'gutenberg-edit-site') !== false)) {

            $modules_asset_path = INFOBOX_ADMIN_PATH . '/dist/modules.asset.php';
            if (!file_exists($modules_asset_path)) {
                return;
            }

            $controls_dependencies = require $modules_asset_path;
            if (!is_array($controls_dependencies) || !isset($controls_dependencies['dependencies'], $controls_dependencies['version'])) {
                return;
            }

            wp_register_script(
                "infobox-controls-util",
                INFOBOX_ADMIN_URL . 'dist/modules.js',
                (array) $controls_dependencies['dependencies'],
                $controls_dependencies['version'],
                true
            );

            wp_localize_script('infobox-controls-util', 'EssentialBlocksLocalize', array(
                'eb_wp_version' => (float) get_bloginfo('version'),
                'rest_rootURL' => get_rest_url(),
            ));

            if ($hook == 'post-new.php' || $hook == 'post.php') {
                wp_localize_script('infobox-controls-util', 'eb_conditional_localize', array(
                    'editor_type' => 'edit-post'
                ));
            } else if ($hook == 'site-editor.php') {
                wp_localize_script('infobox-controls-util', 'eb_conditional_localize', array(
                    'editor_type' => 'edit-site'
                ));
            }

			wp_register_style(
				'essential-blocks-iconpicker-css',
				INFOBOX_ADMIN_URL . 'dist/style-modules.css',
				[],
				INFOBOX_VERSION,
				'all'
			);

            wp_register_style(
                'infobox-editor-css',
                INFOBOX_ADMIN_URL . 'dist/modules.css',
                array(
                    'create-block-infobox-block-css',
					'essential-blocks-iconpicker-css'
                ),
                $controls_dependencies['version'],
                'all'
            );
        }
    }
    /**
     * Resolve what to hand `register_block_type()`.
     *
     * The path form needs WP 5.7+, so this used to fall back to the block-name
     * form on older installs. The plugin now requires WP 6.0, which makes that
     * branch unreachable — the path form is always used.
     *
     * @param string $blockname Legacy block-name fallback. Unused; the parameter
     *                          is kept so existing call sites keep working.
     * @param string $blockPath Absolute path to the directory holding block.json.
     * @return string
     */
    public static function get_block_register_path($blockname, $blockPath)
    {
        return $blockPath;
    }
}
Infobox_Helper::register();