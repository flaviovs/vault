<?php

namespace Vault;

class ForbiddenException extends \Exception {}

class ClientApp {
	protected $request;
	protected $response;
	protected $router;
	protected $conf;
	protected $root_session;
	protected $session;
	protected $log;
	protected $views;
	protected $messages;
	protected $user;

	public function __construct( $name ) {
		// Workaround '_SERVER' not present in $GLOBALS, unless
		// referenced before (see
		// https://bugs.php.net/bug.php?id=65223).
		$_SERVER;

		$web_factory = new \Aura\Web\WebFactory( $GLOBALS );
		$this->request = $web_factory->newRequest();
		$this->response = $web_factory->newResponse();

		$this->response->content->setType( 'text/html' );
		$this->response->content->setCharset( 'utf-8' );

		$router_factory = new \Aura\Router\RouterFactory();
		$this->router = $router_factory->newInstance();

		$this->conf = [];

		$session_factory = new \Aura\Session\SessionFactory;
		$this->root_session = $session_factory->newInstance(
			$this->request->cookies->get() );
		$this->session = $this->root_session->getSegment( __CLASS__ );

		$this->log = new \Monolog\Logger( $name );

		$this->views = new \UView\Registry( __DIR__ . '/../view/client' );

		$this->messages = new Message_Area();

	}

	protected function get_conf( $section, $key, $default = null ) {
		return ( ! empty( $this->conf[ $section ] )
		         && array_key_exists( $key, $this->conf[ $section ] ) ) ?
			$this->conf[ $section ][ $key ] : $default;
	}

	protected function init_logging() {
		$handler = new \Monolog\Handler\ErrorLogHandler();
		$handler->setFormatter(
			new \Monolog\Formatter\LineFormatter(
				"[%level_name%] %channel%: %message% %context% %extra%\n" ) );
		$this->log->setHandlers( [ $handler ] );
		$this->log->pushProcessor( new \Monolog\Processor\WebProcessor() );
	}

	protected function load_config() {
		$this->conf = parse_ini_file( __DIR__ . '/../client.ini', true );
	}

	protected function handle_exception( \Exception $ex ) {
		$this->response->status->setCode( 500 );
		$this->log->addError( $ex->getMessage(), [ 'exception' => $ex ] );
		$view = $this->views->get( 'exception' );
		$this->display_page( __( 'Oops..' ), $view );
	}

	protected function init_router() {
		$this->router->addGet( 'request', '/' );
		$this->router->addPost( 'request#submission', '/' );
		$this->router->addGet( 'confirm', '/confirm' );
		$this->router->addPost( 'confirm#submission', '/confirm' );
		$this->router->addGet( 'logout', '/logout' );

		// We do not require a user to be logged in on the following
		// paths
		$this->router->addPost( 'ping', '/ping' )
			->addValues( [ '_skip_login_check' => true ] );

		$this->router->addGet( 'auth', '/auth' )
			->addValues( [ '_skip_login_check' => true ] );
	}

	protected function flash_message( $msg, $level ) {
		$messages = $this->session->getFlashNext( 'messages', [] );
		if ( ! array_key_exists( $level, $messages ) ) {
			$messages[ $level ] = [];
		}
		$messages[ $level ][] = $msg;
		$this->session->setFlash( 'messages', $messages );
	}

	protected function flash_info( $msg ) {
		$this->flash_message( $msg, Message_Area::INFO );
	}

	protected function flash_error( $msg ) {
		$this->flash_message( $msg, Message_Area::ERROR );
	}

	protected function display_page( $title, $contents ) {
		$view = $this->views->get( 'page' );

		foreach ( $this->session->getFlash( 'messages', [] ) as $level => $msgs ) {
			foreach ( $msgs as $msg ) {
				$this->messages->add_message( $level, $msg );
			}
		}

		$view->set( 'messages', (string) $this->messages );
		$view->set( 'title', $title );
		$view->set( 'contents', $contents );
		$view->set( 'user', $this->user );

		$this->response->content->set( $view );
	}

	protected function new_client() {
		$url = $this->get_conf( 'api', 'url' );
		$key = $this->get_conf( 'api', 'key' );
		$secret = $this->get_conf( 'api', 'secret' );

		if ( ! $url ) {
			throw new \RuntimeException( 'No API URL in config.ini' );
		}
		if ( ! $key ) {
			throw new \RuntimeException( 'No API key in config.ini' );
		}
		if ( ! $secret ) {
			throw new \RuntimeException( 'No API secret in config.ini' );
		}

		return new VaultClient( $url, $key, $secret );
	}

	protected function get_request_form() {
		$form = $this->views->get( 'request-form' );
		$form->set( 'form_token',
		            $this->root_session->getCsrfToken()->getValue() );
		$form->set( 'req_email', $this->user->email );
		return $form;
	}

