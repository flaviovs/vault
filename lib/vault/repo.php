<?php
/**
 * Contains the Vault repository class.
 */

namespace Vault;

/**
 * Exception raised when a problem regarding Vault data is found.
 */
class VaultDataException extends VaultException {}

/**
 * The Vault repository class.
 */
class Repository {

	/**
	 * The database connection.
	 *
	 * @var \Aura\Sql\ExtendedPDO
	 */
	protected $db;

	/**
	 * Constructs the object.
	 */
	public function __construct( \Aura\Sql\ExtendedPdo $db ) {
		$this->db = $db;
	}

	/**
	 * Begins a repository transaction.
	 */
	public function begin() {
		$this->db->beginTransaction();
	}

	/**
	 * Commits a repository transactions.
	 */
	public function commit() {
		$this->db->commit();
	}

	/**
	 * Rollback a repository transaction.
	 */
	public function rollback() {
		$this->db->rollBack();
	}

	/**
	 * Find an app by ID.
	 *
	 * @param int $appid The app ID.
	 *
	 * @return App
	 *
	 * @throws VaultDataException if no app is found.
	 */
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
		                $row['vault_secret'], $row['name'], $row['ping_url'] );
		$app->appid = $appid;

		return $app;
	}

	/**
	 * Finds an app by key.
	 *
	 * @param string $key The app key.
	 *
	 * @return App
	 *
	 * @throws VaultDataException if no app is found.
	 */
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

		$app = new App( $key, $row['secret'],
		                $row['vault_secret'], $row['name'], $row['ping_url'] );
		$app->appid = $row['vault_app_id'];

		return $app;
	}

	/**
	 * Adds an app.
	 *
	 * @param App $app The app to add.
	 */
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

	/**
	 * Adds a request.
	 *
	 * @param Request $request The request to add.
	 */
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

	/**
	 * Find a request by ID.
	 *
	 * @param int $reqid The request ID.
	 *
	 * @throws VaultDataException if no request is found.
	 */
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

	/**
	 * Adds a secret.
	 *
	 * @param Secret $secret The secret to add.
	 */
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

	/**
	 * Clears a request input key.
	 *
	 * @param Request $request The request.
	 */
	public function clear_request_input_key( Request $request ) {
		$this->db->perform( 'UPDATE vault_requests '
		                    . 'SET input_key = NULL '
		                    . 'WHERE vault_request_id = ?',
		                    [ $request->reqid ] );
		$request->input_key = null;
	}

	/**
	 * Finds a secret.
	 *
	 * @param int $reqid The associated request ID.
	 *
	 * @return Secret
	 *
	 * @throws VaultDataException if no secret is found.
	 */
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

	/**
	 * Record the fact that a secret was unlocked.
	 *
	 * @param Secret $secret The secret object.
	 */
	public function record_unlock( Secret $secret ) {
		$this->db->perform( 'UPDATE vault_secrets '
		                    . 'SET secret = NULL, mac = NULL '
		                    . 'WHERE vault_request_id = ?',
		                    [ $secret->reqid ] );
	}

	/**
	 * Deletes a secret.
	 *
	 * @param Secret $secret The secret to delete.
	 */
	public function delete_secret( Secret $secret ) {
		$this->db->perform( 'DELETE FROM vault_secrets '
		                    . 'WHERE vault_request_id = ?',
		                    [ $secret->reqid ] );
	}

	/**
	 * Deletes answered requests.
	 *
	 * @param \DateTime $before Only requests before this date/time will be deleted.
	 */
	public function delete_answered_requests( \DateTime $before ) {
		$this->db->perform( 'DELETE vault_requests '
		                    . 'FROM vault_requests '
		                    . 'JOIN vault_secrets USING (vault_request_id) '
		                    . 'WHERE vault_secrets.created < ?',
		                    [ $before->format( 'Y-m-d H:i:s' ) ] );
	}

	/**
	 * Deletes unanswered requests.
	 *
	 * @param \DateTime $before Only requests before this date/time will be deleted.
	 */
	public function delete_unanswered_requests( \DateTime $before ) {
		$this->db->perform( 'DELETE FROM vault_requests '
		                    . 'WHERE created < ?',
		                    [ $before->format( 'Y-m-d H:i:s' ) ] );
	}
}
