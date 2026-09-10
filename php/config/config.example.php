<?php
// Copy to config.local.php, outside the public document root. Never commit credentials.
return [
    'base_url' => 'https://www.zichtbaar-marketing.nl',
    'admin_email' => 'info@zichtbaar-marketing.nl',
    // Generate with: php bin/setup.php (interactive; never a plaintext password here).
    'admin_password_hash' => '',
    'app_key' => '',
    'blog_ai_key' => '',
    'blog_ai_model' => 'gpt-4.1-mini',
    'blog_ai_images' => true,
    'blog_image_model' => 'gpt-image-2.5-sunburst',
    'linkedin_client_id' => '',
    'linkedin_client_secret' => '',
    'linkedin_author' => '',
    'linkedin_token' => '',
    'linkedin_expires' => 0,
    'linkedin_enabled' => false,
    'linkedin_enabled_since' => 0,
    'linkedin_version' => '202606',
    'smtp_host' => '',
    'smtp_port' => 587,
    'smtp_security' => 'tls', // tls (587) or ssl (465), certificate verification stays enabled.
    'smtp_username' => '',
    'smtp_password' => '',
    'smtp_from' => 'info@zichtbaar-marketing.nl',
    'data_dir' => dirname(__DIR__) . '/storage',
];
