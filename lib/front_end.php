<?php

namespace Vault;

class Front_End_App extends Web_App {

	protected $views;
	protected $session;
	protected $script_config = [];
	protected $script_files = [];

	public function __construct($name, array $globals = NULL ) {
		parent::__construct($name, $globals);
		$this->views = new View_Registry();

		// Initialize our session object.
		$session_factory = new \Aura\Session\SessionFactory;
		$session = $session_factory->newInstance(
			$this->request->cookies->get() );
		$this->session = $session->getSegment( 'Vault' );
	}

	public function init_router() {
		$this->router
			->addTokens([
				            'reqid'     => '\d+',
			            ]);

		$this->router->addGet( 'request.reqid.input',
		                       '/request/{reqid}/input' );

		$this->router->addPost( 'request.reqid.input#submission',
		                        '/request/{reqid}/input' );

		$this->router->addGet( 'request.reqid.thank-you',
		                       '/request/{reqid}/thank-you' );
	}

	protected function display_page( $title, $contents ) {
		$view = $this->views->get('page');
		$view->set('title', $title);
		$view->set('contents', $contents);

		$scripts = '';
		if ( $this->script_config ) {
			// NB: script tags broken apart to avoid problems with
			// code editors. Please, do not join them!
			$scripts .= "<sc" . "ript>Vault.config = " . json_encode($this->script_config) . ";</scri" ."pt>\n";
		}
		foreach ( $this->script_files as $url ) {
			$scripts .= "<sc" . "ript src=\"$url\"></scri" ."pt>\n";
		}
		$view->set('scripts', $scripts);

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
		                    $this->service->get_request_mac( $request ) ) ) {
			throw new NotFoundException( 'Invalid MAC' );
		}

		$view = $this->views->get( 'input-form' );

		$view->set( 'reqid', $reqid );
		$view->set( 'action',
		            $this->router->generate( 'request.reqid.input#submission',
		                                       [
			                                       'reqid' => $request->reqid,
		                                       ] ) );
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
		                    $this->service->get_request_mac( $request ) ) ) {
			throw new NotFoundException( 'Invalid MAC' );
		}

		$res = $this->service->register_secret(
			$request,
			$this->request->post->get( 'secret' ) );

		// Add a flash flag so that we can control form submission in
		// the "thank you" page.
		$this->session->setFlash( 'reqid', $request->reqid );

		$this->response->redirect->afterPost(
			$this->router->generate( 'request.reqid.thank-you',
			                         [
				                         'reqid' => $request->reqid,
			                         ] ) );
	}

	protected function handle_request_input_thank_you( $reqid ) {
		if ( $this->session->getFlash( 'reqid' ) != $reqid ) {
			throw new NotFoundException();
		}

		$this->display_page( 'Thank you!',
		                     $this->views->get( 'input-thank-you' ));
	}

	protected function handle_request( \Aura\Router\Route $route ) {

		switch ( $route->params['action'] ) {

		case 'request.reqid.input':
			$this->handle_input_request( $route->params[ 'reqid' ] );
			break;

		case 'request.reqid.input#submission':
			$this->handle_input_request_submission( $route->params[ 'reqid' ] );
			break;

		case 'request.reqid.thank-you':
			$this->handle_request_input_thank_you( $route->params[ 'reqid' ] );
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
