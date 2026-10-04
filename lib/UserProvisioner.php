<?php

namespace Mtai\Kerberos;

use Bitrix\Main\Security\Random;

/**
 * Создание пользователя портала при первом SSO-входе.
 *
 * Логин — без домена и сферы, e-mail — UPN (если похож на почту),
 * пароль случайный (пользователь входит через SSO; при необходимости
 * администратор может выставить пароль вручную). UPN сохраняется в
 * XML_ID с префиксом модуля для трассировки происхождения учётки.
 */
final class UserProvisioner
{
	public static function create(Principal $principal): ?array
	{
		$password = Random::getString(32, true);

		$email = $principal->looksLikeEmail() ? $principal->upn : '';

		$fields = [
			'LOGIN' => $principal->login,
			'NAME' => $principal->login,
			'EMAIL' => $email,
			'ACTIVE' => 'Y',
			'PASSWORD' => $password,
			'CONFIRM_PASSWORD' => $password,
			'XML_ID' => 'mtai.kerberos|' . $principal->upn,
		];

		$groups = Config::newUserGroups();
		if ($groups !== [])
		{
			$fields['GROUP_ID'] = $groups;
		}

		$user = new \CUser;
		$id = (int)$user->Add($fields);
		if ($id <= 0)
		{
			Logger::log('provision_failed', [
				'upn' => $principal->upn,
				'error' => trim(strip_tags((string)$user->LAST_ERROR)),
			]);

			return null;
		}

		Logger::log('user_created', [
			'id' => $id,
			'login' => $principal->login,
			'upn' => $principal->upn,
		]);

		return [
			'ID' => $id,
			'LOGIN' => $principal->login,
			'ACTIVE' => 'Y',
			'EMAIL' => $email,
		];
	}
}
