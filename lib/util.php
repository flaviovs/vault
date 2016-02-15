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
