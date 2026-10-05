<?php

namespace Mtai\Kerberos;

use Bitrix\Main\Application;
use Bitrix\Main\Authentication\Context as AuthContext;
use Bitrix\Main\Authentication\Method;
use Bitrix\Main\HttpRequest;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

/**
 * Обработчики событий модуля.
 *
 * onBeforeProlog — точка входа SSO. Срабатывает на каждом хите после
 * инициализации сессии и $USER. Если пользователь ещё не авторизован,
 * а веб-сервер подтвердил его личность (REMOTE_USER / доверенный
 * заголовок после SPNEGO), модуль находит пользователя портала
 * и вызывает $USER->Authorize() с методом входа External.
 *
 * После выхода (OnAfterUserLogout) автологин подавляется флагом в
 * сессии + cookie, иначе браузер, автоматически отправляющий Kerberos-
 * билет, заводил бы пользователя обратно мгновенно. Флаг снимается
 * повторным входом (любым способом) или параметром ?krb_relogin=1
 * («войти под доменной учёткой» со страницы входа).
 */
final class SsoHandler
{
	/** Механизмы серверной аутентификации для проверки AUTH_TYPE. */
	private const AUTH_TYPES = ['NEGOTIATE', 'KERBEROS', 'NTLM'];

	public static function register(): void
	{
		$manager = \Bitrix\Main\EventManager::getInstance();
		$module = Config::MODULE_ID;
		$class = static::class;

		$manager->registerEventHandlerCompatible('main', 'OnBeforeProlog', $module, $class, 'onBeforeProlog');
		$manager->registerEventHandlerCompatible('main', 'OnAfterUserLogout', $module, $class, 'onAfterUserLogout');
		$manager->registerEventHandlerCompatible('main', 'OnAfterUserAuthorize', $module, $class, 'onAfterUserAuthorize');
		$manager->registerEventHandlerCompatible('main', 'OnBuildGlobalMenu', $module, $class, 'onBuildGlobalMenu');
	}

	public static function unregister(): void
	{
		$manager = \Bitrix\Main\EventManager::getInstance();
		$module = Config::MODULE_ID;
		$class = static::class;

		$manager->unRegisterEventHandler('main', 'OnBeforeProlog', $module, $class, 'onBeforeProlog');
		$manager->unRegisterEventHandler('main', 'OnAfterUserLogout', $module, $class, 'onAfterUserLogout');
		$manager->unRegisterEventHandler('main', 'OnAfterUserAuthorize', $module, $class, 'onAfterUserAuthorize');
		$manager->unRegisterEventHandler('main', 'OnBuildGlobalMenu', $module, $class, 'onBuildGlobalMenu');
	}

	// ------------------------------------------------------------------
	// SSO
	// ------------------------------------------------------------------

