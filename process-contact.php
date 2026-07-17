<?php
/**
 * public/process-contact.php
 *
 * Closed, asynchronous contact validation gateway.
 * Accepts a POST from the contact form, validates it, persists it,
 * and attempts to send a real-time email notification.
 *
 * Every response — success or failure — is a single JSON object so the
 * front-end fetch() handler has one predictable shape to parse.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/mailer.php';
$pdo = require __DIR__ . '/../config/database.php';

/**
 * Small helper to send a JSON response and stop execution, so every
 * exit point of this script has an identical, predictable shape.
 */
function respond(bool $success, string $message, array $extra = [], int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

// ---------------------------------------------------------------------
// 1. Only POST is allowed. Anything else (GET, PUT, etc.) is rejected
//    immediately — this endpoint has no business responding to them.
// ---------------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(false, 'Invalid request method.', [], 405);
}

// ---------------------------------------------------------------------
// 2. CSRF verification. This must happen before any other processing
//    of the request body.
// ---------------------------------------------------------------------
$submittedToken = $_POST['csrf_token'] ?? null;
if (!verifyCSRFToken($submittedToken)) {
    respond(false, 'Your session has expired. Please refresh the page and try again.', [], 419);
}

// ---------------------------------------------------------------------
// 3. Extract, trim, and validate input.
// ---------------------------------------------------------------------
$name    = cleanInput($_POST['name'] ?? '');
$email   = cleanInput($_POST['email'] ?? '');
$subject = cleanInput($_POST['subject'] ?? '');
$message = cleanInput($_POST['message'] ?? '');

$errors = [];

if ($name === '' || mb_strlen($name) > 100) {
    $errors['name'] = 'Please provide a valid name (max 100 characters).';
}

$validatedEmail = filter_var($email, FILTER_VALIDATE_EMAIL);
if ($validatedEmail === false || mb_strlen($email) > 150) {
    $errors['email'] = 'Please provide a valid email address.';
}

if ($subject === '' || mb_strlen($subject) > 255) {
    $errors['subject'] = 'Please provide a subject (max 255 characters).';
}

if ($message === '') {
    $errors['message'] = 'Message cannot be empty.';
}

if (!empty($errors)) {
    respond(false, 'Please correct the highlighted fields.', ['errors' => $errors], 422);
}

// ---------------------------------------------------------------------
// 4. Insert into contact_logs using a strongly-typed prepared statement.
//    Because config/database.php disables emulated prepares, this query
//    is compiled server-side with placeholders bound as real parameters
//    — user input can never alter the query's structure.
// ---------------------------------------------------------------------
try {
    $insertStatement = $pdo->prepare(
        'INSERT INTO contact_logs (name, email, subject, message, submitted_at)
         VALUES (:name, :email, :subject, :message, NOW())'
    );

    $insertStatement->bindValue(':name', $name, PDO::PARAM_STR);
    $insertStatement->bindValue(':email', $validatedEmail, PDO::PARAM_STR);
    $insertStatement->bindValue(':subject', $subject, PDO::PARAM_STR);
    $insertStatement->bindValue(':message', $message, PDO::PARAM_STR);
    $insertStatement->execute();
} catch (PDOException $exception) {
    error_log('[Contact Insert Error] ' . $exception->getMessage());
    respond(false, 'We could not save your message right now. Please try again shortly.', [], 500);
}

// ---------------------------------------------------------------------
// 5. Attempt to send the email notification. All user-supplied text is
//    passed through escapeHTML() before being embedded in the HTML
//    email body, preventing HTML/script injection into the notification
//    email itself (e.g. if opened in a webmail client that renders HTML).
// ---------------------------------------------------------------------
$safeName    = escapeHTML($name);
$safeEmail   = escapeHTML($validatedEmail);
$safeSubject = escapeHTML($subject);
$safeMessage = nl2br(escapeHTML($message), false);

$htmlBody = <<<HTML
<div style="font-family: Arial, Helvetica, sans-serif; max-width: 600px; margin: 0 auto;">
    <h2 style="color:#1a1a2e; border-bottom:2px solid #16213e; padding-bottom:8px;">
        New Contact Form Submission
    </h2>
    <table style="width:100%; border-collapse: collapse;">
        <tr>
            <td style="padding:8px 0; font-weight:bold; width:120px;">Name:</td>
            <td style="padding:8px 0;">{$safeName}</td>
        </tr>
        <tr>
            <td style="padding:8px 0; font-weight:bold;">Email:</td>
            <td style="padding:8px 0;">{$safeEmail}</td>
        </tr>
        <tr>
            <td style="padding:8px 0; font-weight:bold;">Subject:</td>
            <td style="padding:8px 0;">{$safeSubject}</td>
        </tr>
    </table>
    <div style="margin-top:16px; padding:16px; background:#f4f4f7; border-radius:6px;">
        <p style="margin:0; white-space:pre-line;">{$safeMessage}</p>
    </div>
</div>
HTML;

$plainBody = "New Contact Form Submission\n\n"
    . "Name: {$name}\n"
    . "Email: {$validatedEmail}\n"
    . "Subject: {$subject}\n\n"
    . "Message:\n{$message}\n";

try {
    sendContactNotification($name, $validatedEmail, "Portfolio Contact: {$subject}", $htmlBody, $plainBody);
    respond(true, 'Thank you! Your message has been sent successfully.');
} catch (\Throwable $mailException) {
    // The database insert already succeeded at this point, so we do NOT
    // treat this as a hard failure — we log it internally and tell the
    // user their message was received but notification is pending.
    error_log('[Contact Mail Error] ' . $mailException->getMessage());
    respond(
        true,
        'Your message was saved successfully. Email notification is pending — we will still see your message.',
        ['mail_dispatch' => 'pending']
    );
}
