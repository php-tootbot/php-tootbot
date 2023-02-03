<?php
/**
 * Class UtilTest
 *
 * @created      03.02.2023
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2023 smiley
 * @license      MIT
 */

namespace PHPTootBot\PHPTootBotTest;

use InvalidArgumentException;
use PHPTootBot\PHPTootBot\Util;
use PHPUnit\Framework\TestCase;

/**
 * Tests the Util class
 */
class UtilTest extends TestCase{

	public function testMkdirInvalidDirectoryException():void{
		$this::expectException(InvalidArgumentException::class);
		$this::expectExceptionMessage('invalid directory');

		Util::mkdir('/');
	}

	public function testMkdirDirectoryExistsAsFileException():void{
		$this::expectException(InvalidArgumentException::class);
		$this::expectExceptionMessage('already exists as a file');

		Util::mkdir(__DIR__.'/../composer.json');
	}

	public function testMkdir():void{
		$dir = Util::mkdir(__DIR__.'/../.build');

		$this::assertDirectoryExists($dir);
	}

	public function testLoadJSON():void{
		$jsonFile = __DIR__.'/../composer.json';

		// object
		$json = Util::loadJSON($jsonFile);
		$this::assertSame('php-tootbot/php-tootbot', $json->name);

		// associative array
		$json = Util::loadJSON($jsonFile, true);
		$this::assertSame('php-tootbot/php-tootbot', $json['name']);
	}

	public function testSaveJSON():void{
		$data = ['foo' => 'bar'];
		$file = __DIR__.'/../.build/test.json';

		Util::saveJSON($file, $data, 0);

		$this::assertFileExists($file);

		$json = Util::loadJSON($file);
		$this::assertSame('bar', $json->foo);

		unlink($file);
	}

}
