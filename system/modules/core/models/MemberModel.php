<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2013 Leo Feyer
 *
 * @package Core
 * @link    https://contao.org
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */


/**
 * Run in a custom namespace, so the class can be replaced
 */
namespace Contao;


/**
 * Reads and writes members
 *
 * @package   Models
 * @author    Leo Feyer <https://github.com/leofeyer>
 * @copyright Leo Feyer 2005-2013
 */
class MemberModel extends \Model
{

	/**
	 * Table name
	 * @var string
	 */
	protected static $strTable = 'tl_member';


	/**
	 * Find an active member by his/her e-mail-address and username
	 *
	 * @param string $strEmail    The e-mail address
	 * @param string $strUsername The username
	 * @param array  $arrOptions  An optional options array
	 *
	 * @return \Model|null The model or null if there is no member
	 */
	public static function findActiveByEmailAndUsername($strEmail, $strUsername=null, array $arrOptions=array())
	{
		$time = time();
		$t = static::$strTable;

		$arrColumns = array("$t.email=? AND $t.login=1 AND ($t.start='' OR $t.start<$time) AND ($t.stop='' OR $t.stop>$time) AND $t.disable=''");

		if ($strUsername !== null)
		{
			$arrColumns[] = "$t.username=?";
		}

		return static::findOneBy($arrColumns, array($strEmail, $strUsername), $arrOptions);
	}

	/**
	 * Dont create a home directory
	 */
	const NO_HOMEDIR = '30'; /* bin2hex(0) */


	/**
	 * The password in arrData['password'] is not crypted
	 */
	const ENCRYPT_PASSWORD = 1;

	/**
	 * The password in arrData['password'] is crypted
	 */
	const DONT_ENCRYPT_PASSWORD = 0;


	/**
	 * Create a new user
	 *
	 * @param array   $arrData The data which are to use for creation
	 * @param integer homedir ID or MemberModel::NO_HOMEDIR if no homedir desired
	 * @param object  callee for the hooks
	 *
	 * @return \Contao\MemberModel|null if something goes wrong (not implemented)
	 */
	public static function createNewMember(&$arrData, $entcryptPassword = MemberModel::DONT_ENCRYPT_PASSWORD,  $reg_homeDir = MemberModel::NO_HOMEDIR, \Widged &$caller = null)
	{
		if (($entcryptPassword == self::ENCRYPT_PASSWORD) && $arrData['password'])
		{
			$arrData['password'] = \Encryption::hash($arrData['password']);
		}

		$objNewUser = new \MemberModel();
		$objNewUser->setRow($arrData);
		$objNewUser->save();

		$insertId = $objNewUser->id;

		// Assign home directory
		if ($reg_homeDir != self::NO_HOMEDIR)
		{
			$objHomeDir = \FilesModel::findByUuid(hex2bin($reg_homeDir));

			if ($objHomeDir !== null)
			{
				\System::importStatic('Files');
				$strUserDir = $arrData['username'] ?: 'user_' . $insertId;

				// Add the user ID if the directory exists
				while (is_dir(TL_ROOT . '/' . $objHomeDir->path . '/' . $strUserDir))
				{
					$strUserDir .= '_' . $insertId;
				}

				new \Folder($objHomeDir->path . '/' . $strUserDir);
				$objUserDir = \FilesModel::findByPath($objHomeDir->path . '/' . $strUserDir);

				// Save the folder ID
				$objNewUser->assignDir = 1;
				$objNewUser->homeDir = $objUserDir->uuid;
				$objNewUser->save();
			}
		}

		// HOOK: send insert ID and user data
		if (isset($GLOBALS['TL_HOOKS']['createNewUser']) && is_array($GLOBALS['TL_HOOKS']['createNewUser']))
		{
			foreach ($GLOBALS['TL_HOOKS']['createNewUser'] as $callback)
			{
				$objCallback = System::importStatic($callback[0]);
				$objCallback->$callback[1]($insertId, $arrData, $caller);
			}
		}

		return $objNewUser;
	}
}
