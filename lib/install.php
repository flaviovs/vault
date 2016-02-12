<?php

namespace Vault;

class Installer_App extends App {

	protected function init_basic_logging() {
		$handler = new \Monolog\Handler\StreamHandler('php://stderr');
		$handler->setFormatter(
			new \Monolog\Formatter\LineFormatter(
				"%channel%: %level_name%: %message% %context% %extra%\n"));
		$this->log->setHandlers([$handler]);
	}

	protected function handle_exception( \Exception $ex ) {
		$this->response->status->setCode(500);
	}
}
