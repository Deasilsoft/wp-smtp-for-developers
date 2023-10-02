<?php
/**
 * Plugin Name: WP SMTP for Developers
 * Plugin URI: https://deasilsoft.com/
 * Description: Configure SMTP settings using wp-config.php constants.
 * Version: 1.0
 * Author: Deasilsoft
 * Author URI: https://deasilsoft.com/
 * License: MIT
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'phpmailer_init', 'wps4d_configure_smtp_settings' );
add_action( 'admin_notices', 'wps4d_display_smtp_errors' );
add_action( 'admin_menu', 'wps4d_register_smtp_settings_page' );
add_action( 'admin_post_wps4d_send_test_email', 'wps4d_handle_test_email_submission' );
add_action( 'wp_mail_failed', 'wps4d_handle_wp_mail_failure' );

function wps4d_get_smtp_setting( $key ) {
	$default_settings = [
		'SMTP_SERVER'   => null,
		'SMTP_USERNAME' => null,
		'SMTP_PASSWORD' => null,
		'SMTP_AUTH'     => true,
		'SMTP_SECURE'   => 'tls',
		'SMTP_PORT'     => '587',
		'SMTP_DEBUG'    => '0',
		'SMTP_FROM'     => null,
		'SMTP_NAME'     => null,
	];

	return defined( $key ) ? constant( $key ) : $default_settings[ $key ] ?? null;
}

/**
 * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer
 *
 * @return void
 *
 * @throws \PHPMailer\PHPMailer\Exception
 */
function wps4d_configure_smtp_settings( $phpmailer ) {
	if ( ! wps4d_are_required_smtp_constants_defined() ) {
		wps4d_add_smtp_error( 'Required SMTP constants are not defined in wp-config.php.' );

		return;
	}

	$phpmailer->isSMTP();

	$phpmailer->Host       = wps4d_get_smtp_setting( 'SMTP_SERVER' );
	$phpmailer->Username   = wps4d_get_smtp_setting( 'SMTP_USERNAME' );
	$phpmailer->Password   = wps4d_get_smtp_setting( 'SMTP_PASSWORD' );
	$phpmailer->SMTPAuth   = wps4d_get_smtp_setting( 'SMTP_AUTH' );
	$phpmailer->SMTPSecure = wps4d_get_smtp_setting( 'SMTP_SECURE' );
	$phpmailer->Port       = wps4d_get_smtp_setting( 'SMTP_PORT' );
	$phpmailer->SMTPDebug  = wps4d_get_smtp_setting( 'SMTP_DEBUG' );

	if ( wps4d_get_smtp_setting( 'SMTP_FROM' ) && wps4d_get_smtp_setting( 'SMTP_NAME' ) ) {
		$phpmailer->setFrom( wps4d_get_smtp_setting( 'SMTP_FROM' ), wps4d_get_smtp_setting( 'SMTP_NAME' ) );
	}
}

function wps4d_are_required_smtp_constants_defined() {
	return defined( 'SMTP_SERVER' ) && defined( 'SMTP_USERNAME' ) && defined( 'SMTP_PASSWORD' );
}

function wps4d_add_smtp_error( $message ) {
	$errors = get_transient( 'wps4d_smtp_errors' );

	if ( ! $errors ) {
		$errors = [];
	}

	$errors[] = $message;

	set_transient( 'wps4d_smtp_errors', $errors, 60 );
}

function wps4d_display_smtp_errors() {
	$errors = get_transient( 'wps4d_smtp_errors' );

	if ( ! $errors ) {
		return;
	}

	foreach ( $errors as $error ) {
		echo '<div class="notice notice-error is-dismissible">';
		echo '<p><strong>SMTP Error:</strong> ' . esc_html( $error ) . '</p>';
		echo '</div>';
	}

	delete_transient( 'wps4d_smtp_errors' );
}

function wps4d_register_smtp_settings_page() {
	add_options_page(
		'SMTP Settings',
		'SMTP Settings',
		'manage_options',
		'wps4d_smtp_settings',
		'wps4d_display_smtp_settings_page_content'
	);
}

