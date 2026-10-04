<?php

namespace Mtai\Kerberos;

use Bitrix\Main\Config\Option;

/**
 * Типизированный доступ к опциям модуля с кешированием на запрос.
 */
final class Config
{
	public const MODULE_ID = 'mtai.kerberos';

	/** Ключ сессии: автологин подавлен (пользователь вышел). */
	public const SESSION_NO_AUTOLOGIN = 'MTAI_KERBEROS_NO_AUTOLOGIN';

	/** Cookie-дубликат флага подавления — переживает уничтожение сессии. */
	public const COOKIE_NO_AUTOLOGIN = 'MTAI_KRB_NOAUTO';

	/** GET-параметр принудительного повторного SSO-входа (?krb_relogin=1). */
	public const PARAM_RELOGIN = 'krb_relogin';

	private static ?array $options = null;

	private static function load(): array
	{
		if (self::$options === null)
		{
			$defaults = [];
			$defaultFile = dirname(__DIR__) . '/default_option.php';
			if (is_file($defaultFile))
			{
				$defaults = (array)(include $defaultFile);
			}

			$stored = [];
			try
			{
				$stored = Option::getForModule(self::MODULE_ID);
			}
			catch (\Throwable)
			{
				// модуль ещё не установлен — используются значения по умолчанию
			}

			self::$options = array_merge($defaults, $stored);
		}

		return self::$options;
	}

	public static function get(string $name, string $default = ''): string
	{
		$options = self::load();

		return (string)($options[$name] ?? $default);
	}

	public static function getBool(string $name): bool
	{
		return strtoupper(self::get($name)) === 'Y';
	}

	// --- Основное ---

	public static function enabled(): bool
	{
		return self::getBool('enabled');
	}

	/**
	 * Имя переменной $_SERVER с проверенным пользователем: REMOTE_USER
	 * или доверенный заголовок (X-Remote-User, X-Krb-User, ...).
	 */
	public static function headerName(): string
	{
		$name = strtoupper(trim(self::get('header_name')));

		return $name !== '' ? $name : 'REMOTE_USER';
	}

	/** Приходит ли имя из заголовка, который клиент теоретически может подменить. */
	public static function isCustomHeader(): bool
	{
		return !in_array(self::headerName(), ['REMOTE_USER'], true);
	}

	public static function requireAuthType(): bool
	{
		return self::getBool('require_auth_type');
	}

	public static function disableAfterLogout(): bool
	{
		return self::getBool('disable_autologin_after_logout');
	}

	// --- Пользователи ---

	public static function mapByEmail(): bool
	{
		return self::getBool('map_by_email');
	}

	public static function createUsers(): bool
	{
		return self::getBool('create_users');
	}

	/**
	 * @return int[]
	 */
	public static function newUserGroups(): array
	{
		$raw = self::get('new_user_groups');

		return array_values(array_filter(array_map('intval', explode(',', $raw))));
	}

	// --- Безопасность ---

	/**
	 * Разрешённые сферы (верхний регистр, без пробелов).
	 *
	 * @return string[]
	 */
	public static function realms(): array
	{
		return array_values(array_filter(array_map(
			static fn (string $realm): string => strtoupper(trim($realm)),
			explode(',', self::get('realms'))
		)));
	}

	/**
	 * Сети, из которых принимается SSO.
	 *
	 * @return string[]
	 */
	public static function ipAllowList(): array
	{
		return array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', self::get('ip_allow')))));
	}

	// --- Диагностика ---

	public static function debug(): bool
	{
		return self::getBool('debug');
	}
}
