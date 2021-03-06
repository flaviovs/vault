<?php
/**
 * Contains the CLI interface app class
 */

namespace Vault;

/**
 * The CLI interface app class.
 */
class CLI_App extends Console_App {

	/**
	 * {@inheritdoc}
	 */
	protected function get_usage() {
		return 'COMMAND [COMMAND-ARGS]';
	}

	/**
	 * Prints result array, usually returned by a service call.
	 *
	 * @param array $result The array to print.
	 */
	protected function print_result( array $result ) {
		echo json_encode( $result, JSON_PRETTY_PRINT );
		echo "\n";
	}

	/**
	 * Handles the 'app add' command.
	 *
	 * @throws \InvalidArgumentException on invalid arguments.
	 */
	protected function handle_app_add() {
		$name = $this->getopt->get( 3 );
		if ( ! $name ) {
			throw new \InvalidArgumentException( 'Missing app name' );
		}

		$ping_url = $this->getopt->get( 4 );
		if ( ! $ping_url ) {
			throw new \InvalidArgumentException( 'Missing ping URL' );
		}

		$res = $this->service->add_app( $name, $ping_url );

		$this->print_result( $res );
	}

	/**
	 * Handles the 'app' command.
	 *
	 * @throws \InvalidArgumentException on invalid arguments.
	 */
	protected function handle_app() {
		$app_command = $this->getopt->get( 2 );

		switch ( $app_command ) {
			case 'add':
				$this->handle_app_add();
				break;
			default:
				throw new \InvalidArgumentException( "Unkown app command '$app_command'" );
		}
	}

	/**
	 * Handles the 'request' command.
	 *
	 * @throws \InvalidArgumentException on invalid arguments.
	 */
	protected function handle_request() {
		$appkey = $this->getopt->get( 2 );
		$email = $this->getopt->get( 3 );

		if ( ! ( $appkey && $email ) ) {
			throw new \InvalidArgumentException( "Usage 'request APPKEY EMAIL'" );
		}

		$app_data = $this->getopt->get( 4 );

		$instructions = file_get_contents( 'php://stdin' );

		$res = $this->service->register_request( $appkey,
		                                         $email,
		                                         $instructions,
		                                         $app_data );

		$this->print_result( $res );
	}

	/**
	 * Handles the 'secret' command.
	 *
	 * @throws \InvalidArgumentException on invalid arguments.
	 */
	protected function handle_secret() {
		$reqid = $this->getopt->get( 2 );

		if ( ! $reqid ) {
			throw new \InvalidArgumentException( "Usage 'secret REQUEST-ID'" );
		}

		$request = $this->repo->find_request( $reqid );

		try {
			$this->repo->find_secret( $reqid );
			throw new \InvalidArgumentException( "A secret for request $reqid was already entered." );
		} catch ( VaultDataException $ex ) {
			// *NOTHING*
		}

		$secret = file_get_contents( 'php://stdin' );

		$res = $this->service->register_secret( $request, $secret );

		$this->print_result( $res );
	}

	/**
	 * Process the command line.
	 *
	 * @throws \InvalidArgumentException if arguments are not valid.
	 */
	protected function process_command() {
		$command = $this->getopt->get( 1 );

		switch ( $command ) {
			case '':
				throw new \InvalidArgumentException( 'Invalid usage.' );

			case 'app':
				$this->handle_app();
				break;

			case 'maintenance':
				$this->service->maintenance();
				break;

			case 'request':
				$this->handle_request();
				break;

			case 'secret':
				$this->handle_secret();
				break;

			default:
				throw new \InvalidArgumentException( "Unkown command '$command'" );
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function run() {
		$this->bootstrap();

		try {
			$this->process_command();
		} catch ( \InvalidArgumentException $ex ) {
			$this->print_error( $ex->getMessage() );
			return 1;
		}

		return 0;
	}
}
