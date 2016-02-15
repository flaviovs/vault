<?php

namespace Vault;

const SCHEMA = [
	'ALTER DATABASE DEFAULT CHARACTER SET = utf8',
	'SET default_storage_engine=InnoDB',

	'CREATE TABLE apps (
	appid INTEGER PRIMARY KEY AUTO_INCREMENT,
	appkey VARCHAR(16) NOT NULL UNIQUE COLLATE utf8_bin,
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

	"CREATE TABLE log_level (
	loglevelid CHAR(1) PRIMARY KEY,
	name VARCHAR(10) NOT NULL UNIQUE
)",

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


	"CREATE TABLE log (
	logid INTEGER PRIMARY KEY AUTO_INCREMENT,
	created DATETIME NOT NULL,
	loglevelid CHAR(1) NOT NULL REFERENCES log_level,
	message TEXT NOT NULL,
	appid INTEGER REFERENCES apps
)
"

];
