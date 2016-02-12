<?php

namespace Vault;

abstract class App {

	protected $request;
	protected $response;
	protected $log;
	protected $conf;

	abstract protected function init_basic_logging();
	abstract protected function handle_exception( \Exception $ex );

	public function __construct( array $globals = NULL ) {
		if ( ! $globals )
			$globals = $GLOBALS;
		$web_factory = new \Aura\Web\WebFactory( $globals );
		$this->request = $web_factory->newRequest();
		$this->response = $web_factory->newResponse();

		$this->log = new \Monolog\Logger('app');
	}

	protected function load_config() {
		$ini = VAULT_ROOT . '/config.ini';
		if ( file_exists( $ini ) ) {
			$this->conf = parse_ini_file( $ini, TRUE );
		} else {
			$this->conf = [];
		}
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
		echo json_encode($this->response->content->get());
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
			$this->init_basic_logging();
			$this->load_config();
		}
		catch ( \Exception $ex )
		{
			$this->log->addCritical( $ex );
			$this->handle_exception( $ex );
		}

		$this->send_response();
	}
}
