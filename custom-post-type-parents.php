<?php
/**
 * Custom Post Type Parents
 *
 * @package   Custom Post Type Parents
 * @author    MIGHTYminnow & Mickey Kay <mickey@mickeykaycreative.com>
 * @license   GPL-2.0+
 * @link      http://wordpress.org/plugins/custom-post-type-parents
 * @copyright 2015 MIGHTYminnow & Mickey Kay
 *
 * @wordpress-plugin
 * Plugin Name:       Custom Post Type Parents
 * Plugin URI:        http://wordpress.org/plugins/custom-post-type-parents
 * Description:       Set a "parent page" for custom post types that is indicated in menus and lists of pages.
 * Version:           1.1.2
 * Author:            MIGHTYminnow & Mickey Kay
 * Author URI:        mickey@mickeykaycreative.com
 * License:           GPLv2+
 * Text Domain:       custom-post-type-parents
 * Domain Path:       /languages
 */

/**
 * Includes
 */

add_action( 'plugins_loaded', 'cptp_start' );
/**
 * Initialize the Better Font Awesome plugin.
 *
 * Start up Better Font Awesome early on the plugins_loaded hook, priority 5, in
 * order to load it before any other plugins that might also use the Better Font
 * Awesome Library.
 *
 * @since  0.9.5
 */
function cptp_start() {
    global $custom_post_type_parents;
    $custom_post_type_parents = Custom_Post_Type_Parents::get_instance();
}

/**
 * Better Font Awesome plugin class
 *
 * @since  0.9.0
 */
class Custom_Post_Type_Parents {

    /**
     * Plugin slug.
     *
     * @since  0.9.0
     *
     * @var    string
     */
    const SLUG = 'custom-post-type-parents';

    /**
     * Plugin display name.
     *
     * @since  0.9.0
     *
     * @var    string
     */
    private $plugin_display_name;

    /**
     * Plugin option name.
     *
     * @since  0.9.0
     *
     * @var    string
     */
    protected $option_name = 'custom_post_type_parents_options';

    /**
     * Plugin options.
     *
     * @since  0.9.0
     *
     * @var    string
     */
    protected $options;

    /**
     * Args for fetching post types.
     *
     * @var  array
     */
    protected $post_type_args = array(
        'public'   => true,
    );

    /**
     * Post types affected by plugin.
     *
     * @since  1.0.1
     *
     * @var    array
     */
    protected $post_types = array();

    /**
     * Instance of this class.
     *
     * @since  0.9.0
     *
     * @var    Better_Font_Awesome_Plugin
     */
    protected static $instance = null;

    /**
     * Returns the instance of this class, and initializes the instance if it
     * doesn't already exist.
     *
     * @return  Better_Font_Awesome  The BFA object.
     */
     public static function get_instance() {

        if ( null == self::$instance ) {
            self::$instance = new self;
        }

        return self::$instance;

    }

