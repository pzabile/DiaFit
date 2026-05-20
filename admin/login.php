<?php
// Admin auth is handled at the web-server level via Hostinger
// "Password Protect Directories" on /admin/. There is no PHP login.
header('Location: /admin/');
exit;
