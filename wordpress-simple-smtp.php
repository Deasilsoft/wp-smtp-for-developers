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
		echo <<<HTML
			<div class="notice notice-error is-dismissible">
				<p><strong>SMTP Error:</strong> {esc_html($error)}</p>
			</div>
			HTML;
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
	$recipient = isset( $_GET['recipient'] ) ? sanitize_email( $_GET['recipient'] ) : '';
	$sent      = isset( $_GET['sent'] ) ? (bool) $_GET['sent'] : null;

	$nonce_field = wp_nonce_field( 'wps4d_test_email_nonce', '_wpnonce', true, false );
	$admin_url   = admin_url( 'admin-post.php' );
	$notice      = "";

	if ( ! wps4d_are_required_smtp_constants_defined() ) {
		echo <<<HTML
			<h2>Send Test Mail</h2>
			<p><strong>Cannot send test email:</strong> Required SMTP constants are not defined.</p>
			HTML;

		return;
	}

	if ( isset( $sent ) ) {
		$message = $sent ? 'Test email sent successfully!' : 'Failed to send test email.';
		$class   = $sent ? 'notice-success' : 'notice-error';

		$notice = <<<HTML
			<div class='notice {$class} is-dismissible' role='alert'>
				<p>{$message}</p>
			</div>
			HTML;
	}

	echo <<<HTML
		<h2>Send Test Mail</h2>
		{$notice}
		<form method="post" action="{$admin_url}">
			<input type="hidden" name="action" value="wps4d_send_test_email">
			{$nonce_field}
			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row"><label for="recipient">Recipient Email</label></th>
						<td><input type="email" name="recipient" id="recipient" value="{$recipient}" class="regular-text" required></td>
					</tr>
				</tbody>
			</table>
			<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Send Test Email"></p>
		</form>
		HTML;
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
		'SMTP Server'   => wps4d_get_smtp_setting( 'SMTP_SERVER' ) ?: 'Not set',
		'SMTP Username' => wps4d_get_smtp_setting( 'SMTP_USERNAME' ) ?: 'Not set',
		'SMTP Password' => wps4d_get_smtp_setting( 'SMTP_PASSWORD' ) ? '********' : 'Not set',
		'SMTP Auth'     => wps4d_get_smtp_setting( 'SMTP_AUTH' ) ? 'Yes' : 'No',
		'SMTP Secure'   => wps4d_get_smtp_setting( 'SMTP_SECURE' ) ?: 'Not set',
		'SMTP Port'     => wps4d_get_smtp_setting( 'SMTP_PORT' ) ?: 'Not set',
		'SMTP Debug'    => wps4d_get_smtp_setting( 'SMTP_DEBUG' ) ?: 'Not set',
		'SMTP From'     => wps4d_get_smtp_setting( 'SMTP_FROM' ) ?: 'Not set',
		'SMTP Name'     => wps4d_get_smtp_setting( 'SMTP_NAME' ) ?: 'Not set',
	];

	echo '<h2>Configuration Overview</h2>';
	echo '<table class="form-table">';

	foreach ( $settings as $name => $value ) {
		$display_name  = esc_html( $name );
		$display_value = esc_html( $value );

		echo "<tr><th>{$display_name}</th><td>{$display_value}</td></tr>";
	}

	echo '</table>';
}

function wps4d_display_constants_overview() {
	echo <<<HTML
		<h2>Constants Overview:</h2>
		<ul>
			<li><strong>SMTP_SERVER:</strong> The address of your SMTP server.</li>
			<li><strong>SMTP_USERNAME:</strong> The username or email used to authenticate with the SMTP server.</li>
			<li><strong>SMTP_PASSWORD:</strong> The password used to authenticate with the SMTP server.</li>
			<li><strong>SMTP_AUTH (optional):</strong> Whether to use SMTP authentication, or not. Defaults to true.</li>
			<li><strong>SMTP_SECURE (optional):</strong> Encryption method. Can be "tls", "ssl", or omitted. Defaults to "tls".</li>
			<li><strong>SMTP_PORT (optional):</strong> The port used by the SMTP server. Common values are 25, 465, and 587. Defaults to 587.</li>
			<li>
				<strong>SMTP_DEBUG (optional):</strong> Number value for debugging. Defaults to 0.
				<br>
				<strong>Available SMTP_DEBUG values are:</strong>
				<ul>
					<li>0: No output</li>
					<li>1: Commands</li>
					<li>2: Data and commands</li>
					<li>3: As 2 plus connection status</li>
					<li>4: Low-level data output</li>
				</ul>
			</li>
			<li><strong>SMTP_FROM (optional):</strong> The email address that emails will be sent from. Requires SMTP_NAME to be set.</li>
			<li><strong>SMTP_NAME (optional):</strong> The name that emails will be sent from. Requires SMTP_FROM to be set.</li>
		</ul>
		HTML;
}

function wps4d_display_example_configurations() {
	echo <<<HTML
		<h2>Example Configurations:</h2>
		
		<h3>Gmail:</h3>
		<pre>
		define( 'SMTP_SERVER', 'smtp.gmail.com' );
		define( 'SMTP_USERNAME', 'your-email@gmail.com' );
		define( 'SMTP_PASSWORD', 'your-gmail-password' );
		define( 'SMTP_PORT', '587' );
		define( 'SMTP_SECURE', 'tls' );
		</pre>
		<p>Note: Using Gmail requires allowing "less secure apps" in your Gmail settings, or you can use an "App Password".</p>
		
		<h3>Outlook:</h3>
		<pre>
		define( 'SMTP_SERVER', 'smtp.office365.com' );
		define( 'SMTP_USERNAME', 'your-email@outlook.com' );
		define( 'SMTP_PASSWORD', 'your-outlook-password' );
		define( 'SMTP_PORT', '587' );
		define( 'SMTP_SECURE', 'tls' );
		</pre>
		
		<h3>AWS SES (Simple Email Service):</h3>
		<pre>
		define( 'SMTP_SERVER', 'email-smtp.us-west-2.amazonaws.com' );
		define( 'SMTP_USERNAME', 'your-ses-smtp-username' );
		define( 'SMTP_PASSWORD', 'your-ses-smtp-password' );
		define( 'SMTP_PORT', '587' );
		define( 'SMTP_SECURE', 'tls' );
		</pre>
		<p>Note: Ensure your AWS SES account is out of the "sandbox" mode to send emails to any recipient.</p>
		HTML;
}
