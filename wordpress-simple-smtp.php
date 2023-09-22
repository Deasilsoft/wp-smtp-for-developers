<?php
/**
 * Plugin Name: WordPress Simple SMTP
 * Plugin URI: https://deasilsoft.com/
 * Description: Configure SMTP settings using wp-config.php constants.
 * Version: 1.0
 * Author: Deasilsoft
 * Author URI: https://deasilsoft.com/
 * License: MIT
 */

add_action('phpmailer_init', 'wpss_configure_smtp_settings');
add_action('admin_notices', 'wpss_display_smtp_errors');
add_action('admin_menu', 'wpss_register_smtp_settings_page');

function wpss_configure_smtp_settings(PHPMailer $phpmailer)
{
    if (!wpss_are_required_smtp_constants_defined()) {
        wpss_add_smtp_error('Required SMTP constants are not defined in wp-config.php.');

        return;
    }

    $phpmailer->isSMTP();

    $phpmailer->Host = SMTP_SERVER;
    $phpmailer->Username = SMTP_USERNAME;
    $phpmailer->Password = SMTP_PASSWORD;

    if (defined('SMTP_PORT')) {
        $phpmailer->Port = SMTP_PORT;
    }

    if (defined('SMTP_SECURE')) {
        $phpmailer->SMTPSecure = SMTP_SECURE;
    }

    if (defined('SMTP_FROM') && defined('SMTP_NAME')) {
        $phpmailer->setFrom(SMTP_FROM, SMTP_NAME);
    }

    if (defined('SMTP_AUTH')) {
        $phpmailer->SMTPAuth = SMTP_AUTH;
    }

    if (defined('SMTP_DEBUG')) {
        $phpmailer->SMTPDebug = SMTP_DEBUG;
    }
}

function wpss_are_required_smtp_constants_defined()
{
    return defined('SMTP_SERVER') && defined('SMTP_USERNAME') && defined('SMTP_PASSWORD');
}

function wpss_add_smtp_error($message)
{
    $errors = get_transient('wpss_smtp_errors');

    if (!$errors) {
        $errors = [];
    }

    $errors[] = $message;

    set_transient('wpss_smtp_errors', $errors, 60);
}

function wpss_display_smtp_errors()
{
    $errors = get_transient('wpss_smtp_errors');

    if (!$errors) {
        return;
    }

    foreach ($errors as $error) {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p><strong>SMTP Error:</strong> ' . esc_html($error) . '</p>';
        echo '</div>';
    }

    delete_transient('wpss_smtp_errors');
}

function wpss_register_smtp_settings_page()
{
    add_options_page(
        'SMTP Settings',
        'SMTP Settings',
        'manage_options',
        'wpss_smtp_settings',
        'wpss_display_smtp_settings_page_content'
    );
}

function wpss_display_smtp_settings_page_content()
{
    $settings = [
        'SMTP Server' => defined('SMTP_SERVER') ? SMTP_SERVER : 'Not set',
        'SMTP Username' => defined('SMTP_USERNAME') ? SMTP_USERNAME : 'Not set',
        'SMTP Password' => defined('SMTP_PASSWORD') ? '********' : 'Not set',
        'SMTP Port' => defined('SMTP_PORT') ? SMTP_PORT : 'Not set',
        'SMTP Secure' => defined('SMTP_SECURE') ? SMTP_SECURE : 'Not set',
        'SMTP From' => defined('SMTP_FROM') ? SMTP_FROM : 'Not set',
        'SMTP Name' => defined('SMTP_NAME') ? SMTP_NAME : 'Not set'
    ];

    echo '<div class="wrap">' . "\n";
    echo '<h1>SMTP Settings</h1>' . "\n";
    echo '<table class="form-table">' . "\n";

    foreach ($settings as $name => $value) {
        echo "<tr><th>{$name}</th><td>{$value}</td></tr>" . "\n";
    }

    echo '</table>' . "\n";
    echo '<h2>Constants Overview:</h2>' . "\n";
    echo '<ul>' . "\n";
    echo '<li><strong>SMTP_SERVER:</strong> The address of your SMTP server.</li>' . "\n";
    echo '<li><strong>SMTP_USERNAME:</strong> The username or email used to authenticate with the SMTP server.</li>' . "\n";
    echo '<li><strong>SMTP_PASSWORD:</strong> The password used to authenticate with the SMTP server.</li>' . "\n";
    echo '<li><strong>SMTP_PORT (optional):</strong> The port used by the SMTP server. Common values are 25, 465, and 587.</li>' . "\n";
    echo '<li><strong>SMTP_SECURE (optional):</strong> Encryption method. Can be "tls", "ssl", or omitted.</li>' . "\n";
    echo '<li><strong>SMTP_FROM (optional):</strong> The email address that emails will be sent from.</li>' . "\n";
    echo '<li><strong>SMTP_NAME (optional):</strong> The name that emails will be sent from.</li>' . "\n";
    echo '</ul>' . "\n";

    echo '<h3>Example Configurations:</h3>' . "\n";

    echo '<h4>Gmail:</h4>' . "\n";
    echo "<pre>\n";
    echo "define('SMTP_SERVER', 'smtp.gmail.com');\n";
    echo "define('SMTP_USERNAME', 'your-email@gmail.com');\n";
    echo "define('SMTP_PASSWORD', 'your-gmail-password');\n";
    echo "define('SMTP_PORT', '587');\n";
    echo "define('SMTP_SECURE', 'tls');\n";
    echo '</pre>' . "\n";
    echo '<p>Note: Using Gmail requires allowing "less secure apps" in your Gmail settings, or you can use an "App Password".</p>' . "\n";

    echo '<h4>Outlook:</h4>' . "\n";
    echo "<pre>\n";
    echo "define('SMTP_SERVER', 'smtp.office365.com');\n";
    echo "define('SMTP_USERNAME', 'your-email@outlook.com');\n";
    echo "define('SMTP_PASSWORD', 'your-outlook-password');\n";
    echo "define('SMTP_PORT', '587');\n";
    echo "define('SMTP_SECURE', 'tls');\n";
    echo '</pre>' . "\n";

    echo '<h4>AWS SES (Simple Email Service):</h4>' . "\n";
    echo "<pre>\n";
    echo "define('SMTP_SERVER', 'email-smtp.us-west-2.amazonaws.com'); // This may vary depending on your SES region\n";
    echo "define('SMTP_USERNAME', 'your-ses-smtp-username');\n";
    echo "define('SMTP_PASSWORD', 'your-ses-smtp-password');\n";
    echo "define('SMTP_PORT', '587');\n";
    echo "define('SMTP_SECURE', 'tls');\n";
    echo '</pre>' . "\n";
    echo '<p>Note: Ensure your AWS SES account is out of the "sandbox" mode to send emails to any recipient.</p>' . "\n";

    echo '</div>' . "\n";
}
