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
			throw new DataException("App not found");
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
					   'appid' => $app->appid,
					   'appkey' => $app->key,
					   'secret' => $app->secret,
					   'name' => $app->name,
					   'ping_url' => $app->ping_url,
				   ]);
		$sth = $this->db->prepare($query);
		$sth->execute($query->getBindValues());
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

		$request->reqid = $this->db->lastInsertId();

		return $request;
	}
}
