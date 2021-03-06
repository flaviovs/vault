<?php
/**
 * Defines the main Vault client app class
 */

namespace Vault;

/**
 * Exception thrown on unauthorized HTTP requests.
 *
 * By default, the app will issue a "401 Unauthorized" when this
 * exception is caught.
 */
class ForbiddenException extends \Exception {}

/**
 * The client app class.
 */
class ClientApp {
	/**
	 * The default configuration.
	 *
	 * @var array
	 */
	const DEFAULT_CONFIG = [];

	/**
	 * The request object.
	 *
	 * @var \Aura\Web\Request
	 */
	protected $request;

	/**
	 * The response object.
	 *
	 * @var \Aura\Web\Response
	 */
	protected $response;

	/**
	 * The router object.
	 *
	 * @var \Aura\Router\Router
	 */
	protected $router;

	/**
	 * The config object.
	 *
	 * @var \UConfig\Config
	 */
	protected $conf;

	/**
	 * The root (main) Aura session.
	 *
	 * @var \Aura\Session\Session
	 */
	protected $root_session;

	/**
	 * This app session segment.
	 *
	 * We use a segment to avoid future clash with other tools that
	 * might be integrated with the app.
	 *
	 * @var \Aura\Session\Segment
	 */
	protected $session;

	/**
	 * The logger object.
	 *
	 * @var \Monolog\Logger
	 */
	protected $log;

	/**
	 * The view registry.
	 *
	 * @var \UView\Registry
	 */
	protected $views;

	/**
	 * The message area object.
	 *
	 * @var Message_Area
	 */
	protected $messages;

	/**
	 * The current, logged-in, user.
	 *
	 * @var User
	 */
	protected $user;

	/**
	 * Constructs the object.
	 *
	 * @param string $name The app name.
	 */
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

		$this->conf = new \UConfig\Config( static::DEFAULT_CONFIG );
		$this->conf->addHandler( new \UConfig\INIFileHandler( VAULT_ROOT . '/client.ini' ) );

		$session_factory = new \Aura\Session\SessionFactory;
		$this->root_session = $session_factory->newInstance(
			$this->request->cookies->get() );
		$this->session = $this->root_session->getSegment( __CLASS__ );

		$this->log = new \Monolog\Logger( $name );

		$this->views = new \UView\Registry( VAULT_ROOT . '/view/client' );

