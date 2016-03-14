<?php

namespace Vault;

class DatabaseLoggingHandler extends \Monolog\Handler\AbstractProcessingHandler {
	protected $db;
	protected $sth;

	public function __construct( \PDO $db,
	                             $level = \Monolog\Logger::DEBUG,
	                             $bubble = true ) {
		parent::__construct( $level, $bubble );
		$this->db = $db;
	}

	protected function get_level_id( $level_name ) {
		return 'EMERGENCY' == $level_name ? '!' : $level_name[0];
	}

	public function write( array $record ) {
		if ( ! $this->sth ) {
			$this->sth = $this->db->prepare( 'INSERT INTO vault_log (created, vault_log_level_id, message, vault_app_id) VALUES (NOW(), ?, ?, ?)' );
		}

		if ( isset( $extra['app'] ) ) {
			$appid = $extra['app']->appid;
		} else {
			$appid = null;
		}

		$this->sth->execute( [
			                     $this->get_level_id( $record['level_name'] ),
			                     $record['message'],
			                     $appid,
		                     ] );
	}
}


class Mailer extends \PHPMailer {
	protected $debug;
	protected $log;

	public function __construct( Config $conf, \Monolog\Logger $log ) {
		parent::__construct( true ); // Tell PHPMailer that we want exceptions.

		try {
			$from_address = $conf->get( 'mailer', 'from_address' );
		} catch ( ConfigException $ex ) {
			throw new VaultException( 'Missing from_address mailer configuration' );
		}

		try {
			$from_name = $conf->get( 'mailer', 'from_name' );
		} catch ( ConfigException $ex ) {
			throw new VaultException( 'Missing from_name mailer configuration' );
		}

		$this->debug = $conf->get( 'debug', 'mailer', false );
		if ( $this->debug ) {
			// @codingStandardsIgnoreStart
			$this->Mailer = 'debug';
			// @codingStandardsIgnoreEnd
		}

		$this->setFrom( $from_address, $from_name );

		$this->log = $log;
	}

	protected function debugSend( $headers, $body ) {
		$this->log->addDebug( 'Omitting email to '
		                      . implode( ',',
		                                 array_keys( $this->all_recipients ) ),
		                      [
			                      'headers' => $headers,
			                      'body' => $body,
		                      ] );
		return true;
	}
}


class Esc {

	static public function html( $string, $quote_style = ENT_NOQUOTES ) {
		return htmlspecialchars( $string, $quote_style | ENT_HTML5, 'UTF-8' );
	}

	static public function attr( $string ) {
		return htmlspecialchars( $string, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	static public function instructions( $string ) {
		return strip_tags( $string, Request::INSTRUCTIONS_ALLOWED_TAGS );
	}
}
