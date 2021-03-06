<?php
/**
 * @package    Molajo
 * @copyright  2012 Individual Molajo Contributors. All rights reserved.
 * @license    GNU GPL v 2, or later and MIT, see License folder
 */
namespace Molajo\Includer;

use Molajo\Includer;

defined('MOLAJO') or die;

/**
 * Tag
 *
 * @package     Molajo
 * @subpackage  Includer
 * @since       1.0
 */
Class TagIncluder extends Includer
{
	/**
	 * process
	 *
	 * @return mixed
	 * @since   1.0
	 */
	public function __construct($name = null, $type = null)
	{
		return 'still need to do Tag Includer';
	}

	public function render($tag)
	{

		foreach (x::y($tag) as $item) {
			$buffer .= $includer->etc($thing;
		}

		return $buffer;
	}
}
