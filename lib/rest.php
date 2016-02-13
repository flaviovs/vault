<?php

namespace Vault;

class REST_App extends Web_App {

	public function __construct( array $globals = NULL ) {
		parent::__construct( $globals );
		$this->response->content->setType( 'application/json' );
	}

	protected function handle_exception( \Exception $ex ) {
		parent::handle_exception( $ex );
		$this->response->content->set(
			[ 'message' => 'It was not possible to process the request.' ] );
	}

	protected function send_response_contents() {
		echo json_encode($this->response->content->get());
	}
}
