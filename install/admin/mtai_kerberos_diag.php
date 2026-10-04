<?php
/**
 * Диагностика Kerberos SSO: что видит PHP от веб-сервера, разбор
 * principal'а, журнал решений модуля.
 */

use Bitrix\Main\Application;
use Bitrix\Main\Localization\Loc;

/** @global CMain $APPLICATION */

defined('ADMIN_MODULE_NAME') || define('ADMIN_MODULE_NAME', 'mtai.kerberos');

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin.php';

Loc::loadMessages(__FILE__);

$moduleId = 'mtai.kerberos';
\Bitrix\Main\Loader::includeModule($moduleId);

$POST_RIGHT = $APPLICATION->GetGroupRight($moduleId);
if ($POST_RIGHT < 'R')
{
	$APPLICATION->AuthForm(Loc::getMessage('MTAI_KERBEROS_ACCESS_DENIED'));
}

$request = Application::getInstance()->getContext()->getRequest();

$parseResult = null;
$parseInput = '';
if ($request->isPost() && check_bitrix_sessid() && $request->getPost('parse_identity') !== null)
{
	$parseInput = trim((string)$request->getPost('identity'));
	$principal = \Mtai\Kerberos\Principal::parse($parseInput);
	if ($principal === null)
	{
		$parseResult = null;
		$parsed = false;
	}
	else
	{
		$parsed = true;
		$parseResult = [
			Loc::getMessage('MTAI_KERBEROS_DIAG_F_LOGIN') => $principal->login,
			Loc::getMessage('MTAI_KERBEROS_DIAG_F_REALM') => $principal->realm,
			Loc::getMessage('MTAI_KERBEROS_DIAG_F_DOMAIN') => $principal->domain,
			Loc::getMessage('MTAI_KERBEROS_DIAG_F_UPN') => $principal->upn,
			Loc::getMessage('MTAI_KERBEROS_DIAG_F_REALM_ALLOWED') => $principal->realmAllowed()
				? Loc::getMessage('MTAI_KERBEROS_YES')
				: Loc::getMessage('MTAI_KERBEROS_NO'),
		];
	}
}

if ($request->isPost() && check_bitrix_sessid() && $request->getPost('clear_log') !== null)
{
	$logFile = \Mtai\Kerberos\Logger::filePath();
	if ($logFile !== null && is_file($logFile))
	{
		unlink($logFile);
	}
}

$server = Application::getInstance()->getContext()->getServer();

$serverVars = [
	'REMOTE_USER' => (string)$server->get('REMOTE_USER'),
	'REDIRECT_REMOTE_USER' => (string)$server->get('REDIRECT_REMOTE_USER'),
	'AUTH_TYPE' => (string)$server->get('AUTH_TYPE'),
	'REMOTE_ADDR' => (string)$server->getRemoteAddr(),
	'HTTPS' => $server->get('HTTPS') ? 'on' : 'off',
];
$authorization = (string)$server->get('HTTP_AUTHORIZATION');
$serverVars['HTTP_AUTHORIZATION'] = $authorization === ''
	? ''
	: (str_starts_with($authorization, 'Negotiate ')
		? 'Negotiate <token ' . strlen($authorization) . ' байт>'
		: substr($authorization, 0, 40) . (strlen($authorization) > 40 ? '…' : ''));

$settings = [
	Loc::getMessage('MTAI_KERBEROS_OPT_ENABLED') => \Mtai\Kerberos\Config::enabled()
		? Loc::getMessage('MTAI_KERBEROS_YES') : Loc::getMessage('MTAI_KERBEROS_NO'),
	Loc::getMessage('MTAI_KERBEROS_OPT_HEADER_NAME') => \Mtai\Kerberos\Config::headerName(),
	Loc::getMessage('MTAI_KERBEROS_OPT_MAP_BY_EMAIL') => \Mtai\Kerberos\Config::mapByEmail()
		? Loc::getMessage('MTAI_KERBEROS_YES') : Loc::getMessage('MTAI_KERBEROS_NO'),
	Loc::getMessage('MTAI_KERBEROS_OPT_CREATE_USERS') => \Mtai\Kerberos\Config::createUsers()
		? Loc::getMessage('MTAI_KERBEROS_YES') : Loc::getMessage('MTAI_KERBEROS_NO'),
	Loc::getMessage('MTAI_KERBEROS_OPT_REALMS') => \Mtai\Kerberos\Config::realms() === []
		? Loc::getMessage('MTAI_KERBEROS_ANY') : implode(', ', \Mtai\Kerberos\Config::realms()),
	Loc::getMessage('MTAI_KERBEROS_OPT_IP_ALLOW') => \Mtai\Kerberos\Config::ipAllowList() === []
		? Loc::getMessage('MTAI_KERBEROS_ANY') : implode(', ', \Mtai\Kerberos\Config::ipAllowList()),
];

$identity = \Mtai\Kerberos\IdentitySource::read($server);

$APPLICATION->SetTitle(Loc::getMessage('MTAI_KERBEROS_ADMIN_TITLE'));