		$this->messages = new Message_Area();

	}

	/**
	 * Initializes logging.
	 */
	protected function init_logging() {
		$handler = new \Monolog\Handler\ErrorLogHandler();
		$handler->setFormatter(
			new \Monolog\Formatter\LineFormatter(
				"[%level_name%] %channel%: %message% %context% %extra%\n" ) );
		$this->log->setHandlers( [ $handler ] );
		$this->log->pushProcessor( new \Monolog\Processor\WebProcessor() );
	}

	/**
	 * Handles uncaught exceptions.
	 *
	 * @param \Exception $ex The uncaught exception.
	 */
	protected function handle_exception( \Exception $ex ) {
		$this->response->status->setCode( 500 );
		$this->log->addError( $ex->getMessage(), [ 'exception' => $ex ] );
		$this->display_page( __( 'Oops..' ), $this->views->get( 'exception' ) );
	}

	/**
	 * Initializes the router object.
	 */
	protected function init_router() {
		$this->router->addGet( 'request', '/' );
		$this->router->addPost( 'request#submission', '/' );
		$this->router->addGet( 'request.done', '/done' );
		$this->router->addGet( 'confirm', '/confirm' );
		$this->router->addPost( 'confirm#submission', '/confirm' );
		$this->router->addGet( 'logout', '/logout' );

		// We do not require a user to be logged in on the following
		// paths.
		$this->router->addPost( 'ping', '/ping' )
			->addValues( [ '_skip_login_check' => true ] );

		$this->router->addGet( 'auth', '/auth' )
			->addValues( [ '_skip_login_check' => true ] );
	}

	/**
	 * Add a flash message.
	 *
	 * Flash messages are feedback messages that should be displayed in the next request.
	 *
	 * @param string $msg   The message to flash.
	 * @param string $level The message level. Use either
	 *   Message_Area::INFO or Message_Area::ERROR, for informational
	 *   or error messages, respectively.
	 */
	protected function flash_message( $msg, $level ) {
		$messages = $this->session->getFlashNext( 'messages', [] );
		if ( ! array_key_exists( $level, $messages ) ) {
			$messages[ $level ] = [];
		}
		$messages[ $level ][] = $msg;
		$this->session->setFlash( 'messages', $messages );
	}

	/**
	 * Flash an informational message.
	 *
	 * @param string $msg The message to flash.
	 */
	protected function flash_info( $msg ) {
		$this->flash_message( $msg, Message_Area::INFO );
	}

	/**
	 * Flash an error message.
	 *
	 * @param string $msg The message to flash.
	 */
	protected function flash_error( $msg ) {
		$this->flash_message( $msg, Message_Area::ERROR );
	}


	/**
	 * Display page contents.
	 *
	 * FIXME: this method is badly named -- it actually *sets up* the
	 * response object with proper contents to be displayed as a
	 * "page".
	 *
	 * @param string $title The page title.
	 * @param mixed  $contents The page contents.
	 */
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

	/**
	 * Creates a properly configured client object.
	 *
	 * @return VaultClient The new client object.
	 *
	 * @throws \RuntimeException if the client cannot the be created.
	 */
	protected function new_client() {
		try {
			$url = $this->conf->get( 'api', 'url' );
		} catch ( \UConfig\Exception $ex ) {
			throw new \RuntimeException( 'No API URL in client.ini' );
		}

		try {
			$key = $this->conf->get( 'api', 'key' );
		} catch ( \UConfig\Exception $ex ) {
			throw new \RuntimeException( 'No API key in client.ini' );
		}

		try {
			$secret = $this->conf->get( 'api', 'secret' );
		} catch ( \UConfig\Exception $ex ) {
			throw new \RuntimeException( 'No API secret in client.ini' );
		}

		return new VaultClient( $url, $key, $secret );
	}

	/**
	 * Return the request form view.
	 *
	 * @return \UView\View The request form view.
	 */
	protected function get_request_form() {
		$form = $this->views->get( 'request-form' );
		$form->set( 'form_token',
		            $this->root_session->getCsrfToken()->getValue() );
		$form->set( 'req_email', $this->user->email );
		return $form;
	}

	/**
	 * Displays the login page.
	 *
	 * Displays (actually sets up response contents) the login page.
	 */
	protected function display_login_page() {
		$wpcc_state = base64_encode( openssl_random_pseudo_bytes( 16 ) );
		$this->session->setFlash( 'wpcc_state', $wpcc_state );

		$url_to = $this->conf->get( 'oauth', 'authenticate_url' )
			. '?'
			. http_build_query(
				[
					'response_type' => 'code',
					'client_id' => $this->conf->get( 'oauth',
					                                 'client_id' ),
					'state' => $wpcc_state,
					'redirect_uri' => $this->conf->get( 'oauth',
					                                    'redirect_url' ),
				] );

		$this->display_page( __( 'Vault log in' ),
		                     '<a id="login-button" href="' . $url_to . '"><img src="//s0.wp.com/i/wpcc-button.png" width="231"></a>' );
	}

	/**
	 * Logs in an user.
	 *
	 * @param User $user The user object to log in.
	 */
	protected function log_in( User $user ) {
		$this->root_session->regenerateId();
		$this->log->addInfo( $user->email . ' logged in' );
		$this->session->set( 'user', $user );
		$this->user = $user;
	}

	/**
	 * Handles the OAuth return response.
	 *
	 * @throws \RuntimeException if the response is not valid.
	 */
	protected function handle_auth() {

		// No matter what, we always redirect to the request page.
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
			'client_id' => $this->conf->get( 'oauth', 'client_id' ),
			'redirect_uri' => $this->conf->get( 'oauth', 'redirect_url' ),
			'client_secret' => $this->conf->get( 'oauth', 'client_secret' ),
			'code' => $code,
			'grant_type' => 'authorization_code',
		];

		$ch = curl_init( $this->conf->get( 'oauth', 'request_token_url' ) );
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

	/**
	 * Checks that there's a user currently logged in.
	 *
	 * @throws ForbiddenException if no user is logged in.
	 */
	protected function check_user() {
		$this->user = $this->session->get( 'user' );
		if ( ! $this->user ) {
			$this->display_login_page();
			throw new ForbiddenException();
		}
	}

	/**
	 * Handles the request form.
	 */
	protected function handle_request_form() {
		$this->display_page( __( 'Send a Vault Request' ),
		                     $this->get_request_form() );
	}

	/**
	 * Checks CSRF token in the current (POST) request.
	 *
	 * @throws \RuntimeException if the CSRF token is not present or
	 * invalid.
	 */
	protected function check_form_token() {
		$form_token = $this->request->post->get( 'form_token' );
		if ( ! $this->root_session->getCsrfToken()->isValid( $form_token ) ) {
			throw new \RuntimeException( 'Invalid form token. CSRF attempt?' );
		}
	}

	/**
	 * Handles submission of the request form.
	 */
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

	/**
	 * Handles submission of the confirmation form.
	 */
	protected function handle_confirm_submission() {
		$this->check_form_token();

		$user_email = $this->request->post->get( 'user_email' );
		$instructions = $this->request->post->get( 'instructions' );

		$client = $this->new_client();

		$res = $client->add_request( $user_email, $instructions,
		                             $this->user->email );

		$this->flash_info( __( '<p>The request has been sent.</p><p>You will receive an e-mail when the user submits the requested information.</p><p><strong>Notice</strong>: check your junk/spam folder for e-mails from <i>Vault</i>. You may want to add the notification address to your spam/junk configuration, so that future e-mails go straight to your inbox. <b>Remember to also warn the user about this.</b></p>' ) );

		$this->response->redirect->afterPost(
			$this->router->generate( 'request.done' ) );
	}

	/**
	 * Handles submission ping requests.
	 *
	 * @param array $args Ping argument.
	 */
	protected function handle_ping_submission( array $args ) {

		$body = $this->views->get( 'email-unlock' );
		$body->set( 'reqid', $args['reqid'] );
		$body->set( 'unlock_url', $args['unlock_url'] );
		$body->set( 'unlock_key', $args['unlock_key'] );

		$mailer_factory = new Mailer_Factory( $this->conf, $this->log );

		$mailer = $mailer_factory->new_mailer();
		$mailer->addAddress( $args['app_data'] );
		// @codingStandardsIgnoreStart
		$mailer->Subject = 'The information you requested is now available';
		$mailer->Body = (string) $body;
		// @codingStandardsIgnoreEnd

		$mailer->send();

	}

	/**
	 * Handles and dispatches ping requests.
	 *
	 * @throws NotFoundException if the request seems not valid or
	 * incomplete.
	 */
	protected function handle_ping() {
		$subject = $this->request->post->get( 's' );
		$payload = $this->request->post->get( 'p' );
		$mac = $this->request->post->get( 'm' );

		$known_mac = hash_hmac( 'sha1', "$subject $payload",
		                        $this->conf->get( 'api', 'vault_secret' ),
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

	/**
	 * Handles user logout.
	 */
	protected function handle_logout() {
		$this->session->set( 'user', null );
		$this->root_session->regenerateId();
		$this->flash_info( __( 'You have successfully logged out.' ) );
		$this->response->redirect->to( $this->router->generate( 'request' ) );
	}

	/**
	 * Front-controller request dispatcher.
	 *
	 * The method will look for an authenticated user by default,
	 * unless the "_skip_login_check" parameter is empty in the route.
	 *
	 * @throws NotFoundException if the requested route is not found.
	 * @throws \RuntimeException if any other internal problem is found.
	 */
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

			case 'request.done':
				$this->display_page( __( 'Done' ),
				                     $this->views->get( 'request-done' ) );
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

	/**
	 * Sets up a HTTP "Not found" response.
	 */
	protected function handle_not_found() {
		$this->response->status->setCode( 404 );
		$this->session->setFlashNow( 'messages', [] );
		$this->display_page( __( 'Page not found' ),
		                     __( "Sorry, the page you were looking for doesn't exist or has been moved." ) );
	}

	/**
	 * Sets up a HTTP "Forbidden" response.
	 */
	protected function handle_forbidden() {
		$this->response->status->setCode( 403 );
		$this->session->setFlashNow( 'messages', [] );
	}

	/**
	 * Sets up response metadata.
	 */
	protected function prepare_response() {
		$type = $this->response->content->getType();
		$charset = $this->response->content->getCharset();

		$this->response->headers->set( 'Content-Type',
									   "$type; charset=\"$charset\"" );

		if ( $this->user ) {
			$this->response->cache->setPrivate();
		}
	}

	/**
	 * Sends out the response object down the wire.
	 */
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

	/**
	 * Run the client application.
	 */
	public function run() {
		try {
			$this->init_logging();
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
