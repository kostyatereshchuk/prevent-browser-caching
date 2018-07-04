<?php
/**
 * Plugin Name: Prevent Browser Caching
 * Description: Update the version of all CSS and JS files. Show the latest changes on the site without asking the client to clear browser cache.
 * Version: 2.2
 * Author: Kostya Tereshchuk
 * Author URI: https://wordpress.org/support/users/kostyatereshchuk/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: prevent-browser-caching
 * Domain Path: /lang/
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! function_exists( 'prevent_browser_caching' ) ) {
    function prevent_browser_caching( $args = array() ) {
        if ( ! class_exists('Prevent_Browser_Caching_Function') ) {
            $default_args = array(
                'assets_version' => time()
            );

            $assets_version = isset( $args['assets_version'] ) ? $args['assets_version'] : $default_args['assets_version'];

            class Prevent_Browser_Caching_Function {
                public $assets_version = '';

                /**
                 * Prevent_Browser_Caching_Function constructor.
                 * @param $assets_version
                 */
                public function __construct( $assets_version ) {
                    $this->assets_version = $assets_version;

                    add_filter( 'style_loader_src', array( $this, 'add_query_arg' ), 10000 );
                    add_filter( 'script_loader_src', array( $this, 'add_query_arg' ), 10000 );
                }

                /**
                 * Adds query parameters to CSS and JS files.
                 * @param $src
                 * @return string
                 */
                public function add_query_arg( $src )
                {
                    if ( $this->assets_version ) {
                        $src = add_query_arg( 'ver', $this->assets_version, $src );
                    } else {
                        $src = remove_query_arg( 'ver', $src );
                    }

                    return $src;
                }

            }

            new Prevent_Browser_Caching_Function( $assets_version );
        }
    }
}

function maybe_load_class_prevent_browser_caching() {
    if ( ! class_exists('Prevent_Browser_Caching') && ! class_exists('Prevent_Browser_Caching_Function') ) {

        include_once 'includes/class-prevent-browser-caching.php';

    }
}
add_action( 'after_setup_theme', 'maybe_load_class_prevent_browser_caching' );