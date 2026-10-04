# mtai.kerberos — Kerberos / SPNEGO SSO для Bitrix24

Модуль единого входа: пользователь, уже прошедший доменную аутентификацию
(Kerberos/SPNEGO на уровне веб-сервера), попадает в портал **без ввода
логина и пароля**. Отдельный пароль Bitrix24 не нужен и не используется.

```
Браузер ──(Negotiate: Kerberos-билет)──► Веб-сервер (SPNEGO)
                                            │ проверяет билет у KDC
                                            ▼
                                       REMOTE_USER = user@CORP.LOCAL
                                            │
                                            ▼
                              модуль mtai.kerberos
                              (OnBeforeProlog: пользователь не
                               авторизован + есть проверенное имя)
                                            │
                                            ▼
                        найти/создать пользователя портала
                                            │
                                            ▼
                        $USER->Authorize(Method::External)
```

Ключевой принцип доверия: **модуль не проверяет Kerberos сам** — это задача
веб-сервера (mod_auth_gssapi, spnego и т.п.). Модуль доверяет только
переменной/заголовку, до которого клиент дотянуться не может, и авторизует
соответствующего пользователя портала.

## Что делает модуль

| Возможность | Как |
|---|---|
| Автовход | На каждом хите (`OnBeforeProlog`), если пользователь не авторизован, а сервер передал проверенное имя — `REMOTE_USER` или настроенный доверенный заголовок |
| Разбор имени | `user@REALM`, `DOM\user`, `user` → логин без домена/сферы, сфера, UPN |
| Поиск пользователя | по `LOGIN` (без домена и сферы), затем по `EMAIL = UPN` (в AD UPN обычно равен рабочей почте) |
| Автосоздание | Опционально: логин = имя без сферы, e-mail = UPN, случайный пароль, группы из настроек, `XML_ID = mtai.kerberos\|<UPN>` (пометка происхождения) |
| Ограничения | Разрешённые Kerberos-сферы; сети (IP/CIDR), из которых принимается SSO |
| Корректный выход | После «Выйти» автологин подавляется (флаг сессии + cookie до закрытия браузера), иначе браузер с Kerberos-билетом заводил бы пользователя обратно мгновенно |
| Повторный вход | Ссылка «войти под доменной учёткой»: `/?krb_relogin=1` — снимает подавление |
| Метод входа | `Authentication\Context` с `Method::External` — вход виден в истории входов как внешний |

Не найден пользователь и автосоздание выключено — показывается штатная
форма входа (модуль просто отходит в сторону).

## Установка

Каталог `mtai.kerberos/` — в `local/modules/`. Затем: **Настройки → Модули**
→ установить `mtai.kerberos`. Установщик регистрирует обработчики
и страницу диагностики в `/bitrix/admin/mtai_kerberos_diag.php`.

## Настройки

Страница: **Настройки → Настройки модулей → Kerberos / SPNEGO SSO**.

| Опция | Значение по умолчанию | Описание |
|---|---|---|
| Включить SSO | выкл | Мастер-выключатель |
| Переменная/заголовок | `REMOTE_USER` | Откуда читать проверенное имя. За прокси — доверенный заголовок (например `X-Remote-User`) |
| Требовать AUTH_TYPE | выкл | Авторизоваться только при `AUTH_TYPE` = Negotiate/Kerberos/NTLM |
| Не заводить обратно после выхода | вкл | Подавление автологина после logout + `?krb_relogin=1` |
| Искать по e-mail = UPN | вкл | Дополнительный способ сопоставления |
| Создавать пользователя | выкл | Автосоздание при первом входе |
| Группы новых пользователей | — | IDs групп (мультивыбор) |
| Разрешённые сферы | — | Через запятую (`CORP.LOCAL, ALT.DOMAIN.RU`); пусто = любые |
| Сети SSO | — | IP/CIDR по одному в строке; пусто = все |
| Журнал решений | выкл | `upload/mtai.kerberos/sso.log` |

## Настройка веб-сервера (production)

### Apache + mod_auth_gssapi (эталонная схема)

SPNEGO выполняет Apache, PHP (FPM) получает `REMOTE_USER`. Нужен keytab
с сервис-принципалом `HTTP/portal.corp.local@CORP.LOCAL`:

```apache
<VirtualHost *:443>
    ServerName portal.corp.local

    <Location />
        AuthType GSSAPI
        AuthName "Kerberos SSO"
        GssapiCredStore keytab:/etc/krb5.keytab
        KrbAuthRealms CORP.LOCAL
        GssapiBasicAuth On              # fallback: доменный логин/пароль
        Require valid-user
    </Location>

    # при PHP-FPM через SetHandler proxy:fcgi — пропустить REMOTE_USER в PHP
    CGIPassAuth On

    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php-fpm/www.sock|fcgi://localhost"
    </FilesMatch>

    ProxyPassMatch ^/(.*\.php(/.*)?)$ unix:/run/php-fpm/www.sock|fcgi://localhost/var/www/html/$1
</VirtualHost>
```

Модуль оставляет опцию `header_name = REMOTE_USER`. Если Apache отдаёт
`REMOTE_USER` в окружение FPM корректно — больше ничего не нужно.
Когда переменная теряется (частая история с проксированием), надёжнее
продублировать её заголовком: `RequestHeader set X-Remote-User expr=%{REMOTE_USER}`
и указать в модуле `header_name = X-Remote-User`.

