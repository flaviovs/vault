<?php

namespace Vault;

abstract class Console_App extends Vault_App {

	protected $getopt;
	protected $stdio;
	private $cli_context;

	public function __construct() {
		parent::__construct();

		$cli_factory = new \Aura\Cli\CliFactory();
		$this->cli_context = $cli_factory->newContext($GLOBALS);
		$this->stdio = $cli_factory->newStdio();
	}

	protected function init_basic_logging() {
		$handler = new \Monolog\Handler\StreamHandler('php://stderr');
		$handler->setFormatter(
			new \Monolog\Formatter\LineFormatter(
				"%channel%: %level_name%: %message% %context% %extra%\n"));
		$this->log->setHandlers([$handler]);
	}

	protected function get_options() {
		return [
			'verbose,v' => 'Be verbose.',
			'help' => 'Display this help.',
		];
	}

	protected function get_usage() {
		return '';
	}

	protected function print_help() {
		$this->stdio->out("Usage: $_SERVER[SCRIPT_NAME] [OPTIONS]");
		$usage = $this->get_usage();
		if ( $usage ) {
			$this->stdio->out(" $usage");
		}
		$this->stdio->outln();
		$this->stdio->outln();
		$this->stdio->outln("OPTIONS may be:");
		foreach ( $this->get_options() as $option => $help ) {
			$opts = array_map( function ( $opt ) {
					return ( strlen($opt) == 1 ?
					         "-$opt" : "--$opt" );
				}, explode( ',', $option ));
			$this->stdio->outln("  " . implode(", ", $opts));
			$this->stdio->outln("      $help");
		}
	}

	protected function parse_arguments() {
		$this->getopt = $this->cli_context->getopt( $this->get_options() );
		$has_errors = $this->getopt->hasErrors();
		if ( $has_errors ) {
			foreach ( $this->getopt->getErrors() as $error ) {
				$this->stdio->err( '<<red>>' );
				fwrite( STDERR, $error->getMessage() );
				$this->stdio->errln( '<<reset>>' );
			}
			$this->stdio->errln( "Try '--help'" );
		};
		return ! $has_errors;
	}

	protected function process_arguments() {
		if ( $this->getopt->get( '--help' ) ) {
			$this->print_help();
			exit(0);
		}

		if ( $this->getopt->get('-v') ) {
			foreach ( $this->log->getHandlers() as $handler ) {
				$handler->setLevel(\Monolog\Logger::INFO);
			}
		}
	}

	protected function bootstrap() {
		parent::bootstrap();
		if ( ! $this->parse_arguments() ) {
			exit( 1 );
		}
		$this->process_arguments();
	}
}