    /**
     * Better Font Awesome Plugin constructor.
     *
     * @since  0.9.0
     */
    function __construct() {

        // Perform plugin initialization actions.
        $this->initialize();

        // Include required files.
        $this->includes();

        // Load the plugin text domain.
        add_action( 'init', array( $this, 'load_text_domain' ) );

        // Deregister default SSN widget and register our own.
        add_action( 'widgets_init', array( $this, 'do_widget_registration' ) );

        // Add necessary menu classes.
        add_filter( 'nav_menu_css_class', array( $this, 'add_menu_classes' ), 10, 2 );
        add_filter( 'page_css_class', array( $this, 'add_menu_classes' ), 10, 2 );

        // Simple Section Navigation filters
        add_filter( 'simple_section_nav_filter_post', array( $this, 'ssn_filter_post' ) );

        // Set up the admin settings page.
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'add_settings' ) );

    }

    /**
     * Do necessary initialization actions.
     *
     * @since  1.0.0
     */
    private function initialize() {

        // Set display name
        $this->plugin_display_name = __( 'Custom Post Type Parents', 'custom-post-type-parents' );

        // Get options
        $this->options = get_option( $this->option_name );

    }

    /**
     * Include required files.
     *
     * @since  1.0.0
     */
    private function includes() {

    	// Custom Simple Section Navigation widget override
        require_once( plugin_dir_path( __FILE__ ) . 'includes/custom-simple-section-nav.php' );

    }

    /**
     * Load plugin text domain.
     *
     * @since  1.0.0
     */
    function load_text_domain() {
        $locale = apply_filters( 'plugin_locale', get_locale(), 'custom-post-type-parents' );
		load_textdomain( 'custom-post-type-parents', WP_LANG_DIR . '/custom-post-type-parents/custom-post-type-parents-' . $locale . '.mo' );
		load_plugin_textdomain( 'custom-post-type-parents', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }

    /**
     * Unregister standard SSN widget, and register custom SSN widget.
     *
     * @since  1.0.0
     */
    function do_widget_registration() {
    	unregister_widget( 'SimpleSectionNav' );
    	register_widget( 'CustomSimpleSectionNav' );
    }

    /**
     * Add menu ancestor classes
     *
     * @since  1.0.0
     *
     * @param  array    $classes  Current menu item classes array.
     * @param  WP_Post  $item     Menu item object.
     */
	function add_menu_classes( $classes, $item ){

        // Only modify nav classes if post type has assigned parent
        if ( ! $this->has_assigned_parent() ) {
            return $classes;
        }

        // Get parent ID
        $current_post_type = get_post_type();
        $custom_post_type_parent_id = $this->options[ 'parent-' . $current_post_type ];

	    // Get all parent ancestor ID's
	    $ancestor_ids = $this->get_ancestor_ids( $current_post_type );

        // Current post ID is represented differently for custom menu vs auto menu
        $menu_item_id = isset( $item->object_id ) ? $item->object_id : $item->ID;

        // Add class for parent post
        if ( $menu_item_id == $custom_post_type_parent_id ) {
            $classes[] = 'custom-custom-post-type-parent';
            $classes[] = 'current-menu-parent';
            $classes[] = 'current-' . $current_post_type . '-parent';

            // Backwards compatibility
            $classes[] = 'current_page_parent';
        }

        // Add class for ancestor posts
        if ( in_array ( $menu_item_id, $ancestor_ids ) ) {
            $classes[] = 'custom-custom-post-type-ancestor';
            $classes[] = 'current-menu-ancestor';
            $classes[] = 'current-' . $current_post_type . '-ancestor';

            // Backwards compatibility
            $classes[] = 'current_page_ancestor';
        }

	    return $classes;
	}

    /**
     * Check if custom post type has assigned parent.
     *
     * @since   1.0.0
     *
     * @param   string  $post_type  Post type slug.
     *
     * @return  bool               True if the post type has an assigned parent.
     */
    function has_assigned_parent( $post_type = '' ) {

    	// Get current post type if needed
    	$post_type = $post_type ? $post_type : get_post_type();

        if (
            ! isset( $this->options[ 'parent-' . $post_type ] ) ||
            'none' == $this->options[ 'parent-' . $post_type ]
        ) {
            return false;
        }

        return true;
    }

    /**
     * Get array of custom post types ancestor ID's including parent.
     *
     * @since   1.0.0
     *
     * @param   string  $post_type  Post type to check for ancestors.
     *
     * @return  array              Array of ancestor ID's.
     */
    function get_ancestor_ids( $post_type = '' ) {

    	// Get current post type if needed
    	$post_type = $post_type ? $post_type : get_post_type();

    	$custom_post_type_parent_id = $this->options[ 'parent-' . $post_type ];
	    $ancestor_ids = get_ancestors( $custom_post_type_parent_id, 'page' );

	    // Add parent ID to beginning of array
	    array_unshift( $ancestor_ids, $custom_post_type_parent_id );

	    return $ancestor_ids;
    }

    /**
     * Filter the post sent to SSN to be the Custom Post Type Parent.
     *
     * @since  1.0.0
     *
     * @return  WP_Post  The Custom Post Type parent post.
     */
    function ssn_filter_post( $post ) {
    	if ( $this->has_assigned_parent() ) {
    		$post = get_post( $this->options[ 'parent-' . get_post_type() ] );
    	}

    	return $post;
    }

    /**
     * Create the plugin settings page.
     */
    function add_settings_page() {

        add_options_page(
            $this->plugin_display_name,
            $this->plugin_display_name,
            'manage_options',
            self::SLUG,
            array( $this, 'create_admin_page' )
        );

    }

    /**
     * Output the plugin settings page contents.
     *
     * @since  1.0.0
     */
    public function create_admin_page() {
    ?>
        <div class="wrap">
            <?php screen_icon(); ?>
            <h2><?php echo $this->plugin_display_name; ?></h2>
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'custom_post_type_parent_option_group' );
                do_settings_sections( self::SLUG );
                submit_button();
            ?>
            </form>
        </div>
    <?php
    }

    /**
     * Populate the settings page with specific settings.
     *
     * @since  1.0.0
     */
    function add_settings() {

        register_setting(
            'custom_post_type_parent_option_group', // Option group
            $this->option_name, // Option name
            array( $this, 'validate_settings' ) // Validation callback
        );

        add_settings_section(
            'custom_post_type_parents_main', // ID
            null, // Title
            array( $this, 'add_main_section_description' ), // Callback
            self::SLUG // Page
        );

        $this->post_types = get_post_types( $this->post_type_args );

        // Add section for every custom post type.
        foreach ( $this->post_types as $post_type ) {

        	$post_type_object = get_post_type_object( $post_type );

	        add_settings_field(
	            'parent-' . $post_type, // ID
	            $post_type_object->labels->name, // Title
	            array( $this, 'pages_dropdown_callback'), // Callback
	            self::SLUG, // Page
	            'custom_post_type_parents_main', // Section
	            array( // Args
	            	'post_type_object' => $post_type_object,
	            )
	        );

        }

    }

    /**
     * Output the description for the main settings section.
     *
     * @since  1.0.0
     */
    public function add_main_section_description() {
        ?>
        <p><?php _e( 'Select a parent for each custom post type.', 'custom-post-type-parents' ); ?></p>
        <?php if ( empty( $this->post_types ) ) : ?>
            <p><i><?php _e( 'There are currently no registered custom post types to edit.', 'custom-post-type-parents' ); ?></i></p>
        <?php endif;
    }

    /**
     * Output a checkbox setting.
     *
     * @since  1.0.0
     */
    public function checkbox_callback( $args ) {
        $option_name = esc_attr( $this->option_name ) . '[' . $args['id'] . ']';
        $option_value = isset( $this->options[ $args['id'] ] ) ? $this->options[ $args['id'] ] : '';
        printf(
            '<label for="%s"><input type="checkbox" value="1" id="%s" name="%s" %s/> %s</label>',
            $args['id'],
            $args['id'],
            $option_name,
            checked( 1, $option_value, false ),
            $args['description']
        );
    }

    /**
     * Output a <select> version selector.
     *
     * @since  1.0.0
     *
     * @param array  $versions  All available Font Awesome versions
     */
    public function pages_dropdown_callback( $args ) {

    	$pages = get_pages();

        if ( $pages ) {

            $option_name = 'parent-' . $args['post_type_object']->name;

            $args = array(
                'id'               => $option_name,
                'name'             => $this->option_name . '[' . $option_name . ']',
                'selected'         => ! empty( $this->options[ $option_name ] ) ? $this->options[ $option_name ] : '',
                'show_option_none' => __( 'None', 'custom-post-type-parents' ),
            );
            wp_dropdown_pages( $args );

        } else {
        	echo __( 'There are no pages to choose from.', 'custom-post-type-parents' );
        }
    }

    /**
     * Validate each settings field as needed.
     *
     * @param  array  $input  Contains all settings fields as array keys.
     */
    public function validate_settings( $input ) {

        foreach ( $input as $key => $value ) {
            $input[ $key ] = wp_filter_nohtml_kses( $value );
        }

        return $input;

    }

}