	public static function onBeforeProlog(): void
	{
		global $USER;

		if (!Config::enabled())
		{
			return;
		}
		if (!($USER instanceof \CUser) || $USER->IsAuthorized())
		{
			return;
		}

		$context = Application::getInstance()->getContext();
		$server = $context->getServer();
		$request = $context->getRequest();

		$rawIdentity = IdentitySource::read($server);
		if ($rawIdentity === '')
		{
			// обычный анонимный запрос без SPNEGO — передаём штатному логину
			return;
		}

		if (self::isSuppressed($request))
		{
			Logger::log('skip_after_logout', ['identity' => $rawIdentity]);

			return;
		}

		if (Config::requireAuthType())
		{
			$authType = strtoupper(trim((string)$server->get('AUTH_TYPE')));
			if (!in_array($authType, self::AUTH_TYPES, true))
			{
				Logger::log('skip_auth_type', ['auth_type' => $authType ?: null]);

				return;
			}
		}

		$ip = (string)$server->getRemoteAddr();
		if (!IpFilter::allowed($ip, Config::ipAllowList()))
		{
			Logger::log('deny_ip', ['ip' => $ip]);

			return;
		}

		$principal = Principal::parse($rawIdentity);
		if ($principal === null)
		{
			Logger::log('deny_unparsable', ['identity' => $rawIdentity]);

			return;
		}

		if (!$principal->realmAllowed())
		{
			Logger::log('deny_realm', ['realm' => $principal->realm, 'allowed' => Config::realms()]);

			return;
		}

		$user = UserResolver::resolve($principal);
		if ($user === null && Config::createUsers())
		{
			$user = UserProvisioner::create($principal);
		}
		if ($user === null)
		{
			Logger::log('deny_user_not_found', ['login' => $principal->login, 'upn' => $principal->upn]);

			return;
		}

		if ($user['ACTIVE'] !== 'Y')
		{
			Logger::log('deny_user_inactive', ['id' => $user['ID']]);

			return;
		}

		$authContext = (new AuthContext())
			->setUserId((int)$user['ID'])
			->setMethod(Method::External)
			->setExternalAuthId(Config::MODULE_ID)
		;
		$USER->Authorize($authContext);

		Logger::log('authorized', [
			'id' => (int)$user['ID'],
			'login' => $user['LOGIN'],
			'upn' => $principal->upn,
		]);
	}

	// ------------------------------------------------------------------
	// Управление подавлением автологина
	// ------------------------------------------------------------------

	public static function onAfterUserLogout(array $user = []): void
	{
		if (!Config::enabled() || !Config::disableAfterLogout())
		{
			return;
		}

		// флаг и в сессии, и в cookie: выход может уничтожить сессию,
		// а cookie живёт до закрытия браузера и переживает это
		$_SESSION[Config::SESSION_NO_AUTOLOGIN] = true;
		if (!headers_sent())
		{
			self::sendCookie(Config::COOKIE_NO_AUTOLOGIN, '1', 0);
		}

		Logger::log('logout_suppression_on', ['id' => (int)($user['USER_ID'] ?? 0)]);
	}

	public static function onAfterUserAuthorize(array $user = []): void
	{
		self::clearSuppression();
	}

	private static function isSuppressed(HttpRequest $request): bool
	{
		// принудительный повторный SSO-вход: ?krb_relogin=1
		if ($request->get(Config::PARAM_RELOGIN) !== null)
		{
			self::clearSuppression();

			return false;
		}

		return !empty($_SESSION[Config::SESSION_NO_AUTOLOGIN])
			|| !empty($_COOKIE[Config::COOKIE_NO_AUTOLOGIN]);
	}

	private static function clearSuppression(): void
	{
		unset($_SESSION[Config::SESSION_NO_AUTOLOGIN]);
		if (!headers_sent() && !empty($_COOKIE[Config::COOKIE_NO_AUTOLOGIN]))
		{
			self::sendCookie(Config::COOKIE_NO_AUTOLOGIN, '', time() - 3600);
		}
	}

	private static function sendCookie(string $name, string $value, int $expires): void
	{
		setcookie($name, $value, [
			'expires' => $expires,
			'path' => '/',
			'httponly' => true,
			'samesite' => 'Lax',
		]);
	}

	// ------------------------------------------------------------------
	// Админка
	// ------------------------------------------------------------------

	public static function onBuildGlobalMenu(&$aGlobalMenu, &$aModuleMenu): void
	{
		$aModuleMenu[] = [
			'parent_menu' => 'global_menu_settings',
			'sort' => 3600,
			'url' => 'mtai_kerberos_diag.php?lang=' . LANGUAGE_ID,
			'text' => 'Kerberos SSO: ' . Loc::getMessage('MTAI_KERBEROS_ADMIN_TITLE_SHORT'),
			'title' => Loc::getMessage('MTAI_KERBEROS_ADMIN_TITLE'),
			'icon' => 'sys_menu_icon',
			'page_icon' => 'sys_page_icon',
		];
	}
}
