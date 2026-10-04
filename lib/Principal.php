<?php

namespace Mtai\Kerberos;

/**
 * Имя пользователя, проверенное веб-сервером (Kerberos/NTLM principal).
 *
 * Поддерживаемые форматы:
 *   user@REALM         — Kerberos User Principal Name (AD)
 *   DOM\user           — NTLM / down-level logon name
 *   user               — логин без сферы
 */
final class Principal
{
	/** Логин без домена и сферы. */
	public readonly string $login;

	/** Сфера Kerberos в верхнем регистре (может быть пустой). */
	public readonly string $realm;

	/** NetBIOS-домен из формата DOM\user (может быть пустым). */
	public readonly string $domain;

	/** UPN целиком (user@REALM), если сфера была. */
	public readonly string $upn;

	private function __construct(string $login, string $realm, string $domain)
	{
		$this->login = $login;
		$this->realm = $realm;
		$this->domain = $domain;
		$this->upn = $realm !== '' ? $login . '@' . $realm : $login;
	}

	/**
	 * Разбирает значение REMOTE_USER. Возвращает null, если строка
	 * не похожа на имя пользователя (мусор, service-ticket и т.п.).
	 */
	public static function parse(string $raw): ?self
	{
		// некоторые конфигурации Apache передают значение в кавычках
		$value = trim(trim($raw), '"\'');
		if ($value === '')
		{
			return null;
		}

		$realm = '';
		$domain = '';

		$parts = explode('@', $value);
		$login = array_shift($parts);
		if ($parts !== [])
		{
			// берём только последнюю @-часть (совместимость с user@mail@realm)
			$realm = strtoupper(trim((string)end($parts)));
		}

		$slashPos = strpos($login, '\\');
		if ($slashPos !== false)
		{
			$domain = strtoupper(substr($login, 0, $slashPos));
			$login = substr($login, $slashPos + 1);
		}

		$login = trim($login);
		if (!preg_match('/^[a-zA-Z0-9._\-]{1,100}$/', $login))
		{
			return null;
		}

		return new self($login, $realm, $domain);
	}

	public function hasRealm(): bool
	{
		return $this->realm !== '';
	}

	/** Разрешена ли сфера principal'а настроенным списком (пустой список = любые). */
	public function realmAllowed(): bool
	{
		$realms = Config::realms();
		if ($realms === [] || !$this->hasRealm())
		{
			return true;
		}

		return in_array($this->realm, $realms, true);
	}

	/** Похоже ли UPN на e-mail (для поля EMAIL нового пользователя). */
	public function looksLikeEmail(): bool
	{
		return $this->hasRealm() && check_email($this->upn);
	}

	/**
	 * Варианты логина для поиска пользователя (точный и в нижнем регистре).
	 *
	 * @return string[]
	 */
	public function loginVariants(): array
	{
		$variants = [$this->login];
		$lower = mb_strtolower($this->login);
		if ($lower !== $this->login)
		{
			$variants[] = $lower;
		}

		return $variants;
	}
}