function wps4d_display_smtp_settings_page_content() {
	echo '<div class="wrap">';
	echo '<h1>SMTP Settings</h1>';

	wps4d_display_test_email_form();
	wps4d_display_configuration_overview();
	wps4d_display_constants_overview();
	wps4d_display_example_configurations();

	echo '</div>';
}

function wps4d_display_test_email_form() {
	echo '<h2>Send Test Mail</h2>';

	if ( ! wps4d_are_required_smtp_constants_defined() ) {
		echo '<p><strong>Cannot send test email:</strong> Required SMTP constants are not defined.</p>';

		return;
	}

	$recipient = isset( $_GET['recipient'] ) ? sanitize_email( $_GET['recipient'] ) : '';
	$sent      = isset( $_GET['sent'] ) ? (bool) $_GET['sent'] : null;

	if ( isset( $sent ) ) {
		$message = $sent ? 'Test email sent successfully!' : 'Failed to send test email.';
		$class   = $sent ? 'notice-success' : 'notice-error';

		echo "<div class='notice {$class} is-dismissible'><p>{$message}</p></div>";
	}

	echo '<form method="post" action="' . admin_url( 'admin-post.php' ) . '">';
	echo '<input type="hidden" name="action" value="wps4d_send_test_email">';
	echo '<table class="form-table">';
	echo '<tbody>';
	echo '<tr>';
	echo '<th scope="row"><label for="recipient">Recipient Email</label></th>';
	echo '<td><input type="email" name="recipient" id="recipient" value="' . esc_attr( $recipient ) . '" class="regular-text" required></td>';
	echo '</tr>';
	echo '</tbody>';
	echo '</table>';
	echo '<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Send Test Email"></p>';
	echo '</form>';
}

function wps4d_handle_test_email_submission() {
	$recipient = isset( $_POST['recipient'] ) ? sanitize_email( $_POST['recipient'] ) : '';

	if ( ! $recipient ) {
		wp_redirect( add_query_arg( [ 'sent' => '0' ], wp_get_referer() ) );

		exit;
	}

	$sent = wp_mail( $recipient, 'Test Email from WP SMTP for Developers', 'This is a test email.' );

	wp_redirect( add_query_arg( [ 'sent' => $sent ? '1' : '0', 'recipient' => $recipient ], wp_get_referer() ) );

	exit;
}

function wps4d_handle_wp_mail_failure( $wp_error ) {
	wps4d_add_smtp_error( 'Mailer Error: ' . $wp_error->get_error_message() );
}

function wps4d_display_configuration_overview() {
	$settings = [
		'SMTP Server'   => wps4d_get_smtp_setting( 'SMTP_SERVER' ) ? wps4d_get_smtp_setting( 'SMTP_SERVER' ) : 'Not set',
		'SMTP Username' => wps4d_get_smtp_setting( 'SMTP_USERNAME' ) ? wps4d_get_smtp_setting( 'SMTP_USERNAME' ) : 'Not set',
		'SMTP Password' => wps4d_get_smtp_setting( 'SMTP_PASSWORD' ) ? '********' : 'Not set',
		'SMTP Port'     => wps4d_get_smtp_setting( 'SMTP_PORT' ) ? wps4d_get_smtp_setting( 'SMTP_PORT' ) : 'Not set',
		'SMTP Secure'   => wps4d_get_smtp_setting( 'SMTP_SECURE' ) ? wps4d_get_smtp_setting( 'SMTP_SECURE' ) : 'Not set',
		'SMTP From'     => wps4d_get_smtp_setting( 'SMTP_FROM' ) ? wps4d_get_smtp_setting( 'SMTP_FROM' ) : 'Not set',
		'SMTP Name'     => wps4d_get_smtp_setting( 'SMTP_NAME' ) ? wps4d_get_smtp_setting( 'SMTP_NAME' ) : 'Not set',
	];

	echo '<h2>Configuration Overview</h2>';
	echo '<table class="form-table">';

	foreach ( $settings as $name => $value ) {
		echo "<tr><th>{$name}</th><td>{$value}</td></tr>";
	}

	echo '</table>';
}

function wps4d_display_constants_overview() {
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

function wps4d_display_example_configurations() {
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
