<?php
/**
 * Plugin Name: Prevent Browser Caching
 * Description: Updates the version of all CSS and JS files.
 * Version: 2.0
 * Author: Kostya Tereshchuk
 * Author URI: https://wordpress.org/support/users/kostyatereshchuk/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: prevent-browser-caching
 */


// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;


if ( ! class_exists('Prevent_Browser_Caching') ) {

    /**
     * @class Prevent_Browser_Caching
     */
    class Prevent_Browser_Caching
    {

        /**
         * Single instance of the class.
         *
         * @var Prevent_Browser_Caching
         */
        protected static $_instance = null;

        /**
         * Value of prevent_browser_caching_options option.
         *
         * @var array
         */
        public $options = array();

        /** Value of prevent_browser_caching_clear_cache_time option.
         *
         * @var string
         */
        public $clear_cache_time = '';

        /** Url parameter "time" which will be added to styles and scripts.
         *
         * @var string
         */
        public $time_query_arg = '';


        /**
         * Prevent_Browser_Caching instance.
         *
         * @static
         * @return Prevent_Browser_Caching - Main instance
         */
        public static function instance()
        {
            if (is_null(self::$_instance)) {
                self::$_instance = new self();
            }
            return self::$_instance;
        }

        /**
         * Prevent_Browser_Caching Constructor.
         */
        public function __construct()
        {
            $options = $this->get_options();

            $clear_cache_automatically = $options['clear_cache_automatically'];

            $time = '';
            if ( $clear_cache_automatically == 'every_time' ) {
                $time = time();
            } elseif ( $clear_cache_automatically == 'every_period' ) {
                $update_time = true;

                if ( isset( $_COOKIE['prevent_browser_caching_time'] ) ) {
                    $time = intval( $_COOKIE['prevent_browser_caching_time'] );
                    $time = max( $time, $this->get_clear_cache_time() );
                    $current_time = time();
                    $cached_minutes = round( ( $current_time - $time ) / 60 );
                    $options['clear_cache_automatically_minutes'];

                    if ( $cached_minutes > $options['clear_cache_automatically_minutes'] ) {
                        $update_time = true;
                    } else {
                        $update_time = false;
                    }
                }

                if ( $update_time ) {
                    $time = time();
                    $expiration_time = $time + 60 * $options['clear_cache_automatically_minutes'];
                    setcookie( 'prevent_browser_caching_time', $time, $expiration_time, '/' );
                }
            } elseif ( $clear_cache_automatically == 'never' ) {
                $time = $this->get_clear_cache_time();
            }

            $this->time_query_arg = $time;

            add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array( $this, 'plugin_actions' ), 10, 1 );

            add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

            add_filter( 'style_loader_src', array( $this, 'add_query_arg' ), 10000 );
            add_filter( 'script_loader_src', array( $this, 'add_query_arg' ), 10000 );

            if ( is_admin() ) {
                include_once 'includes/admin/class-prevent-browser-caching-admin-settings.php';
            }
        }

        /**
         * Add settings to plugin links.
         * @param $actions
         * @return mixed
         */
        public function plugin_actions($actions) {
            array_unshift( $actions, "<a href=\"" . menu_page_url( 'prevent-browser-caching', false ) . "\">" . esc_html__( "Settings" ) . "</a>" );
            return $actions;
        }

        /**
         * Set languages directory.
         */
        public function load_textdomain() {
            load_plugin_textdomain( 'prevent-browser-caching', false, dirname(plugin_basename(__FILE__)) . '/lang/' );
        }

        /**
         * Sanitize and return the options in the right form.
         * @param $options
         * @return array
         */
        public function filter_options( $options ) {
            if ( isset( $options['clear_cache_automatically'] ) ) {
                $clear_cache_automatically = esc_html( sanitize_text_field( $options['clear_cache_automatically'] ) );

                if ( ! in_array( $clear_cache_automatically, array( 'every_time', 'every_period', 'never' ) ) ) {
                    $clear_cache_automatically = 'every_time';
                }
            } else {
                $clear_cache_automatically = 'every_time';
            }

            if ( isset( $options['clear_cache_automatically_minutes'] ) ) {
                $clear_cache_automatically_minutes = intval( $options['clear_cache_automatically_minutes'] );
                $clear_cache_automatically_minutes = min( $clear_cache_automatically_minutes, 99999 );
                $clear_cache_automatically_minutes = max( $clear_cache_automatically_minutes, 1 );
            } else {
                $clear_cache_automatically_minutes = 10;
            }

            return array(
                'clear_cache_automatically' => $clear_cache_automatically,
                'clear_cache_automatically_minutes' => $clear_cache_automatically_minutes
            );
        }

        /**
         * Get value of prevent_browser_caching_options option.
         */
        public function get_options() {
            if ( empty( $this->options ) ) {
                $this->options = $this->filter_options( get_option('prevent_browser_caching_options') );
            }

            return $this->options;
        }

        /**
         * Get values of prevent_browser_caching_clear_cache_time option.
         */
        public function get_clear_cache_time() {
            if ( ! $this->clear_cache_time ) {
                $this->clear_cache_time = intval( get_option('prevent_browser_caching_clear_cache_time') );
            }

            return $this->clear_cache_time;
        }

        /**
         * Add query parameters to CSS and JS files.
         * @param $src
         * @return string
         */
        public function add_query_arg( $src ) {
            if ( $time = $this->time_query_arg ) {
                $src = add_query_arg( 'time', $time, $src );
            }

            return $src;
        }

    }

    Prevent_Browser_Caching::instance();

}