<?php
/**
 *
 * ed2k. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2024, RebeldeMule
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = [];
}

// DEVELOPERS PLEASE NOTE
//
// All language files should use UTF-8 as their encoding and the files must not contain a BOM.
//
// Placeholders can now contain order information, e.g. instead of
// 'Page %s of %s' you can (and should) write 'Page %1$s of %2$s', this allows
// translators to re-order the output of data while ensuring it remains correct
//
// You do not need this where single placeholders are used, e.g. 'Message %d' is fine
// equally where a string contains only two placeholders which are used to wrap text
// in a url you again do not need to specify an order e.g., 'Click %sHERE%s' is fine
//
// Some characters you may want to copy&paste:
// ’ » “ ” …
//

$lang = array_merge($lang, [
	'ED2K_BTN_TITLE' => 'View/Copy selected links',
	'ED2K_ALT_ED2K'=> 'eD2K',
	'ED2K_ALT_MAGNET' => 'Magnet',
	'ED2K_MODAL_CLOSE' => 'Close',
	'ED2K_MODAL_TITLE' => 'Selected links',
	'ED2K_MODAL_TEXTAREA_LABEL' => 'List of selected links',
	'ED2K_MODAL_SEND' => 'Send links to application',
	'ED2K_MODAL_COPY' => 'Copy to clipboard',
	'ED2K_MODAL_COPIED' => 'Copied!',

]);