$aContext = [
	[
		'TEXT' => Loc::getMessage('MTAI_KERBEROS_DIAG_SETTINGS'),
		'TITLE' => Loc::getMessage('MTAI_KERBEROS_DIAG_SETTINGS'),
		'LINK' => 'settings.php?mid=mtai.kerberos&lang=' . LANGUAGE_ID,
		'ICON' => 'btn_list',
	],
];
$adminMenu = new CAdminContextMenu($aContext);
$adminMenu->Show();
?>

<?php if (
	\Mtai\Kerberos\Config::enabled()
	&& \Mtai\Kerberos\Config::isCustomHeader()
	&& \Mtai\Kerberos\Config::ipAllowList() === []
): ?>
	<?= CAdminMessage::ShowMessage(Loc::getMessage('MTAI_KERBEROS_WARN_SPOOFABLE')) ?>
<?php endif; ?>

<form method="post" action="<?= $APPLICATION->GetCurPage() ?>?lang=<?= LANGUAGE_ID ?>">
	<?= bitrix_sessid_post() ?>

	<table class="internal" style="width:100%;">
		<tr class="heading">
			<td colspan="2"><?= Loc::getMessage('MTAI_KERBEROS_DIAG_SERVER_VARS') ?></td>
		</tr>
		<?php foreach ($serverVars as $name => $value): ?>
			<tr>
				<td style="width:40%;"><code><?= htmlspecialcharsbx($name) ?></code></td>
				<td><?= $value === '' ? '<i style="color:#999;">—</i>' : '<code>' . htmlspecialcharsbx($value) . '</code>' ?></td>
			</tr>
		<?php endforeach; ?>
		<tr class="heading">
			<td colspan="2"><?= Loc::getMessage('MTAI_KERBEROS_DIAG_IDENTITY') ?></td>
		</tr>
		<tr>
			<td><code><?= htmlspecialcharsbx(\Mtai\Kerberos\Config::headerName()) ?></code></td>
			<td><?= $identity === ''
				? '<i style="color:#999;">' . Loc::getMessage('MTAI_KERBEROS_DIAG_IDENTITY_EMPTY') . '</i>'
				: '<code>' . htmlspecialcharsbx($identity) . '</code>' ?></td>
		</tr>
	</table>

	<br>

	<table class="internal" style="width:100%;">
		<tr class="heading">
			<td colspan="2"><?= Loc::getMessage('MTAI_KERBEROS_DIAG_SETTINGS') ?></td>
		</tr>
		<?php foreach ($settings as $name => $value): ?>
			<tr>
				<td style="width:40%;"><?= htmlspecialcharsbx($name) ?></td>
				<td><?= htmlspecialcharsbx((string)$value) ?></td>
			</tr>
		<?php endforeach; ?>
	</table>

	<br>

	<table class="internal" style="width:100%;">
		<tr class="heading">
			<td colspan="2"><?= Loc::getMessage('MTAI_KERBEROS_DIAG_PARSER') ?></td>
		</tr>
		<tr>
			<td colspan="2">
				<input type="text" name="identity" size="40" value="<?= htmlspecialcharsbx($parseInput) ?>"
					placeholder="user@CORP.LOCAL / DOM\user / user">
				<input type="submit" name="parse_identity" value="<?= Loc::getMessage('MTAI_KERBEROS_DIAG_PARSE') ?>"
					class="adm-btn-save">
			</td>
		</tr>
		<?php if ($parseInput !== ''): ?>
			<?php if (!$parsed): ?>
				<tr>
					<td colspan="2" style="color:red;"><?= Loc::getMessage('MTAI_KERBEROS_DIAG_PARSE_FAIL') ?></td>
				</tr>
			<?php else: ?>
				<?php foreach ($parseResult as $field => $value): ?>
					<tr>
						<td style="width:40%;"><?= htmlspecialcharsbx($field) ?></td>
						<td><code><?= htmlspecialcharsbx($value) ?></code></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		<?php endif; ?>
	</table>

	<br>

	<table class="internal" style="width:100%;">
		<tr class="heading">
			<td><?= Loc::getMessage('MTAI_KERBEROS_DIAG_LOG') ?></td>
			<td style="text-align:right;">
				<input type="submit" name="clear_log" value="<?= Loc::getMessage('MTAI_KERBEROS_DIAG_LOG_CLEAR') ?>">
			</td>
		</tr>
		<?php $logLines = \Mtai\Kerberos\Logger::tail(); ?>
		<?php if ($logLines === []): ?>
			<tr><td colspan="2"><i style="color:#999;"><?= Loc::getMessage('MTAI_KERBEROS_DIAG_LOG_EMPTY') ?></i></td></tr>
		<?php else: ?>
			<?php foreach ($logLines as $line): ?>
				<tr><td colspan="2"><code style="white-space:pre-wrap;"><?= htmlspecialcharsbx($line) ?></code></td></tr>
			<?php endforeach; ?>
		<?php endif; ?>
	</table>
</form>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
