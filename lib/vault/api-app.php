<?php
/**
 * Contains the REST API app class
 */

namespace Vault;

/**
 * The REST API app class.
 */
class REST_App extends Web_App {

	/**
	 * The current (authenticated) client app.
	 *
	 * @var App The app object.
	 */
	protected $auth_app;

	/**
	 * {@inheritdocs}
	 */
	public function __construct( $name, array $globals = null ) {
		parent::__construct( $name, $globals );
		$this->response->content->setType( 'application/json' );
	}

	/**
	 * {@inheritdoc}
	 */
	protected function init_router() {
		if ( $this->conf->get( 'debug', 'api', false ) ) {
			$this->router->addGet( 'devel.info', '/devel/info' );
		}

		$this->router->addPost( 'request', '/request' );
		$this->router->addPost( 'unlock', '/unlock' );
	}

	/**
	 * Checks app authentication.
	 *
	 * @throws UnauthorizedException if the app is not properly
	 * authenticated.
	 */
	protected function check_auth() {
		$app_key = $this->request->client->getAuthUser();
		if ( ! $app_key ) {
			throw new UnauthorizedException();
		}

		try {
			$this->auth_app = $this->repo->find_app_by_key( $app_key );
		} catch ( VaultDataException $ex ) {
			$this->log->addNotice( "No such app '$app_key'" );
			throw new UnauthorizedException();
		}

		if ( ! password_verify( $this->request->client->getAuthPw(),
		                        $this->auth_app->secret ) ) {
			$this->log->addNotice( "Authentication failed for '$app_key'" );
			throw new UnauthorizedException();
		}
	}

	/**
	 * Handles the request add API call.
	 */
	protected function handle_request_add() {
		$this->check_auth();

		$this->response->content->set(
			$this->service->register_request(
				$this->auth_app->key,
				$this->request->post->get( 'email' ),
				$this->request->post->get( 'instructions' ),
				$this->request->post->get( 'app_data' ) ) );
	}

	/**
	 * Handles the secret unlock API call.
	 *
	 * @throws \InvalidArgumentException on invalid data.
	 */
	protected function handle_unlock() {
		$this->check_auth();

		$reqid = $this->request->post->get( 'reqid' );
		$key = base64_decode( $this->request->post->get( 'key' ) );

		$secret = $this->repo->find_secret( $reqid );

		if ( ! $secret->is_mac_valid( $key ) ) {
			throw new \InvalidArgumentException( 'Invalid secret/key' );
		}

		$this->response->content->set(
			[
				'secret' => $this->service->unlock_secret( $secret, $key ),
			] );
	}

	/**
	 * Handles the development debug info API call.
	 *
	 * This call is available only if "api" is set to true in the
	 * "[debug]" section of the Vault configuration.
	 */
	protected function handle_devel_info() {
		$this->check_auth();

		$this->response->content->set( $_SERVER );
	}

	/**
	 * General request dispatcher.
	 *
	 * @param \Aura\Router\Route $route The request route.
	 *
	 * @throws \RuntimeException if the route contains an invalid
	 * action.
	 */
	protected function handle_request( \Aura\Router\Route $route ) {

		switch ( $route->params['action'] ) {

			case 'request':
				$this->handle_request_add();
				break;

			case 'unlock':
				$this->handle_unlock();
				break;

			case 'devel.info':
				$this->handle_devel_info();
				break;

			default:
				throw new \RuntimeException( 'Invalid action: '
				                             . $route->params['action'] );
		}
	}

	/**
	 * {@inheritdoc}
	 */
	protected function handle_exception( \Exception $ex ) {
		parent::handle_exception( $ex );
		$this->response->content->set(
			[ 'message' => 'It was not possible to process the request.' ] );
	}

	/**
	 * {@inheritdoc}
	 */
	protected function handle_unauthorized( $realm ) {
		parent::handle_unauthorized( $realm );
		$this->response->content->set(
			[ 'message' => 'Please, authenticate yourself first.' ] );
	}

	/**
	 * {@inheritdoc}
	 */
	protected function handle_not_found( $msg ) {
		parent::handle_not_found( $msg );
		$this->response->content->set(
			[ 'message' => 'Unknown request.' ] );
	}

	/**
	 * {@inheritdoc}
	 */
	protected function prepare_response() {
		$json = json_encode( $this->response->content->get() );
		$this->response->content->set( $json );
		$this->response->headers->set( 'Content-Length', strlen( $json ) );
		$this->response->cache->disable();
		parent::prepare_response();
	}
}
