<?php
/**
 * Class for registering features for the WordPress AI Demo.
 *
 * @package WordPress\AI_Demo
 */

namespace WP_AI_Demo;

/**
 * Registers features for the WordPress AI Demo.
 */
class WP_Feature_Register {

	/**
	 * Register features for the WordPress AI Demo.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_features() {
		// Only register features if WP_Feature class is available
		if ( ! class_exists( 'WP_Feature' ) ) {
			return;
		}

		/** Demo Features */
		wp_register_feature(
			array(
				'id'          => 'wp-ai-demo/site-info',
				'name'        => __( 'Site Information', 'wp-ai-demo' ),
				'description' => __( 'Get comprehensive information about the WordPress site including name, description, URL, version, language, timezone, date/time formats, active plugins, and active theme.', 'wp-ai-demo' ),
				'type'        => \WP_Feature::TYPE_RESOURCE,
				'categories'  => array( 'demo', 'site', 'information', 'wordpress' ),
				'callback'    => array( $this, 'site_info_callback' ),
			)
		);

		wp_register_feature(
			array(
				'id'          => 'wp-ai-demo/admin-info',
				'name'        => __( 'Admin Information', 'wp-ai-demo' ),
				'description' => __( 'Get information about the current admin user and administrative capabilities.', 'wp-ai-demo' ),
				'type'        => \WP_Feature::TYPE_RESOURCE,
				'categories'  => array( 'demo', 'admin', 'user', 'wordpress' ),
				'callback'    => array( $this, 'admin_info_callback' ),
			)
		);
	}

	/**
	 * Callback for the site info feature.
	 *
	 * @param array $input Input parameters for the feature.
	 * @return array Site information.
	 */
	public function site_info_callback( $input ) {
		return array(
			'name'            => get_bloginfo( 'name' ),
			'description'     => get_bloginfo( 'description' ),
			'url'             => home_url(),
			'admin_url'       => admin_url(),
			'version'         => get_bloginfo( 'version' ),
			'language'        => get_bloginfo( 'language' ),
			'timezone'        => wp_timezone_string(),
			'date_format'     => get_option( 'date_format' ),
			'time_format'     => get_option( 'time_format' ),
			'active_plugins'  => get_option( 'active_plugins' ),
			'active_theme'    => get_option( 'stylesheet' ),
			'users_can_register' => get_option( 'users_can_register' ),
			'start_of_week'   => get_option( 'start_of_week' ),
			'default_role'    => get_option( 'default_role' ),
		);
	}

	/**
	 * Callback for the admin info feature.
	 *
	 * @param array $input Input parameters for the feature.
	 * @return array Admin information.
	 */
	public function admin_info_callback( $input ) {
		$current_user = wp_get_current_user();
		
		return array(
			'user_id'         => $current_user->ID,
			'username'        => $current_user->user_login,
			'display_name'    => $current_user->display_name,
			'email'           => $current_user->user_email,
			'roles'           => $current_user->roles,
			'capabilities'    => array_keys( $current_user->allcaps ),
			'can_manage_options' => current_user_can( 'manage_options' ),
			'can_edit_posts'  => current_user_can( 'edit_posts' ),
			'can_edit_pages'  => current_user_can( 'edit_pages' ),
			'can_manage_plugins' => current_user_can( 'activate_plugins' ),
		);
	}
}