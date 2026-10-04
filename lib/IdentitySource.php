<?php

namespace Mtai\Kerberos;

use Bitrix\Main\Server;

/**
 * Читает проверенное имя пользователя из $_SERVER.
 *
 * REMOTE_USER задают mod_auth_gssapi (Apache) и spnego-модули nginx;
 * за прокси или в тестовом стенде имя может приходить заголовком
 * (X-Remote-User от Apache-фронта, X-Krb-User на стенде) — опция
 * header_name. Значение произвольного заголовка приходит в $_SERVER
 * с префиксом HTTP_ и минусами, заменёнными на подчёркивания.
 */
final class IdentitySource
{
	public static function read(Server $server): string
	{
		$name = Config::headerName();

		$value = trim((string)$server->get($name));
		if ($value !== '')
		{
			return $value;
		}

		if ($name === 'REMOTE_USER')
		{
			// Apache в режиме CGI/FPM кладёт переменную в REDIRECT_REMOTE_USER
			return trim((string)$server->get('REDIRECT_REMOTE_USER'));
		}

		$httpKey = 'HTTP_' . str_replace('-', '_', $name);

		return trim((string)$server->get($httpKey));
	}
}
