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
                // Read by EBIconPicker in the controls bundle. Without it the picker
                // hides the Font Awesome library and offers only Dashicons.
                'fontAwesome' => 'true',
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
            // The editor styles (`infobox-editor-css`) are registered on `init`
            // in infobox.php, not here: see the note there.
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

    /**
     * Clear links that run script when followed.
     *
     * save() already drops these schemes, but content saved before 1.3.2
     * by a user with unfiltered_html can still hold one. Same rule as
     * sanitizeLinkUrl() in src/helpers.js: only javascript:, vbscript: and
     * data: are cleared, so app links such as whatsapp: keep working.
     *
     * Needs the HTML API (WordPress 6.2+); on older versions the markup is
     * returned as is.
     *
     * @param string $content Rendered block markup.
     * @return string
     */
    public static function sanitize_links($content)
    {
        if (!is_string($content) || false === stripos($content, 'href') || !class_exists('WP_HTML_Tag_Processor')) {
            return $content;
        }

        $tags = new WP_HTML_Tag_Processor($content);
        while ($tags->next_tag('a')) {
            $href = $tags->get_attribute('href');
            if (is_string($href) && self::is_script_link($href)) {
                $tags->set_attribute('href', '');
            }
        }

        return $tags->get_updated_html();
    }

    /**
     * Whether a link uses a scheme that runs script.
     *
     * @param string $link
     * @return bool
     */
    private static function is_script_link($link)
    {
        // Browsers ignore control characters and spaces inside a scheme.
        $normalised = preg_replace('/[\x00-\x20\x7f]/', '', $link);
        return (bool) preg_match('/^(javascript|vbscript|data):/i', $normalised);
    }
}
Infobox_Helper::register();