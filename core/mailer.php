<?php
/**
 * core/mailer.php
 *
 * PHPMailer engine coupled with real-time SMTP delivery.
 *
 * Security principles applied:
 *  - Credentials are read from environment variables, never hard-coded,
 *    so this file is safe to commit to version control.
 *  - SMTPSecure is forced to STARTTLS on port 587, so credentials and
 *    message content are never sent in plaintext over the network.
 *  - The function only ever receives already-escaped HTML (escapeHTML()
 *    is applied by the caller before building the message body), so
 *    this file does not need to re-sanitize — but it never trusts
 *    "isHTML" content blindly for the plain-text alternative.
 *  - Uses Composer's autoloader; PHPMailer must be installed via
 *    `composer require phpmailer/phpmailer`.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Send a contact-form notification email.
 *
 * @param string $fromName     Escaped visitor name (for display only).
 * @param string $fromEmail    Visitor's validated email address.
 * @param string $subjectLine  Escaped subject line.
 * @param string $htmlBody     Fully escaped, pre-rendered HTML body.
 * @param string $plainBody    Plain-text fallback body.
 *
 * @throws PHPMailerException on delivery failure — the caller is
 *         responsible for catching this and deciding how to respond,
 *         per the "log it, tell the user it's pending" requirement.
 */
function sendContactNotification(
    string $fromName,
    string $fromEmail,
    string $subjectLine,
    string $htmlBody,
    string $plainBody
): bool {
    $mail = new PHPMailer(true); // true => throw exceptions on error.

    // --- SMTP transport configuration ---------------------------------
    $mail->isSMTP();
    $mail->Host       = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = getenv('SMTP_USERNAME') ?: 'your_gmail_address@gmail.com';
    // Use a Gmail "App Password" here, never the real account password.
    // Generate one at https://myaccount.google.com/apppasswords
    $mail->Password   = getenv('SMTP_APP_PASSWORD') ?: 'REPLACE_WITH_APP_PASSWORD';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Forces TLS on 587.
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';

    // --- Envelope -------------------------------------------------------
    $siteFromAddress = getenv('MAIL_FROM_ADDRESS') ?: $mail->Username;
    $siteFromName     = getenv('MAIL_FROM_NAME') ?: 'Portfolio Contact Form';
    $notifyToAddress  = getenv('MAIL_TO_ADDRESS') ?: $mail->Username;

    $mail->setFrom($siteFromAddress, $siteFromName);
    $mail->addAddress($notifyToAddress);
    // Reply-To is set to the visitor's own address so you can hit "Reply"
    // directly, without ever using their address as the SMTP envelope
    // sender (which would risk SPF/DKIM failures or spoofing complaints).
    $mail->addReplyTo($fromEmail, $fromName);

    $mail->isHTML(true);
    $mail->Subject = $subjectLine;
    $mail->Body    = $htmlBody;
    $mail->AltBody = $plainBody;

    // Let PHPMailer throw; caller wraps this in try/catch.
    return $mail->send();
}
