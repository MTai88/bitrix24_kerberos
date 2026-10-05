<?php

$MESS['MTAI_KERBEROS_SAVED'] = 'Settings saved.';

$MESS['MTAI_KERBEROS_TAB_MAIN'] = 'General';
$MESS['MTAI_KERBEROS_TAB_MAIN_TITLE'] = 'General settings';
$MESS['MTAI_KERBEROS_TAB_USERS'] = 'Users';
$MESS['MTAI_KERBEROS_TAB_USERS_TITLE'] = 'User mapping and provisioning';
$MESS['MTAI_KERBEROS_TAB_SECURITY'] = 'Security';
$MESS['MTAI_KERBEROS_TAB_SECURITY_TITLE'] = 'Realm and network restrictions';
$MESS['MTAI_KERBEROS_TAB_DEBUG'] = 'Diagnostics';
$MESS['MTAI_KERBEROS_TAB_DEBUG_TITLE'] = 'Log and debugging';

$MESS['MTAI_KERBEROS_WARN_SPOOFABLE'] = 'WARNING: the username is read from a header that a client can forge, and the allowed network list is empty. Fill in "Networks where SSO is accepted" on the Security tab or switch to REMOTE_USER.';

$MESS['MTAI_KERBEROS_OPT_ENABLED'] = 'Enable automatic sign-on (SSO)';
$MESS['MTAI_KERBEROS_OPT_ENABLED_HINT'] = 'While disabled the module does nothing on hits.';
$MESS['MTAI_KERBEROS_OPT_HEADER_NAME'] = 'Variable/header with the verified username';
$MESS['MTAI_KERBEROS_OPT_HEADER_NAME_HINT'] = 'REMOTE_USER — for Apache mod_auth_gssapi / nginx spnego. Behind a proxy or on a test stand — a trusted header such as X-Remote-User or X-Krb-User.';
$MESS['MTAI_KERBEROS_OPT_REQUIRE_AUTH_TYPE'] = 'Require a confirmed mechanism (AUTH_TYPE)';
$MESS['MTAI_KERBEROS_OPT_REQUIRE_AUTH_TYPE_HINT'] = 'Authorize only when AUTH_TYPE is Negotiate / Kerberos / NTLM.';
$MESS['MTAI_KERBEROS_OPT_DISABLE_AFTER_LOGOUT'] = 'Do not sign the user back in after logout';
$MESS['MTAI_KERBEROS_OPT_DISABLE_AFTER_LOGOUT_HINT'] = 'After logout, auto-login is suppressed until the browser closes, a new login, or following a link with ?krb_relogin=1.';

$MESS['MTAI_KERBEROS_OPT_MAP_BY_EMAIL'] = 'Also match users by e-mail = UPN';
$MESS['MTAI_KERBEROS_OPT_MAP_BY_EMAIL_HINT'] = 'In AD the UPN (user@domain) usually equals the work e-mail.';
$MESS['MTAI_KERBEROS_OPT_CREATE_USERS'] = 'Create the user on first sign-on';
$MESS['MTAI_KERBEROS_OPT_CREATE_USERS_HINT'] = 'Login without domain/realm, e-mail = UPN, random password. When disabled and no user is found, the standard login form is shown.';
$MESS['MTAI_KERBEROS_OPT_NEW_USER_GROUPS'] = 'Groups of new users';
$MESS['MTAI_KERBEROS_OPT_NEW_USER_GROUPS_HINT'] = 'Ctrl+click to select several. May be left empty.';

$MESS['MTAI_KERBEROS_OPT_REALMS'] = 'Allowed Kerberos realms';
$MESS['MTAI_KERBEROS_OPT_REALMS_HINT'] = 'Comma-separated, case-insensitive. Empty — any. Example: CORP.LOCAL, ALT.DOMAIN.RU';
$MESS['MTAI_KERBEROS_OPT_IP_ALLOW'] = 'Networks where SSO is accepted';
$MESS['MTAI_KERBEROS_OPT_IP_ALLOW_HINT'] = 'One entry per line: an IP or CIDR (10.0.0.0/8). Empty — all addresses. Mandatory when the username comes from a header.';

$MESS['MTAI_KERBEROS_OPT_DEBUG'] = 'Keep an SSO decision log';
$MESS['MTAI_KERBEROS_OPT_DEBUG_HINT'] = 'File upload/mtai.kerberos/sso.log — what the module decided on each SPNEGO hit.';
$MESS['MTAI_KERBEROS_DIAG_PAGE'] = 'Diagnostics';
$MESS['MTAI_KERBEROS_DIAG_PAGE_LINK'] = 'Open the diagnostics page';
$MESS['MTAI_KERBEROS_DIAG_PAGE_HINT'] = 'What PHP sees from the web server, principal parsing, the log.';

$MESS['MTAI_KERBEROS_YES'] = 'yes';
$MESS['MTAI_KERBEROS_NO'] = 'no';
$MESS['MTAI_KERBEROS_ANY'] = '(any)';
$MESS['MTAI_KERBEROS_ACCESS_DENIED'] = 'You are not allowed to view this page.';
