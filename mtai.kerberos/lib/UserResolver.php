<?php

namespace Mtai\Kerberos;

use Bitrix\Main\UserTable;

/**
 * Поиск пользователя портала по Kerberos principal.
 *
 * Порядок: точный логин (без домена и сферы), затем логин в нижнем
 * регистре (страховка для БД с чувствительным к регистру collation),
 * затем e-mail = UPN (в AD UPN обычно совпадает с рабочей почтой).
 */
final class UserResolver
{
	private const SELECT = ['ID', 'LOGIN', 'ACTIVE', 'EMAIL'];

	public static function resolve(Principal $principal): ?array
	{
		foreach ($principal->loginVariants() as $login)
		{
			$row = UserTable::getRow([
				'select' => self::SELECT,
				'filter' => ['=LOGIN' => $login],
			]);
			if ($row !== null)
			{
				return $row;
			}
		}

		if (Config::mapByEmail() && $principal->hasRealm())
		{
			$row = UserTable::getRow([
				'select' => self::SELECT,
				'filter' => ['=EMAIL' => $principal->upn],
			]);
			if ($row !== null)
			{
				return $row;
			}
		}

		return null;
	}
}
