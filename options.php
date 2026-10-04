<?php
/**
 * Настройки модуля mtai.kerberos.
 *
 * Включается обёрткой /bitrix/admin/settings.php (mid=mtai.kerberos),
 * поэтому prolog/epilog не подключаются.
 *
 * Вкладки:
 *   Основные      — включение, источник имени пользователя, поведение
 *                   после выхода;
 *   Пользователи  — сопоставление с пользователями портала, автосоздание;
 *   Безопасность  — разрешённые сферы и сети;
 *   Диагностика   — журнал решений и ссылка на страницу диагностики.
 */

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\GroupTable;
use Bitrix\Main\Localization\Loc;

/** @global CMain $APPLICATION */
/** @global string $mid */

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

Loc::loadMessages(__FILE__);

$moduleId = 'mtai.kerberos';

\Bitrix\Main\Loader::includeModule($moduleId);

$POST_RIGHT = $APPLICATION->GetGroupRight($moduleId);
if ($POST_RIGHT < 'R')
{
	return;
}

$request = Application::getInstance()->getContext()->getRequest();

/** @var array<string, string> name => тип поля (string|int|checkbox) */
$editableOptions = [
	'header_name' => 'string',
	'require_auth_type' => 'checkbox',
	'disable_autologin_after_logout' => 'checkbox',
	'map_by_email' => 'checkbox',
	'create_users' => 'checkbox',
	'realms' => 'string',
	'ip_allow' => 'text',
	'debug' => 'checkbox',
];

$note = null;

if (
	$request->isPost()
	&& check_bitrix_sessid()
	&& $POST_RIGHT >= 'W'
	&& ($request->getPost('Update') !== null || $request->getPost('save') !== null || $request->getPost('apply') !== null)
)
{
	Option::set($moduleId, 'enabled', $request->getPost('enabled') !== null ? 'Y' : 'N');

	foreach ($editableOptions as $name => $type)
	{
		if ($type === 'checkbox')
		{
			Option::set($moduleId, $name, $request->getPost($name) !== null ? 'Y' : 'N');
			continue;
		}

		$value = trim((string)$request->getPost($name));
		Option::set($moduleId, $name, $value);
	}

	$groups = (array)$request->getPost('new_user_groups');
	Option::set($moduleId, 'new_user_groups', implode(',', array_filter(array_map('intval', $groups))));

	$note = Loc::getMessage('MTAI_KERBEROS_SAVED');
}

$optionValue = static function (string $name, string $default = '') use ($moduleId): string {
	return htmlspecialcharsbx(Option::get($moduleId, $name, $default));
};

$checkboxRow = static function (string $name, ?string $label, string $hint = '') use ($moduleId): string {
	$checked = Option::get($moduleId, $name) === 'Y' ? ' checked' : '';
	$html = '<tr><td width="40%">'
		. '<label for="' . $name . '">' . ($label ?? Loc::getMessage('MTAI_KERBEROS_OPT_' . mb_strtoupper($name))) . ':</label></td>'
		. '<td width="60%"><input type="checkbox" name="' . $name . '" id="' . $name . '" value="Y"' . $checked . '>';
	if ($hint !== '')
	{
		$html .= '<br><small>' . $hint . '</small>';
	}

	return $html . '</td></tr>';
};

$groupOptions = [];
$groupIterator = GroupTable::getList([
	'select' => ['ID', 'NAME'],
	'order' => ['ID' => 'ASC'],
]);
while ($row = $groupIterator->fetch())
{
	$groupOptions[(string)$row['ID']] = '[' . $row['ID'] . '] ' . $row['NAME'];
}
$selectedGroups = array_map('strval', \Mtai\Kerberos\Config::newUserGroups());

$aTabs = [
	[
		'DIV' => 'tab_main',
		'TAB' => Loc::getMessage('MTAI_KERBEROS_TAB_MAIN'),
		'ICON' => 'main_settings',
		'TITLE' => Loc::getMessage('MTAI_KERBEROS_TAB_MAIN_TITLE'),
	],
	[
		'DIV' => 'tab_users',
		'TAB' => Loc::getMessage('MTAI_KERBEROS_TAB_USERS'),
		'ICON' => 'main_settings',
		'TITLE' => Loc::getMessage('MTAI_KERBEROS_TAB_USERS_TITLE'),
	],
	[
		'DIV' => 'tab_security',
		'TAB' => Loc::getMessage('MTAI_KERBEROS_TAB_SECURITY'),
		'ICON' => 'main_settings',
		'TITLE' => Loc::getMessage('MTAI_KERBEROS_TAB_SECURITY_TITLE'),
	],
	[
		'DIV' => 'tab_debug',
		'TAB' => Loc::getMessage('MTAI_KERBEROS_TAB_DEBUG'),
		'ICON' => 'main_settings',
		'TITLE' => Loc::getMessage('MTAI_KERBEROS_TAB_DEBUG_TITLE'),
	],
];
$tabControl = new CAdminTabControl('tabControl', $aTabs);
?>
<?php if ($note !== null): ?>
	<?= CAdminMessage::ShowNote($note) ?>
<?php endif; ?>
<?php if (
	\Mtai\Kerberos\Config::enabled()
	&& \Mtai\Kerberos\Config::isCustomHeader()
	&& \Mtai\Kerberos\Config::ipAllowList() === []
): ?>
	<?= CAdminMessage::ShowMessage(Loc::getMessage('MTAI_KERBEROS_WARN_SPOOFABLE')) ?>
