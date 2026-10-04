<?php

$MESS['MTAI_KERBEROS_SAVED'] = 'Настройки сохранены.';

$MESS['MTAI_KERBEROS_TAB_MAIN'] = 'Основные';
$MESS['MTAI_KERBEROS_TAB_MAIN_TITLE'] = 'Основные настройки';
$MESS['MTAI_KERBEROS_TAB_USERS'] = 'Пользователи';
$MESS['MTAI_KERBEROS_TAB_USERS_TITLE'] = 'Сопоставление и создание пользователей';
$MESS['MTAI_KERBEROS_TAB_SECURITY'] = 'Безопасность';
$MESS['MTAI_KERBEROS_TAB_SECURITY_TITLE'] = 'Ограничения по сферам и сетям';
$MESS['MTAI_KERBEROS_TAB_DEBUG'] = 'Диагностика';
$MESS['MTAI_KERBEROS_TAB_DEBUG_TITLE'] = 'Журнал и отладка';

$MESS['MTAI_KERBEROS_WARN_SPOOFABLE'] = 'ВНИМАНИЕ: имя пользователя читается из заголовка, который клиент может подделать, а список разрешённых сетей пуст. Заполните «Сети, из которых принимается SSO» на вкладке «Безопасность» или переключитесь на REMOTE_USER.';

$MESS['MTAI_KERBEROS_OPT_ENABLED'] = 'Включить автоматический вход (SSO)';
$MESS['MTAI_KERBEROS_OPT_ENABLED_HINT'] = 'Пока выключено, модуль ничего не делает на хитах.';
$MESS['MTAI_KERBEROS_OPT_HEADER_NAME'] = 'Переменная/заголовок с проверенным именем';
$MESS['MTAI_KERBEROS_OPT_HEADER_NAME_HINT'] = 'REMOTE_USER — для Apache mod_auth_gssapi / nginx spnego. За прокси и на тест-стенде — доверенный заголовок, например X-Remote-User или X-Krb-User.';
$MESS['MTAI_KERBEROS_OPT_REQUIRE_AUTH_TYPE'] = 'Требовать подтверждённый механизм (AUTH_TYPE)';
$MESS['MTAI_KERBEROS_OPT_REQUIRE_AUTH_TYPE_HINT'] = 'Авторизоваться только если AUTH_TYPE = Negotiate / Kerberos / NTLM.';
$MESS['MTAI_KERBEROS_OPT_DISABLE_AFTER_LOGOUT'] = 'Не заводить обратно после выхода';
$MESS['MTAI_KERBEROS_OPT_DISABLE_AFTER_LOGOUT_HINT'] = 'После «Выйти» автологин подавляется до закрытия браузера, повторного входа или перехода по ссылке с параметром ?krb_relogin=1.';

$MESS['MTAI_KERBEROS_OPT_MAP_BY_EMAIL'] = 'Искать пользователя также по e-mail = UPN';
$MESS['MTAI_KERBEROS_OPT_MAP_BY_EMAIL_HINT'] = 'В AD UPN (user@domain) обычно совпадает с рабочей почтой.';
$MESS['MTAI_KERBEROS_OPT_CREATE_USERS'] = 'Создавать пользователя при первом входе';
$MESS['MTAI_KERBEROS_OPT_CREATE_USERS_HINT'] = 'Логин — без домена и сферы, e-mail — UPN, случайный пароль. Если выключено и пользователь не найден — показывается штатная форма входа.';
$MESS['MTAI_KERBEROS_OPT_NEW_USER_GROUPS'] = 'Группы новых пользователей';
$MESS['MTAI_KERBEROS_OPT_NEW_USER_GROUPS_HINT'] = 'Ctrl+клик — выбор нескольких. Список можно оставить пустым.';

$MESS['MTAI_KERBEROS_OPT_REALMS'] = 'Разрешённые сферы Kerberos';
$MESS['MTAI_KERBEROS_OPT_REALMS_HINT'] = 'Через запятую, регистр не важен. Пусто — любые. Пример: CORP.LOCAL, ALT.DOMAIN.RU';
$MESS['MTAI_KERBEROS_OPT_IP_ALLOW'] = 'Сети, из которых принимается SSO';
$MESS['MTAI_KERBEROS_OPT_IP_ALLOW_HINT'] = 'По одной записи в строке: IP или CIDR (10.0.0.0/8). Пусто — все адреса. Обязательно заполняйте, если имя приходит из заголовка.';

$MESS['MTAI_KERBEROS_OPT_DEBUG'] = 'Вести журнал решений SSO';
$MESS['MTAI_KERBEROS_OPT_DEBUG_HINT'] = 'Файл upload/mtai.kerberos/sso.log — что модуль решил на каждом хите с SPNEGO.';
$MESS['MTAI_KERBEROS_DIAG_PAGE'] = 'Диагностика';
$MESS['MTAI_KERBEROS_DIAG_PAGE_LINK'] = 'Открыть страницу диагностики';
$MESS['MTAI_KERBEROS_DIAG_PAGE_HINT'] = 'Что видит PHP от веб-сервера, разбор principal, журнал.';

$MESS['MTAI_KERBEROS_YES'] = 'да';
$MESS['MTAI_KERBEROS_NO'] = 'нет';
$MESS['MTAI_KERBEROS_ANY'] = '(любые)';
$MESS['MTAI_KERBEROS_ACCESS_DENIED'] = 'Нет прав на просмотр страницы.';
