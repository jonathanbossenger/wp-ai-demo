<?php
/**
 * Options class for the AI API Proxy & WordPress AI Demo.
 *
 * @package WordPress\AI_Demo
 */

namespace WP_AI_Demo;

/**
 * Handles the settings page for the AI API Proxy & WordPress AI Demo.
 */
class WP_AI_API_Options {

	/**
	 * Option name for OpenAI API key.
	 *
	 * @var string
	 */
	const OPENAI_OPTION_NAME = 'wp_ai_demo_openai_key';

	/**
	 * Option name for Anthropic API key.
	 *
	 * @var string
	 */
	const ANTHROPIC_OPTION_NAME = 'wp_ai_demo_anthropic_key';

	/**
	 * Option name for selected API provider.
	 *
	 * @var string
	 */
	const API_PROVIDER_OPTION_NAME = 'wp_ai_demo_api_provider';

	/**
	 * Settings page slug.
	 *
	 * @var string
	 */
	const OPTION_PAGE = 'wp-ai-demo-settings';

	/**
	 * Initializes the options page.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_options_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_notices', array( $this, 'display_admin_notices' ) );
	}

	/**
	 * Adds the options page to the admin menu.
	 */
	public function add_options_page() {
		add_options_page(
			__( 'WordPress AI Demo - Settings', 'wp-ai-demo' ),
			__( 'WordPress AI Demo', 'wp-ai-demo' ),
			'manage_options',
			self::OPTION_PAGE,
			array( $this, 'render_options_page' )
		);
	}

	/**
	 * Registers the settings.
	 */
	public function register_settings() {
		// Register settings for API keys and provider.
		register_setting(
			self::OPTION_PAGE,
			self::OPENAI_OPTION_NAME,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_setting(
			self::OPTION_PAGE,
			self::ANTHROPIC_OPTION_NAME,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_setting(
			self::OPTION_PAGE,
			self::API_PROVIDER_OPTION_NAME,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'openai',
			)
		);

		add_settings_section(
			'wp_ai_demo_api_section',
			__( 'API Settings', 'wp-ai-demo' ),
			array( $this, 'render_api_section_description' ),
			self::OPTION_PAGE
		);

		add_settings_field(
			'api_provider',
			__( 'API Provider', 'wp-ai-demo' ),
			array( $this, 'render_api_provider_field' ),
			self::OPTION_PAGE,
			'wp_ai_demo_api_section'
		);

		add_settings_field(
			'openai_api_key',
			__( 'OpenAI API Key', 'wp-ai-demo' ),
			array( $this, 'render_openai_api_key_field' ),
			self::OPTION_PAGE,
			'wp_ai_demo_api_section'
		);

		add_settings_field(
			'anthropic_api_key',
			__( 'Anthropic API Key', 'wp-ai-demo' ),
			array( $this, 'render_anthropic_api_key_field' ),
			self::OPTION_PAGE,
			'wp_ai_demo_api_section'
		);
	}

	/**
	 * Renders the API section description.
	 */
	public function render_api_section_description() {
		echo '<p>' . esc_html__( 'Choose your preferred AI provider and configure the corresponding API key for the WordPress AI Demo.', 'wp-ai-demo' ) . '</p>';
	}

	/**
	 * Renders the API provider selection field.
	 */
	public function render_api_provider_field() {
		$value = get_option( self::API_PROVIDER_OPTION_NAME, 'openai' );
		?>
		<select name="<?php echo esc_attr( self::API_PROVIDER_OPTION_NAME ); ?>" id="api_provider_select">
			<option value="openai" <?php selected( $value, 'openai' ); ?>>OpenAI</option>
			<option value="anthropic" <?php selected( $value, 'anthropic' ); ?>>Anthropic</option>
		</select>
		<p class="description">
			<?php esc_html_e( 'Select which AI provider you want to use.', 'wp-ai-demo' ); ?>
		</p>
		<script>
		jQuery(document).ready(function($) {
			function toggleApiKeyFields() {
				var provider = $('#api_provider_select').val();
				var openaiRow = $('#openai_api_key').closest('tr');
				var anthropicRow = $('#anthropic_api_key').closest('tr');
				
				if (provider === 'openai') {
					openaiRow.show();
					anthropicRow.hide();
				} else if (provider === 'anthropic') {
					openaiRow.hide();
					anthropicRow.show();
				}
			}
			
			$('#api_provider_select').change(toggleApiKeyFields);
			toggleApiKeyFields(); // Initial call
		});
		</script>
		<?php
	}

	/**
	 * Renders the OpenAI API key field.
	 */
	public function render_openai_api_key_field() {
		$value = get_option( self::OPENAI_OPTION_NAME );
		?>
		<input type="password"
			   id="openai_api_key"
			   name="<?php echo esc_attr( self::OPENAI_OPTION_NAME ); ?>"
			   value="<?php echo esc_attr( $value ); ?>"
			   class="regular-text"
		/>
		<p class="description">
			<?php esc_html_e( 'Enter your OpenAI API key to enable AI chat functionality.', 'wp-ai-demo' ); ?>
		</p>
		<?php
	}

	/**
	 * Renders the Anthropic API key field.
	 */
	public function render_anthropic_api_key_field() {
		$value = get_option( self::ANTHROPIC_OPTION_NAME );
		?>
		<input type="password"
			   id="anthropic_api_key"
			   name="<?php echo esc_attr( self::ANTHROPIC_OPTION_NAME ); ?>"
			   value="<?php echo esc_attr( $value ); ?>"
			   class="regular-text"
		/>
		<p class="description">
			<?php esc_html_e( 'Enter your Anthropic API key to enable AI chat functionality.', 'wp-ai-demo' ); ?>
		</p>
		<?php
	}

	/**
	 * Renders the options page.
	 */
	public function render_options_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::OPTION_PAGE );
				do_settings_sections( self::OPTION_PAGE );
				submit_button();
				?>
			</form>
			<div class="notice notice-info">
				<p>
					<?php esc_html_e( 'After configuring your API key, you can use the AI chat interface that appears in the admin area to interact with your WordPress site.', 'wp-ai-demo' ); ?>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Displays admin notices.
	 */
	public function display_admin_notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$provider = get_option( self::API_PROVIDER_OPTION_NAME, 'openai' );
		$openai_key = get_option( self::OPENAI_OPTION_NAME );
		$anthropic_key = get_option( self::ANTHROPIC_OPTION_NAME );

