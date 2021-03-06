<?php
/**
 * Contains the HTTP front-end app.
 */

namespace Vault;

/**
 * The HTTP front-end app.
 */
class Front_End_App extends Web_App {

	/**
	 * The Aura.Session root session object.
	 *
	 * @var Aura\Session\Session
	 */
	protected $root_session;

	/**
	 * Our session segment.
	 *
	 * @var Aura\Session\Segment
	 */
	protected $session;

	/**
	 * An array to hold script configuration that should be included
	 * in the response page.
	 *
	 * @var array
	 */
	protected $script_config = [];

	/**
	 * An array to hold all script files that should be included in
	 * the response page.
	 *
	 * @var array
	 */
	protected $script_files = ['/script.js'];

	/**
	 * {@inheritdoc}
	 */
	public function __construct( $name, array $globals = null ) {
		parent::__construct( $name, $globals );

		// Initialize our session object.
		$session_factory = new \Aura\Session\SessionFactory;
		$this->root_session = $session_factory->newInstance(
			$this->request->cookies->get() );
		$this->session = $this->root_session->getSegment( __CLASS__ );
	}

	/**
	 * {@inheritdoc}
	 */
	public function init_router() {
		$this->router
			->addTokens( [
				            'reqid' => '\d+',
			             ] );

		$this->router->addGet( 'request.reqid.input',
		                       '/request/{reqid}/input' );

		$this->router->addPost( 'request.reqid.input#submission',
		                        '/request/{reqid}/input' );

		$this->router->addGet( 'request.reqid.thank-you',
		                       '/request/{reqid}/thank-you' );

		$this->router->addGet( 'unlock.reqid.unlock',
		                       '/unlock/{reqid}/unlock' );

		$this->router->addGet( 'unlock.reqid.view',
		                       '/unlock/{reqid}/view' );
	}

	/**
	 * Checks the CSRF token in the submitted (POST) data.
	 *
	 * @throws \RuntimeException if the CSRF token is invalid.
	 */
	protected function check_form_token() {
		$form_token = $this->request->post->get( 'form_token' );
		if ( ! $this->root_session->getCsrfToken()->isValid( $form_token ) ) {
			throw new \RuntimeException( 'Invalid form token. CSRF attempt?' );
		}
	}

	/**
	 * Display page contents.
	 *
	 * FIXME: this method is badly named -- it actually *sets up* the
	 * response object with proper contents to be displayed as a
	 * "page".
	 *
	 * @param string $title    The page title.
	 * @param mixed  $contents The page contents.
	 */
	protected function display_page( $title, $contents ) {
		$view = $this->views->get( 'page' );
		$view->set( 'title', $title );
		$view->set( 'contents', $contents );

		// NB: script tags broken apart to avoid problems with code
		// editors. Please, do not join them!
		$scripts = '<sc' . "ript>var Vault = {'config': " . json_encode( $this->script_config ) . '};</' . "script>\n";

		foreach ( $this->script_files as $url ) {
			$scripts .= '<sc' . "ript src=\"$url\"></scri" . "pt>\n";
		}
		$view->set( 'scripts', $scripts );

		$this->response->content->set( $view );
	}

	/**
	 * Loads a request.
	 *
	 * @param int $reqid The request ID.
	 *
	 * @throws NotFoundException if the request is not found.
	 */
	protected function load_request( $reqid ) {
		try {
			$request = $this->repo->find_request( $reqid );
		} catch ( VaultDataException $ex ) {
			throw new NotFoundException( $ex->getMessage() );
		}
		return $request;
	}

	/**
	 * Handles secret input.
	 *
	 * @param int $reqid The request ID.
	 *
	 * @throws NotFoundException if any problem is found.
	 */
	protected function handle_input_request( $reqid ) {

		$request = $this->load_request( $reqid );

		$mac = $this->request->query->get( 'm' );
		if ( ! $mac ) {
			throw new NotFoundException( 'No MAC' );
		}

		// When the user input the secret, we remove the input key. So
		// we can use it to know if there's a secret already, without
		// having to load the secret itself.
		if ( ! $request->input_key ) {
			throw new NotFoundException( 'No input key' );
		}

		if ( ! hash_equals( base64_decode( $mac ),
		                    $this->service->get_request_mac( $request ) ) ) {
			throw new NotFoundException( 'Invalid MAC' );
		}

		$view = $this->views->get( 'input-form' );

		$view->set( 'form_token',
		            $this->root_session->getCsrfToken()->getValue() );
		$view->set( 'reqid', $reqid );
		$view->set( 'action',
		            $this->router->generate( 'request.reqid.input#submission',
		                                       [
			                                       'reqid' => $request->reqid,
		                                       ] ) );
		$view->set( 'instructions', $request->instructions );
		$view->set( 'mac', $mac );

		$this->display_page( __( 'We need your information' ), $view );
	}

