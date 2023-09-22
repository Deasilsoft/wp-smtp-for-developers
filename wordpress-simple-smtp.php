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

if (!defined('ABSPATH')) {
    exit;
}

add_action('phpmailer_init', 'wpss_configure_smtp_settings');
add_action('admin_notices', 'wpss_display_smtp_errors');
add_action('admin_menu', 'wpss_register_smtp_settings_page');
add_action('admin_post_wpss_send_test_email', 'wpss_handle_test_email_submission');

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
    echo '<div class="wrap">';
    echo '<h1>SMTP Settings</h1>';

    wpss_display_test_email_form();
    wpss_display_configuration_overview();
    wpss_display_constants_overview();
    wpss_display_example_configurations();

    echo '</div>';
}

function wpss_display_test_email_form()
{
    echo '<h2>Send Test Mail</h2>';

    if (!wpss_are_required_smtp_constants_defined()) {
        echo '<p><strong>Cannot send test email:</strong> Required SMTP constants are not defined.</p>';

        return;
    }

    $recipient = isset($_GET['recipient']) ? sanitize_email($_GET['recipient']) : '';
    $sent = isset($_GET['sent']) ? (bool)$_GET['sent'] : null;

    if (isset($sent)) {
        $message = $sent ? 'Test email sent successfully!' : 'Failed to send test email.';
        $class = $sent ? 'notice-success' : 'notice-error';

        echo "<div class='notice {$class} is-dismissible'><p>{$message}</p></div>";
    }

    echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
    echo '<input type="hidden" name="action" value="wpss_send_test_email">';
    echo '<label for="recipient">Recipient Email: </label>';
    echo '<input type="email" name="recipient" value="' . esc_attr($recipient) . '" required>';
    echo '<input type="submit" value="Send Test Email" class="button button-primary">';
    echo '</form>';
}

function wpss_handle_test_email_submission()
{
    $recipient = isset($_POST['recipient']) ? sanitize_email($_POST['recipient']) : '';

    if (!$recipient) {
        wp_redirect(add_query_arg(['sent' => '0'], wp_get_referer()));

        exit;
    }

    $sent = wp_mail($recipient, 'Test Email from WordPress Simple SMTP', 'This is a test email.');

    wp_redirect(add_query_arg(['sent' => $sent ? '1' : '0', 'recipient' => $recipient], wp_get_referer()));

    exit;
}

function wpss_display_configuration_overview()
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

    echo '<h2>Configuration Overview</h2>';
    echo '<table class="form-table">';

    foreach ($settings as $name => $value) {
        echo "<tr><th>{$name}</th><td>{$value}</td></tr>";
    }

    echo '</table>';
}

function wpss_display_constants_overview()
{
    echo '<h2>Constants Overview:</h2>';
    echo '<ul>';
    echo '<li><strong>SMTP_SERVER:</strong> The address of your SMTP server.</li>';
    echo '<li><strong>SMTP_USERNAME:</strong> The username or email used to authenticate with the SMTP server.</li>';
    echo '<li><strong>SMTP_PASSWORD:</strong> The password used to authenticate with the SMTP server.</li>';
    echo '<li><strong>SMTP_PORT (optional):</strong> The port used by the SMTP server. Common values are 25, 465, and 587.</li>';
    echo '<li><strong>SMTP_SECURE (optional):</strong> Encryption method. Can be "tls", "ssl", or omitted.</li>';
    echo '<li><strong>SMTP_FROM (optional):</strong> The email address that emails will be sent from.</li>';
    echo '<li><strong>SMTP_NAME (optional):</strong> The name that emails will be sent from.</li>';
    echo '</ul>';
}

function wpss_display_example_configurations()
{
    echo '<h2>Example Configurations:</h2>';

    echo '<h3>Gmail:</h3>';
    echo "<pre>";
    echo "define( 'SMTP_SERVER', 'smtp.gmail.com' );\n";
    echo "define( 'SMTP_USERNAME', 'your-email@gmail.com' );\n";
    echo "define( 'SMTP_PASSWORD', 'your-gmail-password' );\n";
    echo "define( 'SMTP_PORT', '587' );\n";
    echo "define( 'SMTP_SECURE', 'tls' );\n";
    echo '</pre>';
    echo '<p>Note: Using Gmail requires allowing "less secure apps" in your Gmail settings, or you can use an "App Password".</p>';

    echo '<h3>Outlook:</h3>';
    echo "<pre>";
    echo "define( 'SMTP_SERVER', 'smtp.office365.com' );\n";
    echo "define( 'SMTP_USERNAME', 'your-email@outlook.com' );\n";
    echo "define( 'SMTP_PASSWORD', 'your-outlook-password' );\n";
    echo "define( 'SMTP_PORT', '587' );\n";
    echo "define( 'SMTP_SECURE', 'tls' );\n";
    echo '</pre>';

    echo '<h3>AWS SES (Simple Email Service):</h3>';
    echo "<pre>";
    echo "define( 'SMTP_SERVER', 'email-smtp.us-west-2.amazonaws.com' );\n";
    echo "define( 'SMTP_USERNAME', 'your-ses-smtp-username' );\n";
    echo "define( 'SMTP_PASSWORD', 'your-ses-smtp-password' );\n";
    echo "define( 'SMTP_PORT', '587' );\n";
    echo "define( 'SMTP_SECURE', 'tls' );\n";
    echo '</pre>';
    echo '<p>Note: Ensure your AWS SES account is out of the "sandbox" mode to send emails to any recipient.</p>';
}
