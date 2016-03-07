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
	appid INTEGER NOT NULL,
	app_data TEXT,
	email VARCHAR(100) NOT NULL,
	instructions TEXT,
	input_key TEXT COLLATE utf8_bin,
	created DATETIME NOT NULL,

	CONSTRAINT requests_appid
		FOREIGN KEY requests_appid (appid) REFERENCES apps (appid)
)
',

	'CREATE TABLE secrets (
	reqid INTEGER PRIMARY KEY,
	secret BLOB,
	mac BLOB,
	created DATETIME NOT NULL,

	CONSTRAINT secrets_reqid
		FOREIGN KEY secrets_reqid (reqid) REFERENCES requests (reqid)
			ON DELETE CASCADE
)
',

	'CREATE TABLE log_level (
	loglevelid CHAR(1) COLLATE utf8_bin PRIMARY KEY,
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
	loglevelid CHAR(1) COLLATE utf8_bin NOT NULL,
	message TEXT NOT NULL,
	appid INTEGER,

	CONSTRAINT log_loglevelid
		FOREIGN KEY log_loglevelid (loglevelid)
			REFERENCES log_level (loglevelid),
	CONSTRAINT log_appid
		FOREIGN KEY log_appid (appid) REFERENCES apps (appid)
)
',

];
