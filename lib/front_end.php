<?php

namespace Vault;

class Front_End_App extends Web_App {

	protected $session;
	protected $script_config = [];
	protected $script_files = [ '/script.js' ];

	public function __construct($name, array $globals = NULL ) {
		parent::__construct($name, $globals);

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

		$this->router->addGet( 'unlock.reqid.input',
		                       '/unlock/{reqid}/input' );

		$this->router->addPost( 'unlock.reqid.input#submission',
		                        '/unlock/{reqid}/input' );

		$this->router->addGet( 'unlock.reqid.view',
		                       '/unlock/{reqid}/view' );
	}

	protected function display_page( $title, $contents ) {
		$view = $this->views->get('page');
		$view->set('title', $title);
		$view->set('contents', $contents);

		// NB: script tags broken apart to avoid problems with
		// code editors. Please, do not join them!
		$scripts = "<sc" . "ript>var Vault = {'config': " . json_encode($this->script_config) . "};</scri" ."pt>\n";

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
		$view->set( 'instructions',
		            strip_tags( $request->instructions,
		                        Request::INSTRUCTIONS_ALLOWED_TAGS ) );
		$view->set( 'mac', $mac );

		$this->display_page( __( "We need your information" ), $view );
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

		$this->display_page( __( 'Thank you!' ),
		                     $this->views->get( 'input-thank-you' ));
	}

	protected function handle_unlock_input( $reqid ) {

		$request = $this->load_request( $reqid );

		$mac = $this->request->query->get('m');
		if ( ! $mac ) {
			throw new NotFoundException( 'No MAC' );
		}

		$view = $this->views->get( 'unlock-form' );

		$view->set( 'reqid', $reqid );
		$view->set( 'action',
		            $this->router->generate( 'unlock.reqid.input#submission',
		                                       [
			                                       'reqid' => $request->reqid,
		                                       ] ) );
		$view->set( 'mac', $mac );

		$this->display_page( __( "Input the unlock key" ), $view );
	}

	protected function handle_unlock_input_submission( $reqid ) {
		$request = $this->load_request( $reqid );

		$mac = $this->request->post->get('m');
		if ( ! $mac ) {
			throw new NotFoundException( 'No MAC' );
		}

		$unlock_key = $this->request->post->get('key');

		if ( ! hash_equals( base64_decode($mac),
		                    $this->service->get_request_mac( $request, $unlock_key ) ) ) {
			throw new NotFoundException( 'Invalid URL MAC' );
		}

		try {
			$secret = $this->repo->find_secret( $reqid );
		} catch ( ValtDataException $ex ) {
			throw new NotFoundException( 'Secret not found' );
		}

		if ( ! $secret->secret ) {
			throw new NotFoundException( 'Secret already unlocked' );
		}

		if ( ! $secret->is_mac_valid( $unlock_key ) ) {
			throw new NotFoundException( 'Invalid secret MAC' );
		}

		$this->session->setFlash( 'reqid', $request->reqid );
		$this->session->setFlash( 'view_expire',
		                          time() + $this->conf->get( 'general',
		                                                     'view_time', 60) );
		$this->session->setFlash( 'plaintext',
		                          $this->service->unlock_secret( $secret,
		                                                         $unlock_key ) );

		$this->response->redirect->afterPost(
			$this->router->generate( 'unlock.reqid.view',
			                         [
				                         'reqid' => $request->reqid,
			                         ] ) );
	}

	protected function handle_unlock_view( $reqid ) {
		if ( $this->session->getFlash( 'reqid' ) != $reqid ) {
			throw new NotFoundException();
		}

		$plaintext = $this->session->getFlash( 'plaintext' );

		$remaining_time = $this->session->getFlash( 'view_expire' ) - time();
		if ( $remaining_time <= 0 ) {
			throw new NotFoundException();
		}

		$this->session->keepFlash();
		// Refresh to the same URL after the remaining time is
		// over (which then will fatally return a 404).
		$remaining_time++;
		$this->response->headers->set( 'Refresh',
		                               $remaining_time. "; URL=" . $this->request->url->get());
		$this->script_config['refresh'] = $remaining_time;

		$view = $this->views->get( 'unlock-view' );
		$view->set( 'plaintext', htmlspecialchars( $plaintext ) );

		$this->display_page( __( 'Unlocked' ), $view );
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

		case 'unlock.reqid.input':
			$this->handle_unlock_input( $route->params[ 'reqid' ] );
			break;

		case 'unlock.reqid.input#submission':
			$this->handle_unlock_input_submission( $route->params[ 'reqid' ] );
			break;

		case 'unlock.reqid.view':
			$this->handle_unlock_view( $route->params[ 'reqid' ] );
			break;

		default:
			throw new \RuntimeException( "Invalid action: "
			                             . $route->params[ 'action' ] );
		}
	}


	public function handle_not_found( $message ) {
		parent::handle_not_found( $message );
		$this->display_page( __( 'Page not found' ),
		                     __( "Sorry, the page you were looking for doesn't exist or has been moved." ) );
	}
}
