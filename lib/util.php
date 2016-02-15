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
		return $level_name == 'EMERGENCY' ? '!' : $level_name[ 0 ];
	}

	public function write( array $record ) {
		if ( !$this->sth ) {
			$this->sth = $this->db->prepare( 'INSERT INTO log (created, loglevelid, message, appid) VALUES (NOW(), ?, ?, ?)' );
		}

		if ( isset($extra['app']) ) {
			$appid = $extra['app']->appid;
		} else {
			$appid = NULL;
		}

		$this->sth->execute( [ $this->get_level_id( $record[ 'level_name' ] ),
		                       $record[ 'message' ],
		                       $appid ] );
	}
}


class Mailer extends \PHPMailer {
	protected $debug;
	protected $log;

	public function __construct(array $conf, \Monolog\Logger $log) {
		parent::__construct( TRUE ); // Tell PHPMailer that we want exceptions.

		if ( ! isset( $conf[ 'mailer' ] ) ) {
			throw new VaultException('No mailer configuration found');
		}

		$conf = $conf[ 'mailer' ];

		if ( empty( $conf[ 'from_address' ] ) ) {
			throw new VaultException('Missing from_address mailer configuration');
		}

		if ( empty( $conf[ 'from_name' ] ) ) {
			throw new VaultException('Missing from_name mailer configuration');
		}

		$this->debug = ! empty( $conf[ 'debug' ] );

		if ( $this->debug ) {
			$this->Mailer = 'debug';
		}

		$this->setFrom( $conf[ 'from_address' ], $conf[ 'from_name' ] );

		$this->log = $log;
	}

	protected function debugSend($headers, $body) {
		$this->log->addDebug('Omitting email to '
		                     . implode( ',',
		                                array_keys( $this->all_recipients ) ),
		                     [
			                     'headers' => $headers,
			                     'body' => $body,
		                     ]);
		return TRUE;
	}
}
