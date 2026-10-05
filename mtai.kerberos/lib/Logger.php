<?php

namespace Mtai\Kerberos;

use Bitrix\Main\Application;

/**
 * Журнал решений SSO: upload/mtai.kerberos/sso.log.
 *
 * Пишется только при включённой опции debug. Каждая строка:
 *   дата \t событие \t JSON-контекст
 */
final class Logger
{
	private const MAX_SIZE = 1048576; // 1 МБ, потом ротация в .old

	public static function log(string $event, array $context = []): void
	{
		if (!Config::debug())
		{
			return;
		}

		$file = self::filePath();
		if ($file === null)
		{
			return;
		}

		CheckDirPath(dirname($file) . '/');
		if (is_file($file) && filesize($file) > self::MAX_SIZE)
		{
			rename($file, $file . '.old');
		}

		$line = date('Y-m-d H:i:s') . "\t" . $event;
		if ($context !== [])
		{
			$line .= "\t" . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
		}

		file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX);
	}

	public static function filePath(): ?string
	{
		$root = Application::getDocumentRoot();

		return $root !== '' ? $root . '/upload/mtai.kerberos/sso.log' : null;
	}

	/**
	 * Последние строки журнала (для диагностической страницы).
	 *
	 * @return string[]
	 */
	public static function tail(int $lines = 50): array
	{
		$file = self::filePath();
		if ($file === null || !is_file($file))
		{
			return [];
		}

		$content = file_get_contents($file);
		if ($content === false || trim($content) === '')
		{
			return [];
		}

		return array_slice(explode("\n", rtrim($content)), -$lines);
	}
}
