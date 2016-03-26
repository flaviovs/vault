<?php

namespace Vault;

class REST_App extends Web_App {

	protected $auth_app;

	public function __construct( $name, array $globals = null ) {
		parent::__construct( $name, $globals );
		$this->response->content->setType( 'application/json' );
	}

	protected function init_router() {
		if ( $this->conf->get( 'debug', 'api', false ) ) {
			$this->router->addGet( 'devel.info', '/devel/info' );
		}

		$this->router->addPost( 'request', '/request' );
		$this->router->addPost( 'unlock', '/unlock' );
	}

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

	protected function handle_request_add() {
		$this->check_auth();

		$this->response->content->set(
			$this->service->register_request(
				$this->auth_app->key,
				$this->request->post->get( 'email' ),
				$this->request->post->get( 'instructions' ),
				$this->request->post->get( 'app_data' ) ) );
	}

	protected function handle_unlock() {
		$this->check_auth();

		$reqid = $this->request->post->get( 'reqid' );
		$key = base64_decode( $this->request->post->get( 'key' ) );

		error_log("[$reqid] [$key]");

		$secret = $this->repo->find_secret( $reqid );

		if ( ! $secret->is_mac_valid( $key ) ) {
			throw new \InvalidArgumentException( 'Invalid secret/key' );
		}

		$this->response->content->set(
			[
				'secret' => $this->service->unlock_secret( $secret, $key ),
			] );
	}

	protected function handle_devel_info() {
		$this->check_auth();

		$this->response->content->set( $_SERVER );
	}

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

	protected function handle_exception( \Exception $ex ) {
		parent::handle_exception( $ex );
		$this->response->content->set(
			[ 'message' => 'It was not possible to process the request.' ] );
	}

	protected function handle_unauthorized( $realm ) {
		parent::handle_unauthorized( $realm );
		$this->response->content->set(
			[ 'message' => 'Please, authenticate yourself first.' ] );
	}

	protected function handle_not_found( $msg ) {
		parent::handle_not_found( $msg );
		$this->response->content->set(
			[ 'message' => 'Unknown request.' ] );
	}

	protected function prepare_response() {
		$json = json_encode( $this->response->content->get() );
		$this->response->content->set( $json );
		$this->response->headers->set( 'Content-Length', strlen( $json ) );
		$this->response->cache->disable();
		parent::prepare_response();
	}
}
