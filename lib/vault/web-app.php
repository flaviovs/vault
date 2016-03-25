<?php

namespace Vault;

class UnauthorizedException extends VaultException {}
class NotFoundException extends VaultException {}

abstract class Web_App extends Vault_App {

	protected $request;
	protected $response;
	protected $router;

	abstract protected function init_router();
	abstract protected function handle_request( \Aura\Router\Route $route );

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

	protected function init_basic_logging() {
		$handler = new \Monolog\Handler\ErrorLogHandler();
		$handler->setFormatter(
			new \Monolog\Formatter\LineFormatter(
				"[%level_name%] %channel%: %message% %context% %extra%\n" ) );
		$this->log->setHandlers( [ $handler ] );
		$this->log->pushProcessor( new \Monolog\Processor\WebProcessor() );
	}

	protected function handle_exception( \Exception $ex ) {
		$this->log->addCritical( $ex );
		$this->response->status->set( 500 );
	}

	protected function send_response_status() {
		header( $this->response->status->get(),
		        true,
		        $this->response->status->getCode() );
	}

	protected function send_response_headers() {
		foreach ( $this->response->headers->get() as $label => $value ) {
			header( "{$label}: {$value}" );
		}
	}

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

	protected function send_response_contents() {
		echo $this->response->content->get();
	}

	protected function send_response() {
		$this->send_response_status();
		$this->send_response_headers();
		$this->send_response_cookies();
		$this->send_response_contents();
	}

	protected function prepare_response() {
		$type = $this->response->content->getType();
		$charset = $this->response->content->getCharset();
		$this->response->headers->set( 'Content-Type',
									   "$type; charset=\"$charset\"" );
	}

	protected function dispatch_request() {
		$path = $this->request->url->get( PHP_URL_PATH );
		$route = $this->router->match( $path, $this->request->server->get() );
		if ( ! $route ) {
			throw new NotFoundException( $path );
		}

		$this->handle_request( $route );
	}

	protected function handle_unauthorized( $realm ) {
		$this->response->status->set( '401', 'Unauthorized', '1.1' );
		$this->response->headers->set( 'WWW-Authenticate',
		                               'Basic realm="' . $realm . '"' );
	}

	protected function handle_not_found( $message ) {
		$this->log->addNotice( "Not found ($message)" );
		$this->response->status->set( '404', 'Not Found', '1.1' );
	}

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
