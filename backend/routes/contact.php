<?php
declare(strict_types=1);

if ($path !== '/api/contact') {
    return;
}

if ($method !== 'POST') {
    jsonResponse([
        'error' => 'Methode niet toegestaan',
    ], 405);
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if (!preg_match('~^application/json(?:\s*;|$)~i', $contentType)) {
    jsonResponse([
        'error' => 'Gebruik application/json',
    ], 415);
}

$rawBody = file_get_contents('php://input');

if ($rawBody === false || strlen($rawBody) > 16384) {
    jsonResponse([
        'error' => 'Aanvraag is te groot',
    ], 413);
}

$input = json_decode($rawBody, true);

if (!is_array($input) || array_is_list($input)) {
    jsonResponse([
        'error' => 'Ongeldige JSON',
    ], 422);
}

$honeypot = $input['website'] ?? '';

if (!is_string($honeypot)) {
    jsonResponse([
        'error' => 'Ongeldige invoer',
    ], 422);
}

if (trim($honeypot) !== '') {
    jsonResponse([
        'message' => 'Je bericht is verzonden',
    ]);
}

$name = $input['name'] ?? null;
$email = $input['email'] ?? null;
$subject = $input['subject'] ?? '';
$message = $input['message'] ?? null;

if (
    !is_string($name)
    || !is_string($email)
    || !is_string($subject)
    || !is_string($message)
) {
    jsonResponse([
        'error' => 'Naam, e-mailadres en bericht zijn verplicht',
    ], 422);
}

$name = trim($name);
$email = trim($email);
$subject = trim($subject);
$message = trim($message);

if (
    $name === ''
    || strlen($name) > 100
    || !filter_var($email, FILTER_VALIDATE_EMAIL)
    || strlen($email) > 190
    || strlen($subject) > 150
    || $message === ''
    || strlen($message) > 3000
) {
    jsonResponse([
        'error' => 'Controleer de ingevulde velden',
    ], 422);
}

$rateLimitFile = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
    . DIRECTORY_SEPARATOR
    . 'rgb-visuals-contact-rate-limit.json';
$rateLimitKey = hash(
    'sha256',
    (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown')
);
$rateLimitHandle = fopen($rateLimitFile, 'c+');

if ($rateLimitHandle === false || !flock($rateLimitHandle, LOCK_EX)) {
    if (is_resource($rateLimitHandle)) {
        fclose($rateLimitHandle);
    }

    jsonResponse([
        'error' => 'Verzenden is tijdelijk niet beschikbaar',
    ], 503);
}

$rateLimitData = stream_get_contents($rateLimitHandle);
$rateLimitData = json_decode($rateLimitData ?: '{}', true);
$now = time();
$windowSeconds = 900;
$maxRequests = 5;

if (!is_array($rateLimitData)) {
    $rateLimitData = [];
}

$recentRequests = array_values(array_filter(
    $rateLimitData[$rateLimitKey] ?? [],
    static fn ($timestamp): bool => is_int($timestamp)
        && $timestamp > ($now - $windowSeconds)
));

if (count($recentRequests) >= $maxRequests) {
    flock($rateLimitHandle, LOCK_UN);
    fclose($rateLimitHandle);

    jsonResponse([
        'error' => 'Te veel aanvragen. Probeer het later opnieuw.',
    ], 429);
}

$recentRequests[] = $now;
$rateLimitData[$rateLimitKey] = $recentRequests;

ftruncate($rateLimitHandle, 0);
rewind($rateLimitHandle);
fwrite($rateLimitHandle, json_encode($rateLimitData, JSON_THROW_ON_ERROR));
fflush($rateLimitHandle);
flock($rateLimitHandle, LOCK_UN);
fclose($rateLimitHandle);

$apiKey = getenv('RESEND_API_KEY');
$fromEmail = getenv('CONTACT_FROM_EMAIL');
$toEmail = getenv('CONTACT_TO_EMAIL') ?: 'r.g.b.janssen@st.hanze.nl';

if (
    !is_string($apiKey)
    || $apiKey === ''
    || !is_string($fromEmail)
    || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)
    || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)
) {
    jsonResponse([
        'error' => 'Contact is tijdelijk niet beschikbaar',
    ], 503);
}

$safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$safeEmail = htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$safeSubject = htmlspecialchars(
    $subject !== '' ? $subject : 'Contact via RGB Visuals',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
$safeMessage = nl2br(
    htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
);
$mailSubject = $subject !== '' ? $subject : 'Contact via RGB Visuals';
$payload = json_encode([
    'from' => $fromEmail,
    'to' => [$toEmail],
    'reply_to' => [$email],
    'subject' => $mailSubject,
    'html' => sprintf(
        '<h2>Nieuw bericht via RGB Visuals</h2>'
        . '<p><strong>Naam:</strong> %s<br>'
        . '<strong>E-mailadres:</strong> %s<br>'
        . '<strong>Onderwerp:</strong> %s</p>'
        . '<p><strong>Bericht:</strong></p><p>%s</p>',
        $safeName,
        $safeEmail,
        $safeSubject,
        $safeMessage
    ),
], JSON_THROW_ON_ERROR);

$curl = curl_init('https://api.resend.com/emails');

if ($curl === false) {
    jsonResponse([
        'error' => 'Bericht kon niet worden verzonden',
    ], 502);
}

curl_setopt_array($curl, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 15,
]);

$response = curl_exec($curl);
$httpStatus = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
$curlError = curl_error($curl);
curl_close($curl);

if ($response === false || $curlError !== '' || $httpStatus < 200 || $httpStatus >= 300) {
    error_log('RGB Visuals contactmail mislukt');

    jsonResponse([
        'error' => 'Bericht kon niet worden verzonden. Probeer het later opnieuw.',
    ], 502);
}

jsonResponse([
    'message' => 'Bedankt, je bericht is verzonden.',
]);