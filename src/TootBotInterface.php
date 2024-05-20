<?php
/**
 * Interface TootBotInterface
 *
 * @created      03.02.2023
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2023 smiley
 * @license      MIT
 */

namespace PHPTootBot\PHPTootBot;

/**
 *
 */
interface TootBotInterface{

	/**
	 * Creates and submits a new post generated from the given dataset
	 *
	 * This method shall be called from the actions-runner (or cron job)
	 */
	public function post():static;

}
