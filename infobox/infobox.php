<?php

/**
 * Plugin Name:     Infobox
 * Plugin URI:         https://essential-blocks.com
 * Description:     Highlight Your Key Features & Hold Audience Attention with Info Box Block.
 * Version:         1.3.2
 * Author:          WPDeveloper
 * Author URI:         https://wpdeveloper.net
 * Requires at least: 6.0
 * Tested up to:    7.0
 * Requires PHP:    7.4
 * License:         GPL-3.0-or-later
 * License URI:     https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:     infobox
 *
 * @package         infobox
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers all block assets so that they can be enqueued through the block editor
 * in the corresponding context.
 *
 * @see https://developer.wordpress.org/block-editor/tutorials/block-tutorial/applying-styles-with-stylesheets/
 */

define( 'INFOBOX_VERSION', "1.3.2" );
define( 'INFOBOX_ADMIN_URL', plugin_dir_url( __FILE__ ) );
define( 'INFOBOX_ADMIN_PATH', dirname( __FILE__ ) );

require_once __DIR__ . '/includes/font-loader.php';
require_once __DIR__ . '/includes/post-meta.php';
require_once __DIR__ . '/includes/helpers.php';

/**
 * `lib/style-handler` ships as a git submodule. Guard the include so an
 * uninitialised submodule degrades gracefully instead of fataling on load.
 */
if ( file_exists( __DIR__ . '/lib/style-handler/style-handler.php' ) ) {
    require_once __DIR__ . '/lib/style-handler/style-handler.php';
}

