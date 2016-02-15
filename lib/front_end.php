<?php

namespace Vault;

class Front_End_App extends Web_App {

	protected $views;

	public function __construct($name, array $globals = NULL ) {
		parent::__construct($name, $globals);
		$this->views = new View_Registry();
	}

	public function init_router() {
		$this->router
			->addTokens([
				            'reqid'     => '\d+',
			            ]);

		$this->router->addGet('input.request', '/input/{reqid}');
		$this->router->addPost('input.request.submit', '/input/{reqid}');
	}

	protected function display_page( $title, $contents ) {
		$view = $this->views->get('page');
		$view->set('title', $title);
		$view->set('contents', $contents);
		$this->response->content->set($view);
	}

	protected function load_request( $reqid ) {
		try {
			$request = $this->repo->find_request( $reqid );
		}
		catch ( VaultDataException $ex ) {
			throw new NotFoundException( $ex->getMessage() );
		}
		return $request;
	}

	protected function handle_input_request( $reqid ) {

		$request = $this->load_request( $reqid );

		$mac = $this->request->query->get('m');
		if ( ! $mac ) {
			throw new NotFoundException( 'No MAC' );
		}

		// When the user input the secret, we remove the input key. So
		// we can use it to know if there's a secret already, without
		// having to load the secret itself.
		if ( ! $request->input_key ) {
			throw new NotFoundException( 'No input key' );
		}

		if ( ! hash_equals( base64_decode($mac),
		                    $this->service->get_input_hash( $request ) ) ) {
			throw new NotFoundException( 'Invalid MAC' );
		}

		$view = $this->views->get( 'input-form' );

		$view->set( 'reqid', $reqid );
		$view->set( 'action', "/input/$reqid" );
		$view->set( 'mac', $mac );

		$this->display_page( "Input your credentials", $view );
	}

	protected function handle_input_request_submission( $reqid ) {
		$request = $this->load_request( $reqid );

		$mac = $this->request->post->get('m');
		if ( ! $mac ) {
			throw new NotFoundException( 'No MAC' );
		}

		// See comment in handle_input_request() above
		if ( ! $request->input_key ) {
			throw new NotFoundException( 'No input key' );
		}

		if ( ! hash_equals( base64_decode($mac),
		                    $this->service->get_input_hash( $request ) ) ) {
			throw new NotFoundException( 'Invalid MAC' );
		}

		print 'submit';
	}

	protected function handle_request( \Aura\Router\Route $route ) {

		switch ( $route->params['action'] ) {

		case 'input.request':
			$this->handle_input_request( $route->params[ 'reqid' ] );
			break;

		case 'input.request.submit':
			$this->handle_input_request_submission( $route->params[ 'reqid' ] );
			break;

		default:
			throw new \RuntimeException( "Invalid action: "
			                             . $route->params[ 'action' ] );
		}
	}


	public function handle_not_found( $message ) {
		parent::handle_not_found( $message );
		print "oops";
	}
}
