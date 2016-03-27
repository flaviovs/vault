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
		$sth = $this->db->perform( 'SELECT '
		                           . 'appkey, secret, vault_secret, name, ping_url '
		                           . 'FROM vault_apps '
		                           . 'WHERE vault_app_id = ?',
		                           [ $appid ] );
		$row = $sth->fetch();
		if ( ! $row ) {
			throw new VaultDataException( "App $appid not found" );
		}

		$app = new App( $row['appkey'], $row['secret'],
		                $row['vault_secret'], $row['name'] );
		$app->appid = $appid;
		$app->ping_url = $row['ping_url'];

		return $app;
	}

	public function find_app_by_key( $key ) {
		$sth = $this->db->perform( 'SELECT '
		                           . 'vault_app_id, secret, vault_secret, name, ping_url '
		                           . 'FROM vault_apps '
		                           . 'WHERE appkey = ?',
		                           [ $key ] );
		$row = $sth->fetch();
		if ( ! $row ) {
			throw new VaultDataException( "App '$key' not found" );
		}

		$app = new App($key, $row['secret'],
		               $row['vault_secret'], $row['name']);
		$app->appid = $row['vault_app_id'];
		$app->ping_url = $row['ping_url'];

		return $app;
	}

	public function add_app( App $app ) {
		$sth = $this->db->perform( 'INSERT INTO vault_apps '
		                           . '(appkey, secret, vault_secret, name, ping_url) '
		                           . 'VALUES (?, ?, ?, ?, ?)',
		                           [
			                           $app->key,
			                           $app->secret,
			                           $app->vault_secret,
			                           $app->name,
			                           $app->ping_url,
		                           ] );
		$app->appid = intval( $this->db->lastInsertId() );
	}

	public function add_request( Request $request ) {
		$sth = $this->db->perform( 'INSERT INTO vault_requests '
		                           . '(vault_app_id, app_data, email, instructions, '
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
		$sth = $this->db->perform( 'SELECT vault_app_id, app_data, email, '
		                           . 'instructions, input_key, created '
		                           . 'FROM vault_requests '
		                           . 'WHERE vault_request_id = ?',
		                           [ $reqid ] );
		$row = $sth->fetch();
		if ( ! $row ) {
			throw new VaultDataException( "Request $reqid not found" );
		}

		$request = new Request( $row['vault_app_id'], $row['email'] );
		$request->reqid = $reqid;
		$request->app_data = $row['app_data'];
		$request->instructions = $row['instructions'];
		$request->input_key = $row['input_key'];
		$request->created = new \DateTime( $row['created'] );

		return $request;
	}

	public function add_secret( Secret $secret ) {
		$this->db->perform( 'INSERT into vault_secrets '
		                    . '(vault_request_id, secret, mac, created) '
		                    . 'VALUES (?, ?, ?, ?)',
		                    [
			                    $secret->reqid,
			                    $secret->secret,
			                    $secret->mac,
			                    $secret->created->format( 'Y-m-d H:i:s' ),
		                    ] );
		return $secret;
	}

	public function clear_request_input_key( Request $request ) {
		$this->db->perform( 'UPDATE vault_requests '
		                    . 'SET input_key = NULL '
		                    . 'WHERE vault_request_id = ?',
		                    [ $request->reqid ] );
	}

	public function find_secret( $reqid ) {
		$sth = $this->db->perform( 'SELECT '
		                           . 'secret, mac, created '
		                           . 'FROM vault_secrets '
		                           . 'WHERE vault_request_id = ?',
		                           [ $reqid ] );
		$row = $sth->fetch();
		if ( ! $row ) {
			throw new VaultDataException( "Secret $reqid not found" );
		}

		$app = new Secret( $reqid, $row['secret'] );
		$app->mac = $row['mac'];
		$app->created = new \DateTime( $row['created'] );

		return $app;
	}

	public function record_unlock( Secret $secret ) {
		$this->db->perform( 'UPDATE vault_secrets '
		                    . 'SET secret = NULL, mac = NULL '
		                    . 'WHERE vault_request_id = ?',
		                    [ $secret->reqid ] );
	}

	public function delete_secret( Secret $secret ) {
		$this->db->perform( 'DELETE FROM vault_secrets '
		                    . 'WHERE vault_request_id = ?',
		                    [ $secret->reqid ] );
	}

	public function delete_answered_requests( \DateTime $before ) {
		$this->db->perform( 'DELETE vault_requests '
		                    . 'FROM vault_requests '
		                    . 'JOIN vault_secrets USING (vault_request_id) '
		                    . 'WHERE vault_secrets.created < ?',
		                    [ $before->format( 'Y-m-d H:i:s' ) ] );
	}

	public function delete_unanswered_requests( \DateTime $before ) {
		$this->db->perform( 'DELETE FROM vault_requests '
		                    . 'WHERE created < ?',
		                    [ $before->format( 'Y-m-d H:i:s' ) ] );
	}
}