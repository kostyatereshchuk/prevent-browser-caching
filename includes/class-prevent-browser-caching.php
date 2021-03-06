<?php

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

    /**
     * Value of prevent_browser_caching_clear_cache_time option.
     *
     * @var string
     */
    public $clear_cache_time = '';

    /**
     * Show "Update CSS/JS" button on the toolbar.
     *
     * @var bool
     */
    public $show_on_toolbar = false;

    /**
     * Url parameter "time" which will be added to styles and scripts.
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
        $this->init_params();

        add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array( $this, 'plugin_actions' ), 10, 1 );
        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

        if ( $this->show_on_toolbar ) {
            add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu' ), 10000 );
            add_action( 'template_redirect', array( $this, 'update_css_js' ), 10000 );
        }

        if ( is_admin() ) {
            include_once 'admin/class-prevent-browser-caching-admin-settings.php';
        } else {
            add_filter( 'style_loader_src', array( $this, 'add_query_arg' ), 10000 );
            add_filter( 'script_loader_src', array( $this, 'add_query_arg' ), 10000 );
        }
    }

    /**
     * Initialize Prevent_Browser_Caching parameters.
     */
    public function init_params()
    {
        $options = $this->get_options();

        $clear_cache_automatically = $options['clear_cache_automatically'];

        $time = '';
        if ( $clear_cache_automatically == 'every_time' ) {
            $time = $this->get_time_code();
        } elseif ( $clear_cache_automatically == 'every_period' ) {
            $update_time = true;

            if ( isset( $_COOKIE['prevent_browser_caching_time'] ) ) {
                $time = intval( $_COOKIE['prevent_browser_caching_time'] );
                $time = max( $time, $this->get_clear_cache_time() );
                $current_time = $this->get_time_code();
                $cached_minutes = round( ( $current_time - $time ) / 60 );
                $options['clear_cache_automatically_minutes'];

                if ( $cached_minutes > $options['clear_cache_automatically_minutes'] ) {
                    $update_time = true;
                } else {
                    $update_time = false;
                }
            }

            if ( $update_time ) {
                $time = $this->get_time_code();
                $expiration_time = $time + 60 * $options['clear_cache_automatically_minutes'];
                setcookie( 'prevent_browser_caching_time', $time, $expiration_time, '/' );
            }
        } elseif ( $clear_cache_automatically == 'never' ) {
            $time = $this->get_clear_cache_time();
        }

        $this->time_query_arg = $time;

        $this->show_on_toolbar = $options['show_on_toolbar'];
    }

    /**
     * Add settings to plugin links.
     * @param $actions
     * @return mixed
     */
    public function plugin_actions($actions)
    {
        array_unshift( $actions, "<a href=\"" . menu_page_url( 'prevent-browser-caching', false ) . "\">" . esc_html__( "Settings" ) . "</a>" );
        return $actions;
    }

    /**
     * Set languages directory.
     */
    public function load_textdomain()
    {
        load_plugin_textdomain( 'prevent-browser-caching', false, dirname(plugin_basename(__FILE__)) . '/lang/' );
    }

    /**
     * Sanitize and return the options in the right form.
     * @param $options
     * @return array
     */
    public function filter_options( $options )
    {
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

        if ( isset( $options['show_on_toolbar'] ) ) {
            $show_on_toolbar = $options['show_on_toolbar'] ? true : false;
        } else {
            $show_on_toolbar = false;
        }

        return array(
            'clear_cache_automatically' => $clear_cache_automatically,
            'clear_cache_automatically_minutes' => $clear_cache_automatically_minutes,
            'show_on_toolbar' => $show_on_toolbar
        );
    }

    /**
     * Get value of prevent_browser_caching_options option.
     */
    public function get_options()
    {
        if ( empty( $this->options ) ) {
            $this->options = $this->filter_options( get_option('prevent_browser_caching_options') );
        }

        return $this->options;
    }

    /**
     * Get values of prevent_browser_caching_clear_cache_time option.
     */
    public function get_clear_cache_time()
    {
        if ( ! $this->clear_cache_time ) {
            $this->clear_cache_time = intval( get_option('prevent_browser_caching_clear_cache_time') );
        }

        return $this->clear_cache_time;
    }

    /**
     * Adds query parameters to CSS and JS files.
     * @param $src
     * @return string
     */
    public function add_query_arg( $src )
    {
        if ( $time = $this->time_query_arg ) {
            $url_parts = parse_url( $src );

            $query = array();

            if ( isset( $url_parts['query'] ) ) {
                parse_str( $url_parts['query'], $query );
            }

            if ( isset( $query['ver'] ) ) {
                $ver = $query['ver'] . '.' . $time;
            } else {
                $ver = $time;
            }

            $src = add_query_arg( 'ver', $ver, $src );
        }

        //$src = str_replace( site_url(), '', $src );

        return $src;
    }

    /**
     * Get the current page url.
     *
     * @return string
     */
    public function get_current_url() {
        $is_https = strpos( site_url(), 'https://' ) === 0;

        return ( $is_https ? 'https' : 'http' ) . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }

    /**
     * Adds item(s) to the toolbar.
     *
     * @param WP_Admin_Bar $wp_admin_bar
     */
    public function admin_bar_menu( $wp_admin_bar ) {
        $current_url = $this->get_current_url();

        $update_url = add_query_arg( 'pbc_update_css_js', wp_create_nonce( 'pbc_update_css_js' ), $current_url );

        $wp_admin_bar->add_menu(
            array(
                'id' => 'pbc_update_css_js',
                'title' => 'Update CSS/JS',
                'parent' => false,
                'href' => $update_url,
                'group' => false,
                'meta' => array(),
            )
        );
    }

    /**
     * Update CSS and JS files using toolbar button.
     */
    public function update_css_js() {
        if ( ! isset( $_GET['pbc_update_css_js'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( $_GET['pbc_update_css_js'], 'pbc_update_css_js') ) {
            return;
        }

        update_option( 'prevent_browser_caching_clear_cache_time', $this->get_time_code() );

        $current_url = $this->get_current_url();
        $redirect_url = remove_query_arg( 'pbc_update_css_js', $current_url );

        wp_redirect( $redirect_url );
        exit;
    }

    /**
     * Get the current time number.
     *
     * @return int
     */
    public function get_time_code() {

        return time();
    }

}

Prevent_Browser_Caching::instance();