	protected function display_login_page() {
		$wpcc_state = base64_encode( openssl_random_pseudo_bytes( 16 ) );
		$this->session->setFlash( 'wpcc_state', $wpcc_state );

		$url_to = $this->get_conf( 'oauth', 'authenticate_url' )
			. '?'
			. http_build_query(
				[
					'response_type' => 'code',
					'client_id' => $this->get_conf( 'oauth',
					                                'client_id' ),
					'state' => $wpcc_state,
					'redirect_uri' => $this->get_conf( 'oauth',
					                                   'redirect_url' ),
				] );

		$this->display_page( __( 'Vault log in' ),
		                     '<a id="login-button" href="' . $url_to . '"><img src="//s0.wp.com/i/wpcc-button.png" width="231"></a>' );
	}

	protected function log_in( User $user ) {
		$this->root_session->regenerateId();
		$this->log->addInfo( $user->email . ' logged in' );
		$this->session->set( 'user', $user );
		$this->user = $user;
	}

	protected function handle_auth() {

		// No matter what, we always redirect to the request page
		$this->response->redirect->to( $this->router->generate( 'request' ) );

		$code = $this->request->query->get( 'code' );
		if ( ! $code ) {
			$this->flash_error( __( 'You must login to access this system.' ) );
			return;
		}

		if ( ! hash_equals( $this->session->getFlash( 'wpcc_state' ), $this->request->query->get( 'state' ) ) ) {
			$this->flash_error( __( 'Invalid request.' ) );
			return;
		}

		$postfields = [
			'client_id' => $this->get_conf( 'oauth', 'client_id' ),
			'redirect_uri' => $this->get_conf( 'oauth', 'redirect_url' ),
			'client_secret' => $this->get_conf( 'oauth', 'client_secret' ),
			'code' => $code,
			'grant_type' => 'authorization_code',
		];

		$ch = curl_init( $this->get_conf( 'oauth', 'request_token_url' ) );
		curl_setopt( $ch, CURLOPT_POST, true );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $postfields );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		$auth = curl_exec( $ch );

		$secret = json_decode( $auth, true );

		if ( empty( $secret['access_token'] ) ) {
			throw new \RuntimeException( 'No access token was returned from OAauth' );
		}

		$ch = curl_init( 'https://public-api.wordpress.com/rest/v1/me/' );
		curl_setopt( $ch, CURLOPT_HTTPHEADER,
		             [ 'Authorization: Bearer ' . $secret['access_token'] ] );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		$res = curl_exec( $ch );
		$user = json_decode( $res, true );

		if ( empty( $user['verified'] ) ) {
			$this->flash_error( __( 'You need to verify your e-mail in WordPress.com before logging in here.' ) );
			return;
		}

