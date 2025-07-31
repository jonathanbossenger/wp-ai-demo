<?php
/**
 * Main class for the AI API Proxy REST endpoints.
 *
 * @package WordPress\AI_Demo
 */

namespace WP_AI_Demo;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Registers and handles REST API endpoints for proxying AI service requests.
 */
class WP_AI_API_Proxy {

	/**
	 * Supported AI API service providers.
	 */
	private const SUPPORTED_AI_API_SERVICES = [ 'openai', 'anthropic' ];

	/**
	 * Base URL for the OpenAI API.
	 */
	private const OPENAI_API_ROOT = 'https://api.openai.com/v1/';

	/**
	 * Base URL for the Anthropic API.
	 */
	private const ANTHROPIC_API_ROOT = 'https://api.anthropic.com/v1/';

	/**
	 * Cache namespace for AI proxy data.
	 */
	private const AI_API_PROXY_CACHE_NAMESPACE = 'ai_api_proxy';

	/**
	 * Cache key prefix for provider models.
	 */
	private const AI_API_PROXY_MODELS_CACHE_KEY_PREFIX = 'models';

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wp/v2';

	/**
	 * REST API base route for the proxy.
	 *
	 * @var string
	 */
	protected $rest_base = 'ai-demo-proxy/v1';

	/**
	 * Registers WordPress hooks.
	 */
	public function register_hooks() {
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Registers the REST API routes.
	 */
	public function register_rest_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/healthcheck',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'ai_api_healthcheck' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/models',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_available_models' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<api_path>.*)',
			array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => array( $this, 'ai_api_proxy' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => array(
					'api_path' => array(
						'description' => __( 'The path to proxy to the AI service API.', 'wp-ai-demo' ),
						'type'        => 'string',
						'required'    => true,
					),
				),
			)
		);
	}

	/**
	 * Checks if the current user has permissions to access protected endpoints.
	 *
	 * @return bool|WP_Error True if the user has permission, WP_Error otherwise.
	 */
	public function check_permissions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Sorry, you are not allowed to access this endpoint.', 'wp-ai-demo' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
		return true;
	}

	/**
	 * Healthcheck endpoint callback.
	 * Checks if required API key constants are defined.
	 *
	 * @param WP_REST_Request $request Incoming request data.
	 * @return WP_REST_Response Response object.
	 */
	public function ai_api_healthcheck( WP_REST_Request $request ) {
		$provider = WP_AI_API_Options::get_api_provider();
		$openai_key = WP_AI_API_Options::get_openai_api_key();
		$anthropic_key = WP_AI_API_Options::get_anthropic_api_key();

		$all_defined = false;
		if ( $provider === 'openai' && ! empty( $openai_key ) ) {
			$all_defined = true;
		} elseif ( $provider === 'anthropic' && ! empty( $anthropic_key ) ) {
			$all_defined = true;
		}

		$status = $all_defined ? 'OK' : 'Configuration Error';
		$code   = $all_defined ? 200 : 500;

		$response_data = [
			'status' => $status,
			'provider' => $provider,
		];

		return new WP_REST_Response( $response_data, $code );
	}

	/**
	 * Lists all the models available from the configured provider.
	 *
	 * @param WP_REST_Request $request Incoming request data.
	 * @return WP_Error|WP_REST_Response Model list data or error.
	 */
	public function list_available_models( WP_REST_Request $request ) {
		$provider = WP_AI_API_Options::get_api_provider();
		$provider_models = $this->get_provider_model_list( $provider );

		$all_models = [];

		if ( is_array( $provider_models ) ) {
			foreach ( $provider_models as $model ) {
				if ( is_object( $model ) ) {
					$model->owned_by = $provider;
					$all_models[]    = $model;
				}
			}
		}

		if ( empty( $all_models ) ) {
			return new WP_Error(
				'model_list_failed',
				__( 'Unable to retrieve model lists from the configured provider.', 'wp-ai-demo' ),
				[ 'status' => 500 ]
			);
		}

		$response_data = (object) [
			'object' => 'list',
			'data'   => $all_models,
		];

		return new WP_REST_Response( $response_data );
	}


	/**
	 * Proxies the request to the appropriate AI service (OpenAI or Anthropic).
	 *
	 * @param WP_REST_Request $request Incoming request data.
	 * @return WP_Error|WP_REST_Response Vendor data or error.
	 */
	public function ai_api_proxy( WP_REST_Request $request ) {
		$api_path = $request->get_param( 'api_path' );
		$method   = $request->get_method();
		$body     = $request->get_body();
		$headers  = $request->get_headers();

		$provider = WP_AI_API_Options::get_api_provider();

		// Transform the request for different providers
		if ( $provider === 'anthropic' && $api_path === 'chat/completions' ) {
			$result = $this->proxy_anthropic_chat( $request, $body );
		} else {
			$result = $this->proxy_openai_request( $request, $api_path, $method, $body, $headers );
		}

		return $result;
	}

	/**
	 * Proxies OpenAI requests.
	 *
	 * @param WP_REST_Request $request Incoming request data.
	 * @param string $api_path API path.
	 * @param string $method HTTP method.
	 * @param string $body Request body.
	 * @param array $headers Request headers.
	 * @return WP_Error|WP_REST_Response Vendor data or error.
	 */
	private function proxy_openai_request( WP_REST_Request $request, string $api_path, string $method, string $body, array $headers ) {
		$target_url  = self::OPENAI_API_ROOT . $api_path;
		$auth_header = sprintf( 'Bearer %s', WP_AI_API_Options::get_openai_api_key() );

		$outgoing_headers = array(
			'Content-Type'  => $headers['content_type'][0] ?? ( ! empty( $body ) ? 'application/json' : null ),
			'User-Agent'    => 'WordPress AI Demo/' . WP_AI_DEMO_VERSION,
			'Authorization' => $auth_header,
		);

		$outgoing_headers = array_filter( $outgoing_headers );

		$query_params = $request->get_query_params();
		if ( ! empty( $query_params ) ) {
			unset( $query_params['_envelope'] );
			unset( $query_params['_locale'] );
			$target_url = add_query_arg( $query_params, $target_url );
		}

		return $this->make_api_request( $target_url, $method, $outgoing_headers, $body );
	}

	/**
	 * Proxies Anthropic chat requests, transforming from OpenAI format to Anthropic format.
	 *
	 * @param WP_REST_Request $request Incoming request data.
	 * @param string $body Request body.
	 * @return WP_Error|WP_REST_Response Vendor data or error.
	 */
	private function proxy_anthropic_chat( WP_REST_Request $request, string $body ) {
		$request_data = json_decode( $body, true );
		if ( ! $request_data ) {
			return new WP_Error(
				'invalid_request_body',
				__( 'Invalid JSON in request body.', 'wp-ai-demo' ),
				array( 'status' => 400 )
			);
		}

		// Transform OpenAI format to Anthropic format
		$anthropic_data = $this->transform_openai_to_anthropic( $request_data );

		$target_url  = self::ANTHROPIC_API_ROOT . 'messages';
		$auth_header = WP_AI_API_Options::get_anthropic_api_key();

		$outgoing_headers = array(
			'Content-Type'      => 'application/json',
			'User-Agent'        => 'WordPress AI Demo/' . WP_AI_DEMO_VERSION,
			'x-api-key'         => $auth_header,
			'anthropic-version' => '2023-06-01',
		);

		$transformed_body = wp_json_encode( $anthropic_data );

		$response = $this->make_api_request( $target_url, 'POST', $outgoing_headers, $transformed_body );

		// Transform Anthropic response back to OpenAI format
		if ( ! is_wp_error( $response ) ) {
			$response_data = $response->get_data();
			if ( is_object( $response_data ) || is_array( $response_data ) ) {
				$openai_response = $this->transform_anthropic_to_openai( $response_data );
				$response->set_data( $openai_response );
			}
		}

		return $response;
	}

	/**
	 * Makes the actual API request.
	 *
	 * @param string $target_url Target URL.
	 * @param string $method HTTP method.
	 * @param array $headers Headers.
	 * @param string $body Request body.
	 * @return WP_Error|WP_REST_Response Response or error.
	 */
	private function make_api_request( string $target_url, string $method, array $headers, string $body ) {
		$response = wp_remote_request(
			$target_url,
			array(
				'method'  => $method,
				'headers' => $headers,
				'body'    => $body,
				'timeout' => 60,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'proxy_request_failed',
				__( 'Failed to connect to the AI service.', 'wp-ai-demo' ),
				array( 'status' => 502 )
			);
		}

		$response_code    = wp_remote_retrieve_response_code( $response );
		$response_headers = wp_remote_retrieve_headers( $response );
		$response_body    = wp_remote_retrieve_body( $response );

		$client_headers = [];
		if ( isset( $response_headers['content-type'] ) ) {
			$client_headers['Content-Type'] = $response_headers['content-type'];
		}

		if ( isset( $response_headers['x-request-id'] ) ) {
			$client_headers['X-Request-ID'] = $response_headers['x-request-id'];
		}

		$wp_response = new WP_REST_Response( $response_body, $response_code );

		foreach ( $client_headers as $key => $value ) {
			$wp_response->header( $key, $value );
		}

		// Process JSON responses
		if ( isset( $client_headers['Content-Type'] ) && str_contains( strtolower( $client_headers['Content-Type'] ), 'application/json' ) ) {
			$decoded_body = json_decode( $response_body );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				$wp_response->set_data( $decoded_body );
			}
		} else {
			$wp_response->set_data( $response_body );
		}

		return $wp_response;
	}

	/**
	 * Transforms OpenAI chat format to Anthropic messages format.
	 *
	 * @param array $openai_data OpenAI chat completion request data.
	 * @return array Anthropic messages request data.
	 */
	private function transform_openai_to_anthropic( array $openai_data ) {
		$messages = $openai_data['messages'] ?? [];
		$model = $openai_data['model'] ?? 'claude-3-sonnet-20240229';
		$max_tokens = $openai_data['max_tokens'] ?? 1000;
		$temperature = $openai_data['temperature'] ?? null;
		$tools = $openai_data['tools'] ?? [];

		// Separate system messages from other messages
		$system_content = '';
		$anthropic_messages = [];

		foreach ( $messages as $message ) {
			if ( $message['role'] === 'system' ) {
				$system_content .= ( $system_content ? "\n\n" : '' ) . $message['content'];
			} else {
				$anthropic_message = [
					'role' => $message['role'],
					'content' => $message['content'] ?? '',
				];

				// Handle tool calls
				if ( isset( $message['tool_calls'] ) && is_array( $message['tool_calls'] ) ) {
					$anthropic_message['content'] = [];
					if ( ! empty( $message['content'] ) ) {
						$anthropic_message['content'][] = [
							'type' => 'text',
							'text' => $message['content'],
						];
					}
					foreach ( $message['tool_calls'] as $tool_call ) {
						$anthropic_message['content'][] = [
							'type' => 'tool_use',
							'id' => $tool_call['id'],
							'name' => $tool_call['function']['name'],
							'input' => json_decode( $tool_call['function']['arguments'], true ) ?: [],
						];
					}
				}

				// Handle tool results
				if ( $message['role'] === 'tool' ) {
					$anthropic_message['role'] = 'user';
					$anthropic_message['content'] = [
						[
							'type' => 'tool_result',
							'tool_use_id' => $message['tool_call_id'],
							'content' => $message['content'],
						],
					];
				}

				$anthropic_messages[] = $anthropic_message;
			}
		}

		$anthropic_data = [
			'model' => $model,
			'max_tokens' => $max_tokens,
			'messages' => $anthropic_messages,
		];

		if ( ! empty( $system_content ) ) {
			$anthropic_data['system'] = $system_content;
		}

		if ( $temperature !== null ) {
			$anthropic_data['temperature'] = $temperature;
		}

		// Transform tools
		if ( ! empty( $tools ) ) {
			$anthropic_tools = [];
			foreach ( $tools as $tool ) {
				if ( $tool['type'] === 'function' ) {
					$anthropic_tools[] = [
						'name' => $tool['function']['name'],
						'description' => $tool['function']['description'] ?? '',
						'input_schema' => $tool['function']['parameters'] ?? [],
					];
				}
			}
			if ( ! empty( $anthropic_tools ) ) {
				$anthropic_data['tools'] = $anthropic_tools;
			}
		}

		return $anthropic_data;
	}

	/**
	 * Transforms Anthropic messages response to OpenAI chat completion format.
	 *
	 * @param mixed $anthropic_data Anthropic messages response data.
	 * @return array OpenAI chat completion response data.
	 */
	private function transform_anthropic_to_openai( $anthropic_data ) {
		$anthropic_data = (array) $anthropic_data;

		$message = [
			'role' => 'assistant',
			'content' => '',
		];

		$tool_calls = [];

		if ( isset( $anthropic_data['content'] ) && is_array( $anthropic_data['content'] ) ) {
			$text_parts = [];
			foreach ( $anthropic_data['content'] as $content_block ) {
				$content_block = (array) $content_block;
				if ( $content_block['type'] === 'text' ) {
					$text_parts[] = $content_block['text'];
				} elseif ( $content_block['type'] === 'tool_use' ) {
					$tool_calls[] = [
						'id' => $content_block['id'],
						'type' => 'function',
						'function' => [
							'name' => $content_block['name'],
							'arguments' => wp_json_encode( $content_block['input'] ?? [] ),
						],
					];
				}
			}
			$message['content'] = implode( "\n", $text_parts );
		}

		if ( ! empty( $tool_calls ) ) {
			$message['tool_calls'] = $tool_calls;
		}

		$openai_response = [
			'id' => $anthropic_data['id'] ?? 'chatcmpl-' . wp_generate_uuid4(),
			'object' => 'chat.completion',
			'created' => time(),
			'model' => $anthropic_data['model'] ?? 'claude-3-sonnet-20240229',
			'choices' => [
				[
					'index' => 0,
					'message' => $message,
					'finish_reason' => $anthropic_data['stop_reason'] ?? 'stop',
				],
			],
			'usage' => [
				'prompt_tokens' => $anthropic_data['usage']['input_tokens'] ?? 0,
				'completion_tokens' => $anthropic_data['usage']['output_tokens'] ?? 0,
				'total_tokens' => ( $anthropic_data['usage']['input_tokens'] ?? 0 ) + ( $anthropic_data['usage']['output_tokens'] ?? 0 ),
			],
		];

		return $openai_response;
	}

	/**
	 * Returns the list of available models for a specific provider.
	 * Uses caching.
	 *
	 * @param string $provider The provider key ('openai' or 'anthropic').
	 * @return array List of models (structure depends on provider) or empty array on error/cache miss failure.
	 */
	private function get_provider_model_list( string $provider ): array {
		if ( ! in_array( $provider, self::SUPPORTED_AI_API_SERVICES, true ) ) {
			return [];
		}

		// For Anthropic, return a static list since they don't have a models endpoint
		if ( $provider === 'anthropic' ) {
			$api_key = WP_AI_API_Options::get_anthropic_api_key();
			if ( empty( $api_key ) ) {
				return [];
			}

			return [
				(object) [
					'id' => 'claude-3-5-sonnet-20241022',
					'object' => 'model',
					'created' => time(),
					'owned_by' => 'anthropic',
				],
				(object) [
					'id' => 'claude-3-5-sonnet-20240620',
					'object' => 'model',
					'created' => time(),
					'owned_by' => 'anthropic',
				],
				(object) [
					'id' => 'claude-3-sonnet-20240229',
					'object' => 'model',
					'created' => time(),
					'owned_by' => 'anthropic',
				],
				(object) [
					'id' => 'claude-3-opus-20240229',
					'object' => 'model',
					'created' => time(),
					'owned_by' => 'anthropic',
				],
				(object) [
					'id' => 'claude-3-haiku-20240307',
					'object' => 'model',
					'created' => time(),
					'owned_by' => 'anthropic',
				],
			];
		}

		$api_key = '';
		switch ( $provider ) {
			case 'openai':
				$api_key = WP_AI_API_Options::get_openai_api_key();
				break;
		}
		if ( empty( $api_key ) ) {
			return [];
		}

		$cache_key = sprintf( '%s-%s', self::AI_API_PROXY_MODELS_CACHE_KEY_PREFIX, $provider );
		$found     = false;

		$cached_models = wp_cache_get( $cache_key, self::AI_API_PROXY_CACHE_NAMESPACE, false, $found );
		if ( $found ) {
			return is_array( $cached_models ) ? $cached_models : [];
		}

		$headers  = [];
		$api_path = '';

		switch ( $provider ) {
			case 'openai':
				$headers = [
					'Authorization' => sprintf( 'Bearer %s', WP_AI_API_Options::get_openai_api_key() ),
					'User-Agent'    => 'WordPress AI Demo/' . WP_AI_DEMO_VERSION,
				];
				$api_path = self::OPENAI_API_ROOT . 'models';
				break;
		}

		if ( empty( $api_path ) ) {
			return [];
		}

		$response = wp_remote_get(
			$api_path,
			array(
				'headers' => $headers,
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return [];
		}

		$body = wp_remote_retrieve_body( $response );
		if ( ! $body ) {
			return [];
		}

		$json_data = json_decode( $body );
		if ( ! $json_data || ! is_object( $json_data ) ) {
			return [];
		}

		$models_data = [];
		if ( $provider === 'openai' && isset( $json_data->data ) && is_array( $json_data->data ) ) {
			$models_data = $json_data->data;
		} else {
			return [];
		}

		if ( is_array( $models_data ) ) {
			wp_cache_set( $cache_key, $models_data, self::AI_API_PROXY_CACHE_NAMESPACE, 30 * MINUTE_IN_SECONDS );
			return $models_data;
		} else {
			return [];
		}
	}
}