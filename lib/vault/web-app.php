<?php
/**
 * Contains an abstract base class for HTTP based apps.
 */

namespace Vault;

/**
 * Exception raised when an unauthorized access is attempted.
 */
class UnauthorizedException extends VaultException {}

/**
 * Exception raised when a resource is not found.
 */
class NotFoundException extends VaultException {}

/**
 * Abstract base class for HTTP based apps.
 */
abstract class Web_App extends Vault_App {

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
	 * @var \Aura\Route\Router
	 */
	protected $router;

	/**
	 * Initializes the internal router object.
	 */
	abstract protected function init_router();

	/**
	 * Handles a request route.
	 *
	 * @param \Aura\Router\Route $route The request route.
	 *
	 * @throws \RuntimeException if the route request is not valid.
	 */
	abstract protected function handle_request( \Aura\Router\Route $route );

	/**
	 * Constructs the object.
	 *
	 * @param string $name    The app name.
	 * @param array  $globals Optional custom $GLOBALS array.
	 */
	public function __construct( $name, array $globals = null ) {
		parent::__construct( $name );
		if ( ! $globals ) {
			// Workaround '_SERVER' not present in $GLOBALS, unless
			// referenced before (see
			// https://bugs.php.net/bug.php?id=65223).
			$_SERVER;

			$globals = $GLOBALS;
		}
		$web_factory = new \Aura\Web\WebFactory( $globals );
		$this->request = $web_factory->newRequest();
		$this->response = $web_factory->newResponse();

		$this->response->content->setType( 'text/html' );
		$this->response->content->setCharset( 'utf-8' );

		$router_factory = new \Aura\Router\RouterFactory();
		$this->router = $router_factory->newInstance();
	}

	/**
	 * {@inheritdoc}
	 */
	protected function init_basic_logging() {

		$general_level = $this->conf->get( 'logging', 'general_level' );
		$audit_level = $this->conf->get( 'logging', 'audit_level' );

		// Setup general handler.
		$general_handler = new \Monolog\Handler\ErrorLogHandler(
			\Monolog\Handler\ErrorLogHandler::OPERATING_SYSTEM,
			$general_level );
		$general_handler->setFormatter(
			new \Monolog\Formatter\LineFormatter(
				"[%level_name%] %channel%: %message% %context% %extra%\n" ) );
		$this->log->setHandlers( [ $general_handler ] );

		// Setup audit handler.
		if ( $audit_level == $general_level ) {
			// Same level -- we can safely reuse the general handler
			// for audit.
			$audit_handler = $general_handler;
		} else {
			$audit_handler = new \Monolog\Handler\StreamHandler( 'php://stderr',
			                                                     $audit_level );
			$audit_handler->setFormatter( $general_handler->getFormatter() );
		}
		$this->audit->setHandlers( [ $audit_handler ] );

		$web_processor = new \Monolog\Processor\WebProcessor();
		$this->log->pushProcessor( $web_processor );
		$this->audit->pushProcessor( $web_processor );
	}

	/**
	 * Handles uncaught exceptions.
	 *
	 * @param \Exception $ex The uncaught exception.
	 */
	protected function handle_exception( \Exception $ex ) {
		$this->log->addCritical( $ex );
		$this->response->status->set( 500 );
	}

	/**
	 * Sends the response status code.
	 */
	protected function send_response_status() {
		header( $this->response->status->get(),
		        true,
		        $this->response->status->getCode() );
	}

	/**
	 * Sends the response headers.
	 */
	protected function send_response_headers() {
		foreach ( $this->response->headers->get() as $label => $value ) {
			header( "{$label}: {$value}" );
		}
	}

	/**
	 * Sends the response cookies.
	 */
	protected function send_response_cookies() {
		foreach ( $this->response->cookies->get() as $name => $cookie ) {
			setcookie( $name,
			           $cookie['value'],
			           $cookie['expire'],
			           $cookie['path'],
			           $cookie['domain'],
			           $cookie['secure'],
			           $cookie['httponly'] );
		}
	}

	/**
	 * Sends the response contents.
	 */
	protected function send_response_contents() {
		echo $this->response->content->get();
	}

	/**
	 * Sends the entire response.
	 *
	 * Actually calls send_response_{status,headers,cookies,contents}
	 * (in this order).
	 */
	protected function send_response() {
		$this->send_response_status();
		$this->send_response_headers();
		$this->send_response_cookies();
		$this->send_response_contents();
	}

	/**
	 * Prepares the response object prior to being sent to the browser.
	 */
	protected function prepare_response() {
		$type = $this->response->content->getType();
		$charset = $this->response->content->getCharset();
		$this->response->headers->set( 'Content-Type',
									   "$type; charset=\"$charset\"" );
	}

	/**
	 * Dispatch the request route matching the current HTTP request.
	 *
	 * @throws NotFoundException if the request path is not found.
	 */
	protected function dispatch_request() {
		$path = $this->request->url->get( PHP_URL_PATH );
		$route = $this->router->match( $path, $this->request->server->get() );
		if ( ! $route ) {
			throw new NotFoundException( $path );
		}

		$this->handle_request( $route );
	}

	/**
	 * Handles UnauthorizedException exceptions.
	 *
	 * @param string $realm The authorization realm (usualy the app name).
	 */
	protected function handle_unauthorized( $realm ) {
		$this->response->status->set( '401', 'Unauthorized', '1.1' );
		$this->response->headers->set( 'WWW-Authenticate',
		                               'Basic realm="' . $realm . '"' );
	}

	/**
	 * Handles "not found" requests
	 *
	 * @param string $message A message to be associated with this request.
	 */
	protected function handle_not_found( $message ) {
		$this->log->addNotice( "Not found ($message)" );
		$this->response->status->set( '404', 'Not Found', '1.1' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function run() {
		try {
			$this->bootstrap();
			$this->init_router();
			$this->dispatch_request();
		} catch ( UnauthorizedException $ex ) {
			$this->handle_unauthorized( $this->name );
		} catch ( NotFoundException $ex ) {
			$this->handle_not_found( $ex->getMessage() );
		} catch ( \Exception $ex ) {
			$this->handle_exception( $ex );
		}

		$this->prepare_response();
		$this->send_response();

		return $this->response->status->getCode();
	}
}
