# DKIM keys

The DKIM private key file MUST NOT live in the web root.

Place the private key outside the document root (e.g. `/etc/dkim/piloteshopping.private.key`,
permissions `0400`, owner `www-data`) and reference it via the `DKIM_PRIVATE_KEY_PATH`
environment variable in `.env`.

This directory is denied from the web via `.htaccess`. The previously committed
`private.key` has been removed; the corresponding DKIM record must be rotated.
