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

use chillerlan\HTTP\Common\MultipartStreamBuilder;
use chillerlan\HTTP\Psr17\RequestFactory;
use chillerlan\HTTP\Psr17\StreamFactory;
use chillerlan\HTTP\Psr18\CurlClient;
use chillerlan\OAuth\Core\AccessToken;
use chillerlan\OAuth\Providers\Mastodon;
use chillerlan\OAuth\Storage\MemoryStorage;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use function random_bytes;
use function sha1;
use function sleep;
use function sprintf;

/**
 *
 */
abstract class TootBot implements TootBotInterface{

	protected TootBotOptions          $options;
	protected LoggerInterface         $logger;
	protected ClientInterface         $http;
	protected Mastodon                $mastodon;
	protected RequestFactoryInterface $requestFactory;
	protected StreamFactoryInterface  $streamFactory;

	/**
	 * TootBot constructor
	 */
	public function __construct(TootBotOptions $options){
		$this->options  = $options;

		// invoke the worker instances
		$this->logger         = $this->initLogger();         // PSR-3
		$this->requestFactory = $this->initRequestFactory(); // PSR-17
		$this->streamFactory  = $this->initStreamFactory();  // PSR-17
		$this->http           = $this->initHTTP();           // PSR-18
		$this->mastodon       = $this->initMastodon();       // acts as PSR-18
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

		return (new Mastodon($this->http, $this->options, $this->logger))
			->setInstance($this->options->instance)
			->setStorage(new MemoryStorage)
			->storeAccessToken(new AccessToken($tokenParams))
		;
	}

	/**
	 * initializes a PSR-17 request factory
	 */
	protected function initRequestFactory():RequestFactoryInterface{
		return new RequestFactory;
	}

	/**
	 * initializes a PSR-17 stream factory
	 */
	protected function initStreamFactory():StreamFactoryInterface{
		return new StreamFactory;
	}

	/**
	 * returns a new multipart stream builder
	 */
	protected function getMultipartStreamBuilder():MultipartStreamBuilder{
		return new MultipartStreamBuilder($this->streamFactory);
	}

	/**
	 * @see https://docs.joinmastodon.org/methods/statuses/#form-data-parameters
	 */
	protected function submitToot(array $body):void{

		$headers = [
			'Content-Type'    => 'application/json',
			'Idempotency-Key' => sha1(random_bytes(128)),
		];

		$retry = 0;
		// try to submit the post
		do{

			try{
				$response = $this->mastodon->request(path: '/v1/statuses', method: 'POST', body: $body, headers: $headers);
			}
			catch(Throwable $e){
				$this->logger->warning(sprintf('submit post exception: %s (retry #%s)', $e->getMessage(), $retry));
				$retry++;

				continue;
			}

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
