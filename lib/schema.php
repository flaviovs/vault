<?php

namespace Vault;

const SCHEMA = [
	'ALTER DATABASE DEFAULT CHARACTER SET = utf8',
	'SET default_storage_engine=InnoDB',

	'CREATE TABLE vault_apps (
	vault_app_id INTEGER PRIMARY KEY AUTO_INCREMENT,
	appkey VARCHAR(16) NOT NULL UNIQUE COLLATE utf8_bin,
	secret TEXT NOT NULL COLLATE utf8_bin,
	vault_secret TEXT COLLATE utf8_bin,
	name VARCHAR(100) NOT NULL,
	ping_url VARCHAR(200) NOT NULL UNIQUE
)
',

	'CREATE TABLE vault_requests (
	vault_request_id INTEGER PRIMARY KEY AUTO_INCREMENT,
	vault_app_id INTEGER NOT NULL,
	app_data TEXT,
	email VARCHAR(100) NOT NULL,
	instructions TEXT,
	input_key TEXT COLLATE utf8_bin,
	created DATETIME NOT NULL,

	CONSTRAINT requests_appid
		FOREIGN KEY requests_appid (vault_app_id)
			REFERENCES vault_apps (vault_app_id)
)
',

	'CREATE TABLE vault_secrets (
	vault_request_id INTEGER PRIMARY KEY,
	secret BLOB,
	mac BLOB,
	created DATETIME NOT NULL,

	CONSTRAINT vault_secrets_request_id
		FOREIGN KEY vault_secrets_request_id (vault_request_id)
			REFERENCES vault_requests (vault_request_id)
			ON DELETE CASCADE
)
',

	'CREATE TABLE vault_log_level (
	vault_log_level_id CHAR(1) COLLATE utf8_bin PRIMARY KEY,
	name VARCHAR(10) NOT NULL UNIQUE
)',

	"INSERT INTO vault_log_level (vault_log_level_id, name) VALUES
		('D', 'Debug'),
		('I', 'Info'),
		('N', 'Notice'),
		('W', 'Warning'),
		('E', 'Error'),
		('C', 'Critial'),
		('A', 'Alert'),
		('!', 'Emergency')
",


	'CREATE TABLE vault_log (
	vault_log_id INTEGER PRIMARY KEY AUTO_INCREMENT,
	created DATETIME NOT NULL,
	vault_log_level_id CHAR(1) COLLATE utf8_bin NOT NULL,
	message TEXT NOT NULL,
	vault_app_id INTEGER,

	CONSTRAINT vault_log_level
		FOREIGN KEY vault_log_level (vault_log_level_id)
			REFERENCES vault_log_level (vault_log_level_id),
	CONSTRAINT vault_log_app_id
		FOREIGN KEY vault_log_app_id (vault_app_id)
			REFERENCES vault_apps (vault_app_id)
)
',

];