### nginx + spnego (ngx_http_auth_spnego_module / gssapi)

```nginx
server {
    listen 443 ssl;
    server_name portal.corp.local;

    location / {
        auth_gss on;
        auth_gss_realm CORP.LOCAL;
        auth_gss_keytab /etc/krb5.keytab;
        auth_gss_force_realm on;

        fastcgi_pass unix:/run/php-fpm.sock;
        fastcgi_param REMOTE_USER $remote_user;   # имя после SPNEGO
        include fastcgi_params;
    }
}
```

Любая схема «имя приходит заголовком через прокси» (например
`X-Remote-User` за фронтом) обязана сочетаться с непустым списком
**сетей SSO**: заголовок ставит прокси, но если порт открыт наружу
и запрос может прийти в PHP в обход прокси — заголовок подделает кто
угодно. Страница настроек и диагностики предупреждают об этом явно.

## Тестовый режим без KDC

Когда домена нет (разработка, демо), модуль можно переключить на
произвольный заголовок — например `X-Krb-User`: прокси передаёт его в PHP
как обычный HTTP-заголовок, и это эмулирует «сервер уже проверил
пользователя»:

```bash
# автологин существующего пользователя (login или email = UPN)
curl -k -c /tmp/jar -H 'X-Krb-User: ivanov@CORP.LOCAL' https://portal.corp.local/ -o /dev/null -v

# автосоздание нового пользователя (опция «Создавать пользователя» = да)
curl -k -c /tmp/jar -H 'X-Krb-User: new.employee@CORP.LOCAL' https://portal.corp.local/ -o /dev/null

# проверить, что сессия авторизована: с cookie / редиректит на /online/
curl -k -b /tmp/jar -o /dev/null -w '%{http_code} -> %{redirect_url}\n' https://portal.corp.local/
```

Признак успеха — журнал `upload/mtai.kerberos/sso.log`:

```
2026-10-04 13:10:11  authorized  {"id":3,"login":"ivanov","upn":"ivanov@CORP.LOCAL"}
2026-10-04 13:10:32  user_created  {"id":392,"login":"new.employee","upn":"new.employee@CORP.LOCAL"}
```

Выход (нужен POST с sessid — GET-logout Bitrix24 игнорирует) и повторный вход:

```bash
SID=$(curl -k -b /tmp/jar https://portal.corp.local/online/ | grep -o '"sessid":"[a-f0-9]\{32\}"' | head -1 | cut -d'"' -f4)
curl -k -b /tmp/jar -X POST -d "logout=yes&sessid=$SID" https://portal.corp.local/
# следующий хит с X-Krb-User НЕ заводит обратно: skip_after_logout
# принудительный повторный SSO-вход:
curl -k -b /tmp/jar -H 'X-Krb-User: ivanov@CORP.LOCAL' 'https://portal.corp.local/?krb_relogin=1'
```

## Диагностика

**Настройки → Kerberos SSO: диагностика единого входа**
(`/bitrix/admin/mtai_kerberos_diag.php`):

- что реально видит PHP: `REMOTE_USER`, `REDIRECT_REMOTE_USER`,
  `AUTH_TYPE`, `HTTP_AUTHORIZATION`, IP клиента;
- имя по текущим настройкам модуля (какой источник читается);
- тестер разбора: вводите `user@CORP.LOCAL` / `DOM\user` — видите, как
  модуль его поймёт и разрешена ли сфера;
- хвост журнала решений.

События журнала: `authorized`, `user_created`, `provision_failed`,
`skip_after_logout`, `logout_suppression_on`, `skip_auth_type`, `deny_ip`,
`deny_unparsable`, `deny_realm`, `deny_user_not_found`, `deny_user_inactive`.

## Структура

```
mtai.kerberos/
├── install/
│   ├── index.php                 # установщик: события + страница диагностики
│   ├── version.php
│   └── admin/mtai_kerberos_diag.php
├── lang/{ru,en}/                 # языковые файлы
├── lib/
│   ├── Config.php                # типизированные опции
│   ├── Principal.php             # разбор user@REALM / DOM\user
│   ├── IdentitySource.php        # чтение REMOTE_USER / доверенного заголовка
│   ├── IpFilter.php              # сети (CIDR)
│   ├── UserResolver.php          # поиск по логину и e-mail=UPN
│   ├── UserProvisioner.php       # автосоздание
│   ├── SsoHandler.php            # обработчики событий
│   └── Logger.php                # журнал решений
├── default_option.php
├── include.php
└── options.php                   # страница настроек
```

## Заметки по реализации

- Точка входа — `OnBeforeProlog`: сессия и `$USER` уже инициализированы,
  событие срабатывает и на публичных, и на админских страницах.
- `$USER->Authorize()` вызывается с `Authentication\Context`
  (`Method::External`, `externalAuthId = mtai.kerberos`) — помечает
  способ входа в истории входов.
- Подавление после выхода хранится и в сессии, и в cookie
  (`MTAI_KRB_NOAUTO`, session-cookie): выход может пересоздать сессию,
  cookie в этом случае страхует. Снимается флаг повторным входом
  (`OnAfterUserAuthorize`) или `?krb_relogin=1`.
- Bitrix24 при автосоздании пользователя может назначить свои группы
  по умолчанию (внутрипортальные), поверх явно указанных в опции.
- GET `?logout=yes` современный Bitrix24 игнорирует (CSRF) —
  разлогинирование выполняется POST-запросом с `sessid`.
