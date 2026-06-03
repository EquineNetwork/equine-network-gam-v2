<?php
class Equinenetwork_Gam_V2 {

	protected $loader;
	protected $plugin_name;
	protected $version;

	public function __construct() {
		$this->version     = EQUINENETWORK_GAM_V2_VERSION;
		$this->plugin_name = 'equinenetwork-gam-v2';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_elementor_hooks();
	}

	private function load_dependencies() {
		require_once EQUINENETWORK_GAM_V2_PATH . 'includes/class-equinenetwork-gam-v2-loader.php';
		require_once EQUINENETWORK_GAM_V2_PATH . 'includes/class-equinenetwork-gam-v2-i18n.php';
		require_once EQUINENETWORK_GAM_V2_PATH . 'includes/class-equinenetwork-gam-v2-api.php';
require_once EQUINENETWORK_GAM_V2_PATH . 'admin/class-equinenetwork-gam-v2-admin.php';
		require_once EQUINENETWORK_GAM_V2_PATH . 'admin/class-equinenetwork-gam-v2-metabox.php';
		require_once EQUINENETWORK_GAM_V2_PATH . 'public/class-equinenetwork-gam-v2-public.php';
		require_once EQUINENETWORK_GAM_V2_PATH . 'public/class-equinenetwork-gam-v2-takeover.php';
		require_once EQUINENETWORK_GAM_V2_PATH . 'public/class-equinenetwork-gam-v2-carousel-render.php';
		require_once EQUINENETWORK_GAM_V2_PATH . 'public/class-equinenetwork-gam-v2-carousel-shortcode.php';
		require_once EQUINENETWORK_GAM_V2_PATH . 'public/class-equinenetwork-gam-v2-leaderboard.php';
		$this->loader = new Equinenetwork_Gam_V2_Loader();
	}

	private function set_locale() {
		$plugin_i18n = new Equinenetwork_Gam_V2_i18n();
		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
	}

	private function define_admin_hooks() {
		$plugin_admin = new Equinenetwork_Gam_V2_Admin( $this->plugin_name, $this->version );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_menu' );

		new Equinenetwork_Gam_V2_Metabox();
	}

	private function define_public_hooks() {
		$plugin_public = new Equinenetwork_Gam_V2_Public( $this->plugin_name, $this->version );
		$this->loader->add_action( 'wp_head', $plugin_public, 'gam_head_inject' );
		$this->loader->add_action( 'wp_footer', $plugin_public, 'gam_foot_logic' );
		$this->loader->add_filter( 'the_content', $plugin_public, 'return_categories_on_post' );
		$this->loader->add_filter( 'the_content', $plugin_public, 'inject_stacker', 20 );

		$plugin_takeover = new Equinenetwork_Gam_V2_Takeover();
		$this->loader->add_action( 'wp_footer', $plugin_takeover, 'render_takeover' );

		$plugin_leaderboard = new Equinenetwork_Gam_V2_Leaderboard();
		$this->loader->add_action( 'wp_footer', $plugin_leaderboard, 'render_leaderboards' );

		Equinenetwork_Gam_V2_Carousel_Shortcode::register();

		add_action( 'engam_warm_slot_sizes', function( $slot ) {
			require_once EQUINENETWORK_GAM_V2_PATH . 'includes/class-equinenetwork-gam-v2-api.php';
			( new Equinenetwork_Gam_V2_API() )->get_slot_sizes( $slot );
		} );
	}

	private function define_elementor_hooks() {
		add_action( 'elementor/widgets/register', array( $this, 'register_elementor_widgets' ) );
	}

	public function register_elementor_widgets( $widgets_manager ) {
		require_once EQUINENETWORK_GAM_V2_PATH . 'elementor/class-equinenetwork-gam-v2-widget.php';
		$widgets_manager->register( new Equinenetwork_Gam_V2_Widget() );

		require_once EQUINENETWORK_GAM_V2_PATH . 'elementor/class-equinenetwork-gam-v2-carousel-widget.php';
		$widgets_manager->register( new Equinenetwork_Gam_V2_Carousel_Widget() );
	}

	public function run() {
		$this->loader->run();
	}

	public function get_plugin_name() { return $this->plugin_name; }
	public function get_loader()      { return $this->loader; }
	public function get_version()     { return $this->version; }
}
