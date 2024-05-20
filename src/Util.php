<?php
/**
 * Class Util
 *
 * @created      28.11.2022
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2022 smiley
 * @license      MIT
 */

namespace PHPTootBot\PHPTootBot;

use InvalidArgumentException;
use RuntimeException;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function in_array;
use function is_dir;
use function json_decode;
use function json_encode;
use function mkdir;
use function realpath;
use function sprintf;
use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 *
 */
class Util{

	/**
	 * @throws \InvalidArgumentException|\RuntimeException
	 */
	public static function mkdir(string $dir):string{

		// root dir or traversing is not allowed
		if(empty($dir) || in_array($dir, ['/', '.', '..'])){
			throw new InvalidArgumentException('invalid directory');
		}

		// $dir exists but is not a directory
		if(file_exists($dir) && !is_dir($dir)){
			throw new InvalidArgumentException(sprintf('cannot create directory: %s already exists as a file', $dir));
		}

		// $dir doesn't exist and the attempt to create failed
		if(!file_exists($dir) && !mkdir(directory: $dir, recursive: true)){
			throw new RuntimeException(sprintf('could not create directory: %s', $dir)); // @codeCoverageIgnore
		}

		$dir = realpath($dir);

		// reaplpath error
		if(!$dir){
			throw new InvalidArgumentException('invalid directory (realpath)');
		}

		return $dir;
	}

	/**
	 * load a JSON string from file into an array or object
	 *
	 * @throws \JsonException
	 */
	public static function loadJSON(string $filepath, bool $associative = false):mixed{
		return json_decode(json: file_get_contents($filepath), associative: $associative, flags: JSON_THROW_ON_ERROR);
	}

	/**
	 * save an array or object to a JSON file
	 *
	 * @throws \JsonException
	 */
	public static function saveJSON(string $filepath, array|object $data, int|null $jsonFlags = null):void{
		$jsonFlags ??= JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT;
		$jsonFlags |= JSON_THROW_ON_ERROR;

		file_put_contents($filepath, json_encode($data, $jsonFlags));
	}

}
