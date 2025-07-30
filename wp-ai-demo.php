<?php
/**
 * Plugin Name: WordPress AI Demo
 * Plugin URI: https://github.com/Automattic/wp-ai-demo
 * Description: A demonstration plugin for WordPress AI integration featuring an interactive chat interface and OpenAI API proxy.
 * Version: 1.0.0
 * Author: Automattic AI
 * Author URI: https://automattic.ai/
 * Text Domain: wp-ai-demo
 * License: GPL-2.0-or-later
 * License URI: https://spdx.org/licenses/GPL-2.0-or-later.html
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package WordPress\AI_Demo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'WP_AI_DEMO_PATH', plugin_dir_path( __FILE__ ) );
define( 'WP_AI_DEMO_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_AI_DEMO_VERSION', '1.0.0' );

// Include the main proxy class.
require_once WP_AI_DEMO_PATH . 'includes/class-wp-ai-api-proxy.php';

// Include the options class.
require_once WP_AI_DEMO_PATH . 'includes/class-wp-ai-api-options.php';

// Include the feature registration class.
require_once WP_AI_DEMO_PATH . 'includes/class-wp-feature-register.php';

$proxy_instance = new WP_AI_Demo\WP_AI_API_Proxy();
$proxy_instance->register_hooks();

$options_instance = new WP_AI_Demo\WP_AI_API_Options();
$options_instance->init();

// Register additional demo features if WP Feature API is available.
if ( function_exists( 'wp_register_feature' ) ) {
	$feature_register_instance = new WP_AI_Demo\WP_Feature_Register();
	add_action( 'wp_feature_api_init', array( $feature_register_instance, 'register_features' ) );
}

/**
 * Enqueues scripts and styles for the admin area.
 */
function wp_ai_demo_enqueue_assets() {
	$script_asset_path = WP_AI_DEMO_PATH . 'build/index.asset.php';
	if ( ! file_exists( $script_asset_path ) ) {
		return;
	}
	$script_asset = require $script_asset_path;

	// Check if WP Feature API is available and enqueue wp-features dependency
	$dependencies = $script_asset['dependencies'];
	if ( wp_script_is( 'wp-features', 'registered' ) ) {
		$dependencies[] = 'wp-features';
	}

	// Enqueue the main script.
	wp_enqueue_script(
		'wp-ai-demo-script',
		WP_AI_DEMO_URL . 'build/index.js',
		$dependencies,
		$script_asset['version'],
		true // Load in footer.
	);

	// Only enqueue wp-components CSS if it's not already loaded by core.
	if ( ! wp_style_is( 'wp-components', 'enqueued' ) ) {
		wp_enqueue_style(
			'wp-components',
			includes_url( 'css/dist/components/style.min.css' ),
			array(),
			$script_asset['version']
		);
	}

	wp_enqueue_style(
		'wp-ai-demo-style',
		WP_AI_DEMO_URL . 'build/style-index.css',
		array( 'wp-components' ),
		$script_asset['version']
	);
}
add_action( 'admin_enqueue_scripts', 'wp_ai_demo_enqueue_assets' );

/**
 * Adds the root container div to the admin footer.
 */
function wp_ai_demo_add_root_container() {
	?>
	<div id="wp-ai-demo-chat"></div>
	<?php
}
add_action( 'admin_footer', 'wp_ai_demo_add_root_container' );

/**
 * Display admin notice if WP Feature API is not available.
 */
function wp_ai_demo_admin_notices() {
	if ( ! function_exists( 'wp_register_feature' ) ) {
		?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<?php esc_html_e( 'WordPress AI Demo: The WP Feature API plugin is recommended for full functionality. Some features may be limited without it.', 'wp-ai-demo' ); ?>
			</p>
		</div>
		<?php
	}
}
add_action( 'admin_notices', 'wp_ai_demo_admin_notices' );