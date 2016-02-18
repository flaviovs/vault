<?php

namespace Vault;

class VaultDataException extends VaultException {}

class Repository {

	protected $db;

	public function __construct( \Aura\Sql\ExtendedPdo $db ) {
		$this->db = $db;
	}

	public function begin() {
		$this->db->beginTransaction();
	}

	public function commit() {
		$this->db->commit();
	}

	public function rollback() {
		$this->db->rollBack();
	}

	public function find_app( $appid ) {
		$sth = $this->db->perform('SELECT '
		                          . 'appkey, secret, name, ping_url '
		                          . 'FROM apps '
		                          . 'WHERE appid = ?',
		                          [ $appid ]);
		$row = $sth->fetch();
		if (!$row) {
			throw new VaultDataException("App $appid not found");
		}

		$app = new App($row[ 'appkey' ], $row[ 'secret' ], $row[ 'name' ]);
		$app->appid = $appid;
		$app->ping_url = $row[ 'ping_url' ];

		return $app;
	}

	public function find_app_by_key( $key ) {
		$sth = $this->db->perform('SELECT '
		                          . 'appid, secret, name, ping_url '
		                          . 'FROM apps '
		                          . 'WHERE appkey = ?',
		                          [ $key ]);
		$row = $sth->fetch();
		if (!$row) {
			throw new VaultDataException("App '$key' not found");
		}

		$app = new App($key, $row['secret'], $row['name']);
		$app->appid = $row['appid'];
		$app->ping_url = $row['ping_url'];

		return $app;
	}

	public function add_app(App $app) {
		$sth = $this->db->perform( 'INSERT INTO apps '
		                           . '(appkey, secret, name, ping_url) '
		                           . 'VALUES (?, ?, ?, ?)',
		                           [
			                           $app->key,
			                           $app->secret,
			                           $app->name,
			                           $app->ping_url,
		                           ] );
		$app->appid = intval( $this->db->lastInsertId() );
	}

	public function add_request( Request $request ) {
		$sth = $this->db->perform( 'INSERT INTO requests '
		                           . '(appid, app_data, email, instructions, '
		                           . 'input_key, created) '
		                           . 'VALUES (?, ?, ?, ?, ?, NOW())',
		                           [
			                           $request->appid,
			                           $request->app_data,
			                           $request->email,
			                           $request->instructions,
			                           $request->input_key,
		                           ] );
		$request->reqid = intval( $this->db->lastInsertId() );

		return $request;
	}

	public function find_request( $reqid ) {
		$sth = $this->db->perform( 'SELECT appid, app_data, email, '
		                           . 'instructions, input_key, created '
		                           . 'FROM requests '
		                           . 'WHERE reqid = ?',
		                           [ $reqid ] );
		$row = $sth->fetch();
		if (!$row) {
			throw new VaultDataException("Request $reqid not found");
		}

		$request = new Request($row['appid'], $row['email']);
		$request->reqid = $reqid;
		$request->app_data = $row['app_data'];
		$request->instructions = $row['instructions'];
		$request->input_key = $row['input_key'];
		$request->created = new \DateTime($row['created']);

		return $request;
	}

	public function add_secret( Secret $secret ) {
		$this->db->perform( 'INSERT into secrets '
		                    . '(reqid, secret, created) '
		                    . 'VALUES (?, ?, ?)',
		                    [
			                    $secret->reqid,
			                    $secret->secret,
			                    $secret->created->format(\DateTime::ISO8601),
		                    ] );
		return $secret;
	}

	public function clear_request_input_key( Request $request ) {
		$this->db->perform( 'UPDATE requests '
		                    . 'SET input_key = NULL '
		                    . 'WHERE reqid = ?',
		                    [ $request->reqid ] );
	}

	public function record_ping( $reqid ) {
		$this->db->perform( 'UPDATE secrets SET pinged = NOW() WHERE reqid = ?',
		                    [ $reqid ] );
	}
}