if ( ! function_exists( 'create_block_infobox_block_init' ) ) :
function create_block_infobox_block_init() {

    $script_asset_path = INFOBOX_ADMIN_PATH . "/dist/index.asset.php";
    if ( ! file_exists( $script_asset_path ) ) {
        // Build artefacts are missing (`npm run build` has not been run).
        // Bail out instead of throwing: `Error` does not exist on PHP < 7 and
        // an uncaught throw on `init` takes the whole site down.
        return;
    }
    $script_asset = require $script_asset_path;
    if ( ! is_array( $script_asset ) || ! isset( $script_asset['dependencies'], $script_asset['version'] ) ) {
        return;
    }
    $all_dependencies = array_merge( (array) $script_asset['dependencies'], [
        'wp-blocks',
        'wp-i18n',
        'wp-element',
        'wp-block-editor',
        'infobox-controls-util',
        'essential-blocks-eb-animation'
    ] );

    $index_js = INFOBOX_ADMIN_URL . 'dist/index.js';
    wp_register_script(
        'create-block-infobox-block-editor',
        $index_js,
        $all_dependencies,
        $script_asset['version'],
        true
    );

    $animate_css = INFOBOX_ADMIN_URL . 'assets/css/animate.min.css';
    wp_register_style(
        'essential-blocks-animation',
        $animate_css,
        [],
        INFOBOX_VERSION
    );

    $style_css = INFOBOX_ADMIN_URL . 'dist/style.css';
    // Version dist/style.css by its own modification time. A fixed plugin
    // version keeps serving a cached stylesheet after a rebuild, and the
    // dist/index.asset.php hash does not change when only the SCSS changes.
    $style_css_path    = INFOBOX_ADMIN_PATH . '/dist/style.css';
    $style_css_version = file_exists( $style_css_path ) ? (string) filemtime( $style_css_path ) : INFOBOX_VERSION;
    wp_register_style(
        'create-block-infobox-block-css',
        $style_css,
        [
            'essential-blocks-hover-css',
            'essential-blocks-animation',
            'fontawesome-frontend-css'
        ],
        $style_css_version,
        "all"
    );

    $load_animation_js = INFOBOX_ADMIN_URL . 'assets/js/eb-animation-load.js';
    wp_register_script(
        'essential-blocks-eb-animation',
        $load_animation_js,
        [],
        INFOBOX_VERSION,
        true
    );

    wp_register_style(
        'fontpicker-default-theme',
        INFOBOX_ADMIN_URL . 'assets/css/fonticonpicker.base-theme.react.css',
        [],
        INFOBOX_VERSION,
        "all"
    );

    wp_register_style(
        'fontpicker-matetial-theme',
        INFOBOX_ADMIN_URL . 'assets/css/fonticonpicker.material-theme.react.css',
        [],
        INFOBOX_VERSION,
        "all"
    );

    wp_register_style(
        'fontawesome-frontend-css',
        INFOBOX_ADMIN_URL . 'assets/css/font-awesome5.css',
        [],
        INFOBOX_VERSION,
        "all"
    );

    wp_register_style(
        'essential-blocks-hover-css',
        INFOBOX_ADMIN_URL . 'assets/css/hover-min.css',
        [],
        INFOBOX_VERSION,
        "all"
    );

    /**
     * Editor styles. These must be registered here on `init`, not on
     * `admin_enqueue_scripts`: the iframed editor canvas collects block
     * `editor_style` handles before `admin_enqueue_scripts` fires, so a handle
     * registered there is silently missing from the canvas (no Font Awesome,
     * no Dashicons, no block CSS such as the media `display: flex`).
     */
    wp_register_style(
        'essential-blocks-iconpicker-css',
        INFOBOX_ADMIN_URL . 'dist/style-modules.css',
        [],
        INFOBOX_VERSION,
        'all'
    );

    wp_register_style(
        'infobox-editor-panel-css',
        INFOBOX_ADMIN_URL . 'dist/editor.css',
        [],
        INFOBOX_VERSION,
        'all'
    );

    wp_register_style(
        'infobox-editor-css',
        INFOBOX_ADMIN_URL . 'dist/modules.css',
        [
            'create-block-infobox-block-css',
            'infobox-editor-panel-css',
            'essential-blocks-iconpicker-css',
            'dashicons',
        ],
        INFOBOX_VERSION,
        'all'
    );

    $eb_has_block = WP_Block_Type_Registry::get_instance()->is_registered( 'essential-blocks/infobox' );
    $block_args   = [
        'editor_script'   => 'create-block-infobox-block-editor',
        'editor_style'    => 'infobox-editor-css',
        'render_callback' => function ( $attributes, $content ) {
            if ( ! is_admin() ) {
                wp_enqueue_style( 'create-block-infobox-block-css' );
                wp_enqueue_script( 'essential-blocks-eb-animation' );

                // Dashicons is not loaded on the front end by default.
                $icon = isset( $attributes['infoboxIcon'] ) ? (string) $attributes['infoboxIcon'] : '';
                if ( false !== strpos( $icon, 'dashicons' ) ) {
                    wp_enqueue_style( 'dashicons' );
                }
            }
            return $content;
        }
    ];

    // Essential Blocks has its own version of this block under another name. Posts saved
    // with this one still need its render callback and front-end assets, so keep it
    // registered, but leave out the editor bundle, which can't run beside Essential Blocks.
    if ( $eb_has_block ) {
        unset( $block_args['editor_script'], $block_args['editor_style'] );
    }

    register_block_type(
        Infobox_Helper::get_block_register_path( "infobox/infobox", INFOBOX_ADMIN_PATH ),
        $block_args
    );
}
endif;

add_action( 'init', 'create_block_infobox_block_init', 99 );

/**
 * Block icons are filled #6C3BFF, which almost disappears on the highlighted row of
 * the "/" inserter, a selected List View row and the drag chip. Recolour them there,
 * as Essential Blocks does. The handle is shared, so the stylesheet prints once when
 * several of these plugins are active.
 */
function infobox_block_icon_selected_style() {
    wp_enqueue_style(
        'eb-block-icon-selected',
        plugins_url( 'assets/css/block-icon-selected.css', __FILE__ ),
        [],
        INFOBOX_VERSION
    );
}
add_action( 'enqueue_block_editor_assets', 'infobox_block_icon_selected_style' );

/**
 * Let Essential Blocks' animation assets win on the front end when it is active.
 *
 * Both plugins register these handles and the first registration wins. This
 * plugin's loader doesn't set `window.ebAnimationLoaded`, so Essential Blocks'
 * failsafe would reveal every animated block without playing it. Essential Blocks
 * registers its own at priority 20 and handles the same `.eb___animated` classes.
 */
function infobox_use_essential_blocks_animation() {
    if ( defined( 'ESSENTIAL_BLOCKS_VERSION' ) ) {
        wp_deregister_script( 'essential-blocks-eb-animation' );
        wp_deregister_style( 'essential-blocks-animation' );
    }
}
add_action( 'wp_enqueue_scripts', 'infobox_use_essential_blocks_animation', 19 );
