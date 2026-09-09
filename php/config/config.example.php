<?php
// Copy to config.local.php, outside the public document root. Never commit credentials.
return [
    'base_url' => 'https://www.zichtbaar-marketing.nl',
    'admin_email' => 'info@zichtbaar-marketing.nl',
    // Generate with: php bin/setup.php (interactive; never a plaintext password here).
    'admin_password_hash' => '',
    'app_key' => '',
    'smtp_host' => '',
    'smtp_port' => 587,
    'smtp_security' => 'tls', // tls (587) or ssl (465), certificate verification stays enabled.
    'smtp_username' => '',
    'smtp_password' => '',
    'smtp_from' => 'info@zichtbaar-marketing.nl',
    'data_dir' => dirname(__DIR__) . '/storage',
];
