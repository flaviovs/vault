<?php

namespace Vault;

class VaultDataException extends VaultException {}

class Repository {

	protected $db;
	protected $q;

	public function __construct( \Aura\Sql\ExtendedPdo $db ) {
		$this->db = $db;
		$this->q = new \Aura\SqlQuery\QueryFactory(
			$db->getAttribute( \PDO::ATTR_DRIVER_NAME ) );
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

	public function find_app($key) {
		$query = $this->q->newSelect()
			->cols([
					   'appid',
					   'secret',
					   'name',
					   'ping_url',
				   ])
			->from('apps')
			->where('appkey = ?', $key);
		$sth = $this->db->prepare($query->getStatement());
		$sth->execute($query->getBindValues());
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
		$query = $this->q->newInsert()
			->into('apps')
			->cols([
					   'appkey' => $app->key,
					   'secret' => $app->secret,
					   'name' => $app->name,
					   'ping_url' => $app->ping_url,
				   ]);
		$sth = $this->db->prepare($query);
		$sth->execute($query->getBindValues());

		$app->appid = intval($this->db->lastInsertId());
	}

	public function add_request( Request $request ) {
		$query = $this->q->newInsert()
			->into('requests')
			->set('created', 'NOW()')
			->cols([
				       'appid' => $request->appid,
				       'app_data' => $request->app_data,
				       'email' => $request->email,
					   'instructions' => $request->instructions,
				       'input_key' => $request->input_key,
			       ]);
		$sth = $this->db->prepare($query);
		$sth->execute($query->getBindValues());

		$request->reqid = intval($this->db->lastInsertId());

		return $request;
	}

	public function find_request( $reqid ) {
		$query = $this->q->newSelect()
			->cols([
					   'appid',
					   'app_data',
					   'email',
					   'instructions',
					   'input_key',
					   'created',
				   ])
			->from('requests')
			->where('reqid = ?', $reqid);
		$sth = $this->db->prepare($query->getStatement());
		$sth->execute($query->getBindValues());
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
		$query = $this->q->newInsert()
			->into('secrets')
			->set('created', 'NOW()')
			->cols([
				       'reqid' => $secret->reqid,
				       'secret' => $secret->secret,
			       ]);
		$sth = $this->db->prepare($query);
		$sth->execute($query->getBindValues());

		return $secret;
	}

	public function clear_request_input_key( Request $request ) {
		$sth = $this->db->prepare( 'UPDATE requests '
		                           . 'SET input_key = NULL '
		                           . 'WHERE reqid = ?' );
		$sth->execute( [ $request->reqid ] );
	}
}
