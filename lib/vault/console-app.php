<?php
/**
 * Contains the abstract base class for Vault console apps
 */

namespace Vault;

/**
 * The abstract base class for console apps.
 */
abstract class Console_App extends Vault_App {

	/**
	 * Aura Cli getopt helper.
	 *
	 * @var \Aura\Cli\Getopt
	 */
	protected $getopt;

	/**
	 * Aura Cli stdio object.
	 *
	 * @var \Aura\Cli\Stdio
	 */
	protected $stdio;

	/**
	 * Aura Cli context.
	 *
	 * @var \Aura\Cli\Context
	 */
	private $cli_context;

	/**
	 * {@inheritdoc}
	 */
	public function __construct( $name ) {
		parent::__construct( $name );

		$cli_factory = new \Aura\Cli\CliFactory();
		$this->cli_context = $cli_factory->newContext( $GLOBALS );
		$this->stdio = $cli_factory->newStdio();
	}

	/**
	 * {@inheritdoc}
	 */
	protected function init_basic_logging() {
		$general_level = $this->conf->get( 'logging', 'general_level' );
		$audit_level = $this->conf->get( 'logging', 'audit_level' );

		$general_handler = new \Monolog\Handler\StreamHandler( 'php://stderr',
		                                                       $general_level );
		$general_handler->setFormatter(
			new \Monolog\Formatter\LineFormatter(
				"%channel%: %level_name%: %message% %context% %extra%\n" ) );
		$this->log->setHandlers( [ $general_handler ] );

		if ( $audit_level == $general_level ) {
			$audit_handler = $general_handler;
		} else {
			$audit_handler = new \Monolog\Handler\StreamHandler( 'php://stderr',
			                                                     $audit_level );
			$audit_handler->setFormatter( $general_handler->getFormatter() );
		}
		$this->audit->setHandlers( [ $audit_handler ] );
	}

	/**
	 * Returns the global options array.
	 */
	protected function get_options() {
		return [
			'verbose,v' => 'Be verbose.',
			'help' => 'Display this help.',
		];
	}

	/**
	 * Returns the global usage string.
	 */
	protected function get_usage() {
		return '';
	}

	/**
	 * Prints the help text.
	 */
	protected function print_help() {
		$this->stdio->out( 'Usage: ' . $this->getopt->get( 0 ) . ' [OPTIONS]' );
		$usage = $this->get_usage();
		if ( $usage ) {
			$this->stdio->out( " $usage" );
		}
		$this->stdio->outln();
		$this->stdio->outln();
		$this->stdio->outln( 'OPTIONS may be:' );
		foreach ( $this->get_options() as $option => $help ) {
			$opts = array_map(
				function ( $opt ) {
					return ( strlen( $opt ) == 1 ?
					         "-$opt" : "--$opt" );
				}, explode( ',', $option ) );
			$this->stdio->outln( '  ' . implode( ', ', $opts ) );
			$this->stdio->outln( "      $help" );
		}
	}

	/**
	 * Parse the command line arguments.
	 *
	 * @throws \InvalidArgumentException if invalid arguments are found.
	 */
	protected function parse_arguments() {
		$this->getopt = $this->cli_context->getopt( $this->get_options() );
		$has_errors = $this->getopt->hasErrors();
		if ( $has_errors ) {
			throw new \InvalidArgumentException(
				implode( "\n",
				         array_map( function ( $ex ) {
						                return $ex->getMessage();
					     }, $this->getopt->getErrors() ) )
			);
		}
		return ! $has_errors;
	}

	/**
	 * Process the parsed arguments.
	 */
	protected function process_arguments() {
		if ( $this->getopt->get( '--help' ) ) {
			$this->print_help();
			exit( 0 );
		}

		if ( $this->getopt->get( '-v' ) ) {
			foreach ( $this->log->getHandlers() as $handler ) {
				$handler->setLevel( \Monolog\Logger::INFO );
			}
		}
	}

	/**
	 * Prints a nicely formatted error message.
	 *
	 * @param string $message The error message to print.
	 */
	protected function print_error( $message ) {
		$this->stdio->err( '<<red>>' );
		fwrite( STDERR, $message );
		$this->stdio->errln( '<<reset>>' );
		$this->stdio->errln( "Try '--help'." );
	}

	/**
	 * {@inheritdoc}
	 */
	protected function bootstrap() {
		parent::bootstrap();
		try {
			$this->parse_arguments();
			$this->process_arguments();
		} catch ( \InvalidArgumentException $ex ) {
			$this->print_error( $ex->getMessage() );
			exit( 1 );
		}
	}
}