<?php endif; ?>

<form method="post" action="<?= $APPLICATION->GetCurPage() ?>?mid=<?= urlencode($moduleId) ?>&amp;lang=<?= LANGUAGE_ID ?>">
	<?= bitrix_sessid_post() ?>
	<?php $tabControl->Begin(); ?>

	<?php // ---------- Вкладка: основные ---------- ?>
	<?php $tabControl->BeginNextTab(); ?>
	<?= $checkboxRow(
		'enabled',
		Loc::getMessage('MTAI_KERBEROS_OPT_ENABLED'),
		Loc::getMessage('MTAI_KERBEROS_OPT_ENABLED_HINT')
	) ?>
	<tr>
		<td width="40%"><label for="header_name"><?= Loc::getMessage('MTAI_KERBEROS_OPT_HEADER_NAME') ?>:</label></td>
		<td width="60%">
			<input type="text" size="30" name="header_name" id="header_name" value="<?= $optionValue('header_name', 'REMOTE_USER') ?>">
			<br><small><?= Loc::getMessage('MTAI_KERBEROS_OPT_HEADER_NAME_HINT') ?></small>
		</td>
	</tr>
	<?= $checkboxRow(
		'require_auth_type',
		Loc::getMessage('MTAI_KERBEROS_OPT_REQUIRE_AUTH_TYPE'),
		Loc::getMessage('MTAI_KERBEROS_OPT_REQUIRE_AUTH_TYPE_HINT')
	) ?>
	<?= $checkboxRow(
		'disable_autologin_after_logout',
		Loc::getMessage('MTAI_KERBEROS_OPT_DISABLE_AFTER_LOGOUT'),
		Loc::getMessage('MTAI_KERBEROS_OPT_DISABLE_AFTER_LOGOUT_HINT')
	) ?>

	<?php // ---------- Вкладка: пользователи ---------- ?>
	<?php $tabControl->BeginNextTab(); ?>
	<?= $checkboxRow(
		'map_by_email',
		Loc::getMessage('MTAI_KERBEROS_OPT_MAP_BY_EMAIL'),
		Loc::getMessage('MTAI_KERBEROS_OPT_MAP_BY_EMAIL_HINT')
	) ?>
	<?= $checkboxRow(
		'create_users',
		Loc::getMessage('MTAI_KERBEROS_OPT_CREATE_USERS'),
		Loc::getMessage('MTAI_KERBEROS_OPT_CREATE_USERS_HINT')
	) ?>
	<tr>
		<td width="40%"><label for="new_user_groups"><?= Loc::getMessage('MTAI_KERBEROS_OPT_NEW_USER_GROUPS') ?>:</label></td>
		<td width="60%">
			<select name="new_user_groups[]" id="new_user_groups" multiple size="7">
				<?php foreach ($groupOptions as $groupId => $label): ?>
					<option value="<?= htmlspecialcharsbx($groupId) ?>"<?= in_array($groupId, $selectedGroups, true) ? ' selected' : '' ?>>
						<?= htmlspecialcharsbx($label) ?>
					</option>
				<?php endforeach; ?>
			</select>
			<br><small><?= Loc::getMessage('MTAI_KERBEROS_OPT_NEW_USER_GROUPS_HINT') ?></small>
		</td>
	</tr>

	<?php // ---------- Вкладка: безопасность ---------- ?>
	<?php $tabControl->BeginNextTab(); ?>
	<tr>
		<td width="40%"><label for="realms"><?= Loc::getMessage('MTAI_KERBEROS_OPT_REALMS') ?>:</label></td>
		<td width="60%">
			<input type="text" size="40" name="realms" id="realms" value="<?= $optionValue('realms') ?>">
			<br><small><?= Loc::getMessage('MTAI_KERBEROS_OPT_REALMS_HINT') ?></small>
		</td>
	</tr>
	<tr>
		<td width="40%" class="adm-detail-valign-top"><label for="ip_allow"><?= Loc::getMessage('MTAI_KERBEROS_OPT_IP_ALLOW') ?>:</label></td>
		<td width="60%">
			<textarea name="ip_allow" id="ip_allow" rows="4" cols="40"><?= $optionValue('ip_allow') ?></textarea>
			<br><small><?= Loc::getMessage('MTAI_KERBEROS_OPT_IP_ALLOW_HINT') ?></small>
		</td>
	</tr>

	<?php // ---------- Вкладка: диагностика ---------- ?>
	<?php $tabControl->BeginNextTab(); ?>
	<?= $checkboxRow(
		'debug',
		Loc::getMessage('MTAI_KERBEROS_OPT_DEBUG'),
		Loc::getMessage('MTAI_KERBEROS_OPT_DEBUG_HINT')
	) ?>
	<tr>
		<td width="40%"><?= Loc::getMessage('MTAI_KERBEROS_DIAG_PAGE') ?>:</td>
		<td width="60%">
			<a href="mtai_kerberos_diag.php?lang=<?= LANGUAGE_ID ?>"><?= Loc::getMessage('MTAI_KERBEROS_DIAG_PAGE_LINK') ?></a>
			<br><small><?= Loc::getMessage('MTAI_KERBEROS_DIAG_PAGE_HINT') ?></small>
		</td>
	</tr>

	<?php $tabControl->Buttons(['btnCancel' => false, 'back_url' => '']); ?>
</form>
<?php $tabControl->End(); ?>
