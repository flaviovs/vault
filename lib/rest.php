<?php

namespace Vault;

class REST_App extends App {

	public function __construct( array $globals = NULL ) {
		parent::__construct( $globals );
		$this->response->content->setType( 'application/json' );
	}

	protected function handle_exception( \Exception $ex ) {
		$this->response->status->set( 500 );
		$this->response->content->set(
			[ 'message' => 'It was not possible to process the request.' ] );
	}
}