		$this->log_in( new User( $user['ID'],
		                         $user['email'],
		                         $user['display_name'] ) );
	}

	protected function check_user() {
		$this->user = $this->session->get( 'user' );
		if ( ! $this->user ) {
			$this->display_login_page();
			throw new ForbiddenException();
		}
	}

	protected function handle_request_form() {
		$this->display_page( __( 'Send a Vault Request' ),
		                     $this->get_request_form() );
	}

	protected function check_form_token() {
		$form_token = $this->request->post->get( 'form_token' );
		if ( ! $this->root_session->getCsrfToken()->isValid( $form_token ) ) {
			throw new \RuntimeException( 'Invalid form token. CSRF attempt?' );
		}
	}

	protected function handle_request_form_submission() {
		$this->check_form_token();

		$user_email = $this->request->post->get( 'user-email' );
		$instructions = $this->request->post->get( 'instructions' );

		$errors = [];

		if ( empty( $user_email ) || ! Valid::email( $user_email ) ) {
			$errors['user_email'] = 'Input a valid e-mail address.';
		}

		if ( $errors ) {
			$form = $this->get_request_form();

			$form->set( 'user_email', $user_email );
			$form->set( 'instructions', $instructions );

			$form->set( 'user_email_error',
			            ( isset( $errors['user_email'] ) ?
			              $errors['user_email'] : null ) );

			$this->display_page( __( 'Send a Vault Request' ), $form );
			return;
		}

		$form = $this->views->get( 'confirm' );
		$form->set( 'form_token',
		            $this->root_session->getCsrfToken()->getValue() );

		$form->set( 'action', $this->router->generate( 'confirm#submission' ) );
		$form->set( 'req_email', $this->user->email );
		$form->set( 'user_email', $user_email );
		$form->set( 'instructions', $instructions );

		$this->display_page( __( 'Request Confirmation' ), $form );
	}

	protected function handle_confirm_submission() {
		$this->check_form_token();

		$user_email = $this->request->post->get( 'user_email' );
		$instructions = $this->request->post->get( 'instructions' );

		$client = $this->new_client();

		$res = $client->add_request( $user_email, $instructions,
		                             $this->user->email );

		$this->flash_info( __( '<p>The request was sent.</p><p>You will receive an e-mail when the user submits the information requested.</p>' ) );

		$this->response->redirect->afterPost(
			$this->router->generate( 'request' ) );
	}

	protected function handle_ping_submission( array $args ) {

		$body = $this->views->get( 'email-unlock' );
		$body->set( 'reqid', $args['reqid'] );
		$body->set( 'unlock_url', $args['unlock_url'] );
		$body->set( 'unlock_key', $args['unlock_key'] );

		$mailer = new Mailer( $this->conf, $this->log );
		$mailer->addAddress( $args['app_data'] );
		// @codingStandardsIgnoreStart
		$mailer->Subject = 'The information you requested is now available';
		$mailer->Body = (string) $body;
		// @codingStandardsIgnoreEnd

		$mailer->send();

	}

	protected function handle_ping() {
		$subject = $this->request->post->get( 's' );
		$payload = $this->request->post->get( 'p' );
		$mac = $this->request->post->get( 'm' );

		$known_mac = hash_hmac( 'sha1',  "$subject $payload",
		                        $this->get_conf( 'api', 'vault_secret' ),
		                        true );
		if ( ! hash_equals( $known_mac, $mac ) ) {
			throw new NotFoundException( 'Could not authenticate ping' );
		}

		$args = json_decode( $payload, true );

		switch ( $subject ) {
			case 'submission':
				$this->handle_ping_submission( $args );
				break;

			default:
				throw new NotFoundException( "Unsupported ping subject '$subject'" );
		}
	}

	protected function handle_logout() {
		$this->session->set( 'user', null );
		$this->root_session->regenerateId();
		$this->flash_info( __( 'You have successfully logged out.' ) );
		$this->response->redirect->to( $this->router->generate( 'request' ) );
	}

	protected function handle_request() {
		$path = $this->request->url->get( PHP_URL_PATH );
		$route = $this->router->match( $path, $this->request->server->get() );
		if ( ! $route ) {
			throw new NotFoundException( $path );
		}

		if ( empty( $route->params['_skip_login_check'] ) ) {
			$this->check_user();
		}

		switch ( $route->params['action'] ) {

			case 'auth':
				$this->handle_auth();
				break;

			case 'request':
				$this->handle_request_form();
				break;

			case 'request#submission':
				$this->handle_request_form_submission();
				break;

			case 'confirm':
				$this->handle_confirm();
				break;

			case 'confirm#submission':
				$this->handle_confirm_submission();
				break;

			case 'ping':
				$this->handle_ping();
				break;

			case 'logout':
				$this->handle_logout();
				break;

			default:
				throw new \RuntimeException( 'Invalid action: '
				                             . $route->params['action'] );
		}
	}

	protected function handle_not_found() {
		$this->response->status->setCode( 404 );
		$this->session->setFlashNow( 'messages', [] );
		$this->display_page( __( 'Page not found' ),
		                     __( "Sorry, the page you were looking for doesn't exist or has been moved." ) );
	}

	protected function handle_forbidden() {
		$this->response->status->setCode( 403 );
		$this->session->setFlashNow( 'messages', [] );
	}

	protected function prepare_response() {
		$type = $this->response->content->getType();
		$charset = $this->response->content->getCharset();

		$this->response->headers->set( 'Content-Type',
									   "$type; charset=\"$charset\"" );

		if ( $this->user ) {
			$this->response->cache->setPrivate();
		}
	}

	protected function send_response() {
		header( $this->response->status->get(),
		        true,
		        $this->response->status->getCode() );

		foreach ( $this->response->headers->get() as $label => $value ) {
			header( "{$label}: {$value}" );
		}

		foreach ( $this->response->cookies->get() as $name => $cookie ) {
			setcookie( $name,
			           $cookie['value'],
			           $cookie['expire'],
			           $cookie['path'],
			           $cookie['domain'],
			           $cookie['secure'],
			           $cookie['httponly'] );
		}

		echo $this->response->content->get();
	}

	public function run() {
		try {
			$this->init_logging();
			$this->load_config();
			$this->init_router();
			$this->handle_request();
		} catch ( NotFoundException $ex ) {
			$this->log->addNotice( 'Not found (' . $ex->getMessage() . ')' );
			$this->handle_not_found();
		} catch ( ForbiddenException $ex ) {
			$this->log->addNotice( 'Forbidden' );
			$this->handle_forbidden();
		} catch ( \Exception $ex ) {
			$this->handle_exception( $ex );
		}

		$this->prepare_response();
		$this->send_response();
	}
}
