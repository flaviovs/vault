<?php

namespace Vault;

abstract class Web_App extends Vault_App {

	protected $request;
	protected $response;

	public function __construct($name, array $globals = NULL ) {
		parent::__construct($name);
		if ( ! $globals )
			$globals = $GLOBALS;
		$web_factory = new \Aura\Web\WebFactory( $globals );
		$this->request = $web_factory->newRequest();
		$this->response = $web_factory->newResponse();
	}

	protected function init_basic_logging() {
		$handler = new \Monolog\Handler\ErrorLogHandler();
		$handler->setFormatter(
			new \Monolog\Formatter\LineFormatter(
				"[%level_name%] %channel%: %message% %context% %extra%\n"));
		$this->log->setHandlers([$handler]);
		$this->log->pushProcessor(new \Monolog\Processor\WebProcessor());
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

	public function run() {
		try
		{
			$this->bootstrap();
		}
		catch ( \Exception $ex )
		{
			$this->handle_exception( $ex );
		}

		$this->send_response();

		return $this->response->status->getCode();
	}
}
