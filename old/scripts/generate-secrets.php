<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

echo "# Pilote Shopping — Generated secrets\n";
echo "# Copy these into your .env file (rotate periodically).\n\n";
echo "JWT_SECRET=" . base64_encode(random_bytes(64)) . "\n";
echo "ENCRYPTION_KEY=" . bin2hex(random_bytes(32)) . "\n";
echo "HASH_SALT=" . bin2hex(random_bytes(16)) . "\n";
echo "CRON_SECRET=" . bin2hex(random_bytes(32)) . "\n";
echo "\n# DB / SMTP / Stripe / OAuth secrets must be rotated from their respective providers.\n";
