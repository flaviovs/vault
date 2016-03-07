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
		                           . 'FROM apps '
		                           . 'WHERE appid = ?',
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
		                           . 'appid, secret, vault_secret, name, ping_url '
		                           . 'FROM apps '
		                           . 'WHERE appkey = ?',
		                           [ $key ] );
		$row = $sth->fetch();
		if ( ! $row ) {
			throw new VaultDataException( "App '$key' not found" );
		}

		$app = new App($key, $row['secret'],
		               $row['vault_secret'], $row['name']);
		$app->appid = $row['appid'];
		$app->ping_url = $row['ping_url'];

		return $app;
	}

	public function add_app( App $app ) {
		$sth = $this->db->perform( 'INSERT INTO apps '
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
		if ( ! $row ) {
			throw new VaultDataException( "Request $reqid not found" );
		}

		$request = new Request( $row['appid'], $row['email'] );
		$request->reqid = $reqid;
		$request->app_data = $row['app_data'];
		$request->instructions = $row['instructions'];
		$request->input_key = $row['input_key'];
		$request->created = new \DateTime( $row['created'] );

		return $request;
	}

	public function add_secret( Secret $secret ) {
		$this->db->perform( 'INSERT into secrets '
		                    . '(reqid, secret, mac, created) '
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
		$this->db->perform( 'UPDATE requests '
		                    . 'SET input_key = NULL '
		                    . 'WHERE reqid = ?',
		                    [ $request->reqid ] );
	}

	public function find_secret( $reqid ) {
		$sth = $this->db->perform( 'SELECT '
		                           . 'secret, mac, created '
		                           . 'FROM secrets '
		                           . 'WHERE reqid = ?',
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
		$this->db->perform( 'UPDATE secrets '
		                    . 'SET secret = NULL, mac = NULL WHERE reqid = ?',
		                    [ $secret->reqid ] );
	}

	public function delete_secret( Secret $secret ) {
		$this->db->perform( 'DELETE FROM secrets WHERE reqid = ?',
		                    [ $secret->reqid ] );
	}

	public function delete_answered_requests( \DateTime $before ) {
		$this->db->perform( 'DELETE requests '
		                    . 'FROM requests '
		                    . 'JOIN secrets USING (reqid) '
		                    . 'WHERE secrets.created < ?',
		                    [ $before->format( 'Y-m-d H:i:s' ) ] );
	}

	public function delete_unanswered_requests( \DateTime $before ) {
		$this->db->perform( 'DELETE FROM requests '
		                    . 'WHERE created < ?',
		                    [ $before->format( 'Y-m-d H:i:s' ) ] );
	}
}
