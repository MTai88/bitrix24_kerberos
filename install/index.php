<?php
/**
 * Установщик модуля mtai.kerberos.
 *
 * Модуль не создаёт таблиц: вся конфигурация — в опциях. При установке
 * регистрируется модуль и обработчики событий:
 *   OnBeforeProlog        — точка входа SSO (сессия и $USER уже созданы);
 *   OnAfterUserLogout     — подавление автологина после выхода;
 *   OnAfterUserAuthorize  — сброс подавления при новом входе;
 *   OnBuildGlobalMenu     — пункт «Диагностика» в админ-меню.
 */

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;

Loc::loadMessages(__FILE__);

class mtai_kerberos extends CModule
{
	public $MODULE_ID = 'mtai.kerberos';
	public $MODULE_VERSION;
	public $MODULE_VERSION_DATE;
	public $MODULE_NAME;
	public $MODULE_DESCRIPTION;
	public $MODULE_GROUP_RIGHTS = 'R';

	public function __construct()
	{
		$arModuleVersion = [];
		include __DIR__ . '/version.php';

		$this->MODULE_VERSION = (string)($arModuleVersion['VERSION'] ?? '');
		$this->MODULE_VERSION_DATE = (string)($arModuleVersion['VERSION_DATE'] ?? '');

		$this->MODULE_NAME = Loc::getMessage('MTAI_KERBEROS_MODULE_NAME');
		$this->MODULE_DESCRIPTION = Loc::getMessage('MTAI_KERBEROS_MODULE_DESCRIPTION');
		$this->PARTNER_NAME = Loc::getMessage('MTAI_KERBEROS_PARTNER_NAME');
		$this->PARTNER_URI = Loc::getMessage('MTAI_KERBEROS_PARTNER_URI');
	}

	public function DoInstall(): void
	{
		global $APPLICATION;

		$this->InstallDB();
		$this->InstallEvents();
		$this->InstallFiles();

		$GLOBALS['errors'] = $this->errors;
	}

	public function DoUninstall(): void
	{
		$this->UnInstallFiles();
		$this->UnInstallEvents();
		$this->UnInstallDB();
	}

	public function InstallDB(): bool
	{
		ModuleManager::registerModule($this->MODULE_ID);

		return true;
	}

	public function UnInstallDB(): bool
	{
		// Пользователей, созданных модулем, не трогаем — это данные портала.
		\Bitrix\Main\Config\Option::delete($this->MODULE_ID);
		ModuleManager::unRegisterModule($this->MODULE_ID);

		return true;
	}

	public function InstallEvents(): bool
	{
		// Классы lib/ автозагружаются только после включения модуля.
		\Bitrix\Main\Loader::includeModule($this->MODULE_ID);
		\Mtai\Kerberos\SsoHandler::register();

		return true;
	}

	public function UnInstallEvents(): bool
	{
		if (\Bitrix\Main\Loader::includeModule($this->MODULE_ID))
		{
			\Mtai\Kerberos\SsoHandler::unregister();
		}

		return true;
	}

	public function InstallFiles(): bool
	{
		// Диагностическая страница: в /bitrix/admin кладём заглушку,
		// которая подключает реальную страницу из каталога модуля —
		// так разрешение языковых файлов идёт по пути модуля
		// (lang/ru/install/admin/...), как у штатных модулей.
		$stubPath = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin/mtai_kerberos_diag.php';
		CheckDirPath(dirname($stubPath) . '/');
		file_put_contents(
			$stubPath,
			'<?php require($_SERVER["DOCUMENT_ROOT"] . "/local/modules/mtai.kerberos/install/admin/mtai_kerberos_diag.php");'
			. "\n"
		);

		return true;
	}

	public function UnInstallFiles(): bool
	{
		if (is_file($_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin/mtai_kerberos_diag.php'))
		{
			unlink($_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin/mtai_kerberos_diag.php');
		}

		return true;
	}
}
