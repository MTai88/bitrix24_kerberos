<?php

namespace Mtai\Kerberos;

/**
 * Проверка адреса клиента по списку разрешённых сетей.
 *
 * Список (опция ip_allow): по одной записи в строке, IP или CIDR
 * (10.0.0.0/8, 192.168.1.5). Пустой список — разрешено всем.
 */
final class IpFilter
{
	public static function allowed(string $ip, array $nets): bool
	{
		if ($nets === [] || $ip === '')
		{
			return true;
		}

		$ipBin = @inet_pton($ip);
		if ($ipBin === false)
		{
			return false;
		}

		foreach ($nets as $net)
		{
			if (self::match($ipBin, $net))
			{
				return true;
			}
		}

		return false;
	}

	private static function match(string $ipBin, string $net): bool
	{
		$net = trim($net);
		if ($net === '')
		{
			return false;
		}

		if (strpos($net, '/') === false)
		{
			$netBin = @inet_pton($net);

			return $netBin !== false && $netBin === $ipBin;
		}

		[$base, $prefix] = explode('/', $net, 2);
		$prefix = (int)$prefix;
		$baseBin = @inet_pton(trim($base));
		if ($baseBin === false || $prefix < 0)
		{
			return false;
		}

		$bits = strlen($ipBin) * 8;
		if ($prefix > $bits || strlen($baseBin) !== strlen($ipBin))
		{
			return false;
		}

		if ($prefix === 0)
		{
			return true;
		}

		$mask = str_repeat("\xff", intdiv($prefix, 8));
		if ($prefix % 8 !== 0)
		{
			$mask .= chr(0xff << (8 - $prefix % 8));
		}

		return (substr($ipBin, 0, strlen($mask)) & $mask) === (substr($baseBin, 0, strlen($mask)) & $mask);
	}
}
