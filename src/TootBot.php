<?php
/**
 * Class TootBot
 *
 * @created      03.02.2023
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2023 smiley
 * @license      MIT
 */

namespace PHPTootBot\PHPTootBot;

use chillerlan\HTTP\Psr18\CurlClient;
use chillerlan\OAuth\Core\AccessToken;
use chillerlan\OAuth\Providers\Mastodon\Mastodon;
use chillerlan\OAuth\Storage\MemoryStorage;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 *
 */
abstract class TootBot implements TootBotInterface{

	protected TootBotOptions  $options;
	protected LoggerInterface $logger;
	protected ClientInterface $http;
	protected Mastodon        $mastodon;

	/**
	 * TootBot constructor
	 */
	public function __construct(TootBotOptions $options){
		$this->options  = $options;

		// invoke the worker instances
		$this->logger   = $this->initLogger();   // PSR-3
		$this->http     = $this->initHTTP();     // PSR-18
		$this->mastodon = $this->initMastodon();
	}

	/**
	 * initializes a PSR-3 logger instance
	 */
	protected function initLogger():LoggerInterface{
		// log formatter
		$formatter = (new LineFormatter($this->options->logFormat, $this->options->logDateFormat, true, true))
			->setJsonPrettyPrint(true)
		;
		// a log handler for STDOUT (or STDERR if you prefer)
		$logHandler = (new StreamHandler('php://stdout', $this->options->loglevel))
			->setFormatter($formatter)
		;

		return new Logger('log', [$logHandler]);
	}

	/**
	 * initializes a PSR-18 http client
	 */
	protected function initHTTP():ClientInterface{
		return new CurlClient(options: $this->options, logger: $this->logger);
	}

	/**
	 * initializes the Mastodon OAuth client
	 */
	protected function initMastodon():Mastodon{
		$tokenParams = [
			'accessToken' => $this->options->apiToken,
			'expires'     => AccessToken::EOL_NEVER_EXPIRES,
		];


		$tokenStorage = new MemoryStorage;
		$tokenStorage->storeAccessToken('Mastodon', new AccessToken($tokenParams));

		return (new Mastodon($this->http, $tokenStorage, $this->options, $this->logger))
			->setInstance($this->options->instance);
	}

}