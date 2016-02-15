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


class MailerFactory {
	protected $conf;

	public function __construct(array $conf) {
		if ( ! isset( $conf[ 'mailer' ] ) ) {
			throw new VaultException('No mailer configuration found');
		}

		$this->conf = $conf[ 'mailer' ];

		if ( empty( $this->conf[ 'from_address' ] ) ) {
			throw new VaultException('Missing from_address mailer configuration');
		}

		if ( empty( $this->conf[ 'from_name' ] ) ) {
			throw new VaultException('Missing from_name mailer configuration');
		}
	}

	public function new_mailer() {
		$mailer = new \PHPMailer();
		$mailer->setFrom( $this->conf[ 'from_address' ],
		                  $this->conf[ 'from_name' ] );
		return $mailer;
	}
}
