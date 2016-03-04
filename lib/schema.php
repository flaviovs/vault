<?php

namespace Vault;

const SCHEMA = [
	'ALTER DATABASE DEFAULT CHARACTER SET = utf8',
	'SET default_storage_engine=InnoDB',

	'CREATE TABLE apps (
	appid INTEGER PRIMARY KEY AUTO_INCREMENT,
	appkey VARCHAR(16) NOT NULL UNIQUE COLLATE utf8_bin,
	secret TEXT NOT NULL COLLATE utf8_bin,
	vault_secret TEXT COLLATE utf8_bin,
	name VARCHAR(100) NOT NULL,
	ping_url VARCHAR(200) NOT NULL UNIQUE
)
',

	'CREATE TABLE requests (
	reqid INTEGER PRIMARY KEY AUTO_INCREMENT,
	appid INTEGER NOT NULL
		REFERENCES app,
	app_data TEXT,
	email VARCHAR(100) NOT NULL,
	instructions TEXT,
	input_key TEXT COLLATE utf8_bin,
	created DATETIME NOT NULL
)
',

	'CREATE TABLE secrets (
	reqid INTEGER PRIMARY KEY,
	secret BLOB,
	mac BLOB,
	created DATETIME NOT NULL,
	pinged DATETIME,

	CONSTRAINT secrets_reqid
		FOREIGN KEY secrets_reqid (reqid) REFERENCES requests (reqid)
			ON DELETE CASCADE
)
',

	'CREATE TABLE log_level (
	loglevelid CHAR(1) PRIMARY KEY,
	name VARCHAR(10) NOT NULL UNIQUE
)',

	"INSERT INTO log_level (loglevelid, name) VALUES
		('D', 'Debug'),
		('I', 'Info'),
		('N', 'Notice'),
		('W', 'Warning'),
		('E', 'Error'),
		('C', 'Critial'),
		('A', 'Alert'),
		('!', 'Emergency')
",


	'CREATE TABLE log (
	logid INTEGER PRIMARY KEY AUTO_INCREMENT,
	created DATETIME NOT NULL,
	loglevelid CHAR(1) NOT NULL REFERENCES log_level,
	message TEXT NOT NULL,
	appid INTEGER REFERENCES apps
)
',

];
