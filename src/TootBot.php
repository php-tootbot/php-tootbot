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
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use function random_bytes;
use function sha1;
use function sleep;
use function sprintf;

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

	/**
	 * @see https://docs.joinmastodon.org/methods/statuses/#form-data-parameters
	 */
	protected function submitToot(array $params):void{

		$headers = [
			'Content-Type'    => 'application/json',
			'Idempotency-Key' => sha1(random_bytes(128)),
		];

		$retry = 0;
		// try to submit the post
		do{
			$response = $this->mastodon->request('/v1/statuses', $params, 'POST', $headers);

			if($response->getStatusCode() === 200){
				$this->submitTootSuccess($response);

				break;
			}

			$this->logger->warning(sprintf('submit post error: %s (retry #%s)', $response->getReasonPhrase(), $retry));

			$retry++;
			// we're not going to hammer, we sleep for a bit
			sleep(2);
		}
		while($retry < $this->options->retries);

		if($retry >= $this->options->retries){
			$this->submitTootFailure($response);
		}

	}

	/**
	 * Optional response processing after post submission (e.g. save toot-id, tick off used dataset...)
	 */
	protected function submitTootSuccess(ResponseInterface $response):void{
		// noop
	}

	/**
	 * Optional failed response processing after the maximum number of retries was hit
	 */
	protected function submitTootFailure(ResponseInterface $response):void{
		// noop
	}
}
