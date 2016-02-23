<?php

namespace Vault;

class REST_App extends Web_App {

	public function __construct( $name, array $globals = NULL ) {
		parent::__construct( $name, $globals );
		$this->response->content->setType( 'application/json' );
	}

	protected function handle_exception( \Exception $ex ) {
		parent::handle_exception( $ex );
		$this->response->content->set(
			[ 'message' => 'It was not possible to process the request.' ] );
	}

	protected function handle_not_found( $msg ) {
		parent::handle_not_found( $msg );
		$this->response->content->set(
			[ 'message' => 'Unknown request.' ] );
	}

	protected function send_response_contents() {
		echo json_encode($this->response->content->get());
	}
}
