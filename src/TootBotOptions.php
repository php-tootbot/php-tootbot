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

use chillerlan\HTTP\HTTPOptionsTrait;
use chillerlan\OAuth\OAuthOptionsTrait;
use chillerlan\Settings\SettingsContainerAbstract;
use InvalidArgumentException;
use Psr\Log\LogLevel;
use function in_array;
use function rtrim;
use function sprintf;
use function strtolower;

/**
 * Common toot bot options
´*/
class TootBotOptions extends SettingsContainerAbstract{
	use HTTPOptionsTrait, OAuthOptionsTrait;

	/**
	 * The home instance of this bot, e.g. https://botsin.space/
	 */
	protected string $instance = 'https://botsin.space/';

	/**
	 * The access token from the oauth applcation settings (or any other valid token)
	 *
	 * The settings can be found under `[mastodon instance]/settings/applications/[app id]`
	 *
	 * @link https://botsin.space/settings/applications
	 */
	protected string $apiToken = '';

	/**
	 * The log level for the internal logger instance
	 *
	 * @see \Psr\Log\LogLevel
	 */
	protected string $loglevel = LogLevel::INFO;

	/**
	 * The log format string
	 *
	 * @see \Monolog\Formatter\LineFormatter
	 * @link https://github.com/Seldaek/monolog/blob/main/doc/01-usage.md#customizing-the-log-format
	 */
	protected string $logFormat      = "[%datetime%] %channel%.%level_name%: %message%\n";

	/**
	 * @see \DateTimeInterface::format()
	 * @link https://www.php.net/manual/en/datetime.format.php
	 */
	protected string $logDateFormat = 'Y-m-d H:i:s';

	/**
	 * The visibility of the toots, one of: `public`, `unlisted`, `private`, `direct`
	 *
	 * @link https://docs.joinmastodon.org/methods/statuses/#form-data-parameters
	 */
	protected string $tootVisibility = 'public';

	/**
	 * An optional path to a build directory
	 */
	protected string|null $buildDir = null;

	/**
	 * An optional path to a data directory
	 */
	protected string|null $dataDir = null;

	/**
	 * Sets the Mastodon instance URL
	 */
	protected function set_instance(string $instance):void{
		$this->instance = rtrim($instance, '/');
	}

	/**
	 * Checks and sets the log level
	 */
	protected function set_loglevel(string $loglevel):void{
		$loglevel = strtolower($loglevel);

		if(!in_array($loglevel, ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'])){
			throw new InvalidArgumentException(sprintf('invalid loglevel: "%s"', $loglevel));
		}

		$this->loglevel = $loglevel;
	}

	/**
	 * Checks and sets the toot visibility (public, unlisted, private, direct)
	 */
	protected function set_tootVisibility(string $tootVisibility):void{
		$tootVisibility = strtolower($tootVisibility);

		if(!in_array($tootVisibility, ['public', 'unlisted', 'private', 'direct'])){
			throw new InvalidArgumentException(sprintf('invalid toot visibility: "%s"', $tootVisibility));
		}

		$this->tootVisibility = $tootVisibility;
	}

	/**
	 * Sets the build directory - creates it if it doesn't exist
	 */
	protected function set_buildDir(string $buildDir):void{
		$this->buildDir = Util::mkdir($buildDir);
	}

	/**
	 * Sets the data directory - creates it if it doesn't exist
	 */
	protected function set_dataDir(string $dataDir):void{
		$this->dataDir = Util::mkdir($dataDir);
	}

}
