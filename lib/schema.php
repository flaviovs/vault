<?php

namespace Vault;

const SCHEMA = [
	'SET default_storage_engine=InnoDB;',

	'CREATE TABLE apps (
	appid INTEGER PRIMARY KEY AUTO_INCREMENT,
	appkey VARCHAR(16) NOT NULL UNIQUE,
	secret VARCHAR(40) NOT NULL,
	name VARCHAR(100) NOT NULL,
	ping_url VARCHAR(200) UNIQUE
)
',

	'CREATE TABLE requests (
	reqid INTEGER PRIMARY KEY AUTO_INCREMENT,
	appid INTEGER NOT NULL
		REFERENCES app,
	app_data TEXT,
	email VARCHAR(100) NOT NULL,
	instructions TEXT,
	created DATETIME NOT NULL
)
',

	'CREATE TABLE secrets (
	reqid INTEGER PRIMARY KEY
		REFERENCES request ON DELETE CASCADE,
	secret BLOB NOT NULL,
	created DATETIME NOT NULL
)
',

];
