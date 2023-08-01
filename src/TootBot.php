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
use chillerlan\HTTP\Utils\MessageUtil;
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
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use function array_map;
use function in_array;
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
	 * @see https://docs.joinmastodon.org/methods/statuses/#form-data-parameters
	 */
	protected function submitToot(array $body):void{

		$headers = [
			'Content-Type'    => 'application/json',
			'Idempotency-Key' => sha1(random_bytes(128)),
			'User-Agent'      => $this->options->user_agent,
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
			/** @noinspection PhpUndefinedVariableInspection */
			$this->submitTootFailure($response);
		}

	}

	/**
	 * Uploads the image content given in the StreamInterface, returns the media id required for embedding, null on error.
	 *
	 * @see https://docs.joinmastodon.org/methods/media/
	 * @see https://docs.joinmastodon.org/api/guidelines/#focal-points
	 */
	protected function uploadMedia(
		StreamInterface $image,
		string          $description,
		string          $filename,
		StreamInterface $thumbnail = null,
		array           $focus = null
	):?string{
		// create the multipart body
		$multipartStreamBuilder = new MultipartStreamBuilder($this->streamFactory);

		// the description/alt-text
		$multipartStreamBuilder->addString(
			content  : $description,
			fieldname: 'description',
			headers  : ['Content-Encoding' => 'UTF-8']
		);

		// optional focal points
		if($focus !== null){
			$focus = array_map('floatval', $focus);

			$multipartStreamBuilder->addString(
				content  : sprintf('%f,%f', $focus[0], $focus[1]),
				fieldname: 'focus',
			);
		}

		// the image content stream
		$multipartStreamBuilder->addStream(
			stream   : $image,
			fieldname: 'file',
			filename : $filename,
			headers  : ['Content-Transfer-Encoding' => 'binary']
		);

		// an optional thumbnail - the file type should be the same as the image as the MIME is determined by the given filename
		if($thumbnail !== null){
			$multipartStreamBuilder->addStream(
				stream   : $thumbnail,
				fieldname: 'thumbnail',
				filename : 'thumbnail-'.$filename,
				headers  : ['Content-Transfer-Encoding' => 'binary']
			);
		}

		// create and fire the upload request
		$request = $this->requestFactory
			->createRequest('POST', $this->options->instance.'/api/v2/media')
			->withProtocolVersion('1.1')
			->withHeader('User-Agent', $this->options->user_agent);

		/** @var \Psr\Http\Message\RequestInterface $request */
		$request        = $multipartStreamBuilder->build($request);
		/** @phan-suppress-next-line PhanTypeMismatchArgument */
		$uploadResponse = $this->mastodon->sendRequest($request);
		$status         = $uploadResponse->getStatusCode();

		if(!in_array($status, [200, 202], true)){
			$this->logger->error(sprintf('image upload error: HTTP/%s', $status));

			return null;
		}

		try{
			$json = MessageUtil::decodeJSON($uploadResponse);
		}
		catch(Throwable $e){
			$this->logger->error(sprintf('image upload response json decode error: %s', $e->getMessage()));

			return null;
		}

		$this->logger->info(sprintf('upload successful, media id: "%s"', $json->id));

		return (string)$json->id;
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
