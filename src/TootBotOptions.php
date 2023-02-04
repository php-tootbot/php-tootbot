<?php
/**
 * Class TootBotOptions
 *
 * @created      03.02.2023
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2023 smiley
 * @license      MIT
 */

namespace PHPTootBot\PHPTootBot;

use chillerlan\OAuth\OAuthOptions;
use InvalidArgumentException;
use Psr\Log\LogLevel;
use function in_array;
use function sprintf;
use function strtolower;

/**
 * @property string $instance
 * @property string $apiToken
 * @property string $loglevel
 * @property string $logFormat
 * @property string $logDateFormat
 * @property string $tootVisibility
 * @property string $buildDir
 * @property string $dataDir
´ */
class TootBotOptions extends OAuthOptions{

	protected string $instance       = 'https://mastodon.social';
	protected string $apiToken       = '';
	protected string $loglevel       = LogLevel::INFO;
	protected string $logFormat      = "[%datetime%] %channel%.%level_name%: %message%\n";
	protected string $logDateFormat  = 'Y-m-d H:i:s';
	protected string $tootVisibility = 'public';
	protected string $buildDir;
	protected string $dataDir;

	/**
	 *
	 */
	protected function set_loglevel(string $loglevel):void{
		$loglevel = strtolower($loglevel);

		if(!in_array($loglevel, ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'])){
			throw new InvalidArgumentException(sprintf('invalid loglevel: "%s"', $loglevel));
		}

		$this->loglevel = $loglevel;
	}

	protected function set_tootVisibility(string $tootVisibility):void{
		$tootVisibility = strtolower($tootVisibility);

		if(!in_array($tootVisibility, ['public', 'unlisted', 'private', 'direct'])){
			throw new InvalidArgumentException(sprintf('invalid toot visibility: "%s"', $tootVisibility));
		}

		$this->tootVisibility = $tootVisibility;
	}

	/**
	 *
	 */
	protected function set_buildDir(string $buildDir):void{
		$this->buildDir = Util::mkdir($buildDir);
	}

	/**
	 *
	 */
	protected function set_dataDir(string $dataDir):void{
		$this->dataDir = Util::mkdir($dataDir);
	}

}