		$is_configured = false;
		if ( $provider === 'openai' && ! empty( $openai_key ) ) {
			$is_configured = true;
		} elseif ( $provider === 'anthropic' && ! empty( $anthropic_key ) ) {
			$is_configured = true;
		}

		if ( ! $is_configured ) {
			$provider_name = $provider === 'anthropic' ? 'Anthropic' : 'OpenAI';
			?>
			<div class="notice notice-warning is-dismissible">
				<p>
					<?php
					printf(
						/* translators: %1$s: Provider name, %2$s: URL to the settings page */
						esc_html__( 'WordPress AI Demo: No %1$s API key configured. The AI chat interface will not work. Please configure your API key in the %2$s.', 'wp-ai-demo' ),
						$provider_name,
						'<a href="' . esc_url( admin_url( 'options-general.php?page=' . self::OPTION_PAGE ) ) . '">' . esc_html__( 'WordPress AI Demo settings', 'wp-ai-demo' ) . '</a>'
					);
					?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Get the OpenAI API key.
	 *
	 * @return string The OpenAI API key.
	 */
	public static function get_openai_api_key(): string {
		return get_option( self::OPENAI_OPTION_NAME, '' );
	}

	/**
	 * Get the Anthropic API key.
	 *
	 * @return string The Anthropic API key.
	 */
	public static function get_anthropic_api_key(): string {
		return get_option( self::ANTHROPIC_OPTION_NAME, '' );
	}

	/**
	 * Get the selected API provider.
	 *
	 * @return string The selected API provider ('openai' or 'anthropic').
	 */
	public static function get_api_provider(): string {
		return get_option( self::API_PROVIDER_OPTION_NAME, 'openai' );
	}
}