	/**
	 * Handles submission of the secret input form.
	 *
	 * @param int $reqid The request ID.
	 *
	 * @throws NotFoundException if any problem is found.
	 */
	protected function handle_input_request_submission( $reqid ) {
		$this->check_form_token();

		$request = $this->load_request( $reqid );

		$mac = $this->request->post->get( 'm' );
		if ( ! $mac ) {
			throw new NotFoundException( 'No MAC' );
		}

		// See comment in handle_input_request() above.
		if ( ! $request->input_key ) {
			throw new NotFoundException( 'No input key' );
		}

		if ( ! hash_equals( base64_decode( $mac ),
		                    $this->service->get_request_mac( $request ) ) ) {
			throw new NotFoundException( 'Invalid MAC' );
		}

		$res = $this->service->register_secret(
			$request,
			$this->request->post->get( 'secret' ) );

		// Add a flash flag so that we can check for a proper
		// submission in the "thank you" page.
		$this->session->setFlash( 'reqid', $request->reqid );

		$this->response->redirect->afterPost(
			$this->router->generate( 'request.reqid.thank-you',
			                         [
				                         'reqid' => $request->reqid,
			                         ] ) );
	}

	/**
	 * Handles the secret input "thank you" feedback page.
	 *
	 * @param int $reqid The request ID.
	 *
	 * @throws NotFoundException if the request ID is not valid.
	 */
	protected function handle_request_input_thank_you( $reqid ) {
		if ( $this->session->getFlash( 'reqid' ) !== $reqid ) {
			throw new NotFoundException();
		}

		$this->display_page( __( 'Thank you!' ),
		                     $this->views->get( 'input-thank-you' ) );
	}

	/**
	 * Handles the secret unlock request.
	 *
	 * FIXME: why such a weird method name?
	 *
	 * @param int $reqid The request ID.
	 *
	 * @throws NotFoundException if any problem is found.
	 */
	protected function handle_unlock_unlock( $reqid ) {
		$request = $this->load_request( $reqid );

		$mac = $this->request->query->get( 'm' );
		if ( ! $mac ) {
			throw new NotFoundException( 'No MAC' );
		}

		$unlock_key = base64_decode( $this->request->query->get( 'k' ) );

		if ( ! hash_equals( base64_decode( $mac ), $this->service->get_request_mac( $request, $unlock_key ) ) ) {
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
		                                                     'view_time' ) );
		$this->session->setFlash( 'plaintext',
		                          $this->service->unlock_secret( $secret,
		                                                         $unlock_key ) );

		$this->response->redirect->afterPost(
			$this->router->generate( 'unlock.reqid.view',
			                         [
				                         'reqid' => $request->reqid,
			                         ] ) );
	}

	/**
	 * Handles viewing of an unlocked secret.
	 *
	 * @param int $reqid The request ID.
	 *
	 * @throws NotFoundException if any problem is found.
	 */
	protected function handle_unlock_view( $reqid ) {
		if ( $this->session->getFlash( 'reqid' ) !== $reqid ) {
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
		                               $remaining_time . '; URL=' . $this->request->url->get() );
		$this->script_config['refresh'] = $remaining_time;

		$view = $this->views->get( 'unlock-view' );
		$view->set( 'plaintext', Esc::html( $plaintext ) );

		$this->display_page( __( 'Unlocked' ), $view );
	}

	/**
	 * {@inheritdoc}
	 */
	protected function handle_exception( \Exception $ex ) {
		parent::handle_exception( $ex );
		$this->display_page( __( 'Oops..' ), $this->views->get( 'exception' ) );
	}

	/**
	 * {@inheritdoc}
	 */
	protected function handle_request( \Aura\Router\Route $route ) {

		switch ( $route->params['action'] ) {

			case 'request.reqid.input':
				$this->handle_input_request( $route->params['reqid'] );
				break;

			case 'request.reqid.input#submission':
				$this->handle_input_request_submission( $route->params['reqid'] );
				break;

			case 'request.reqid.thank-you':
				$this->handle_request_input_thank_you( $route->params['reqid'] );
				break;

			case 'unlock.reqid.unlock':
				$this->handle_unlock_unlock( $route->params['reqid'] );
				break;

			case 'unlock.reqid.view':
				$this->handle_unlock_view( $route->params['reqid'] );
				break;

			default:
				throw new \RuntimeException( 'Invalid action: '
				                             . $route->params['action'] );
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function handle_not_found( $message ) {
		parent::handle_not_found( $message );
		$this->display_page( __( 'Not found' ),
		                     $this->views->get( '404-not-found' ) );
	}
}
