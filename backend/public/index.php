<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/*
|--------------------------------------------------------------------------
| Hulpfuncties
|--------------------------------------------------------------------------
*/


function database(): PDO
{
    $dockerHost = getenv('DB_HOST');

    if ($dockerHost !== false && $dockerHost !== '') {
        $config = [
            'host' => $dockerHost,
            'port' => (int) (getenv('DB_PORT') ?: 3306),
            'database' => getenv('DB_NAME') ?: '',
            'username' => getenv('DB_USER') ?: '',
            'password' => getenv('DB_PASSWORD') ?: '',
        ];
    } else {
        // Bestaande Laragon-configuratie blijft werken.
        $config = require __DIR__ . '/../config/database.local.php';
    }

    return new PDO(
        sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $config['host'],
            $config['port'],
            $config['database']
        ),
        $config['username'],
        $config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
}

function jsonResponse(array $data, int $status = 200): never
{
    http_response_code($status);

    echo json_encode($data, JSON_THROW_ON_ERROR);

    exit;
}

function enforceAdminAccess(PDO $pdo): void
{
    if (!isset($_SESSION['admin_id'])) {
        jsonResponse([
            'error' => 'Niet ingelogd',
        ], 401);
    }

    $statement = $pdo->prepare(
        'SELECT id
         FROM admin_users
         WHERE id = :id
           AND is_active = 1
         LIMIT 1'
    );

    $statement->execute([
        'id' => (int) $_SESSION['admin_id'],
    ]);

    if (!$statement->fetch()) {
        jsonResponse([
            'error' => 'Geen toegang',
        ], 403);
    }
}

function enforceAdminOrigin(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if (
        $origin !== ''
        && !in_array(
            $origin,
            [
                'http://localhost:5173',
                'http://127.0.0.1:5173',
            ],
            true
        )
    ) {
        jsonResponse([
            'error' => 'Ongeldige aanvraag',
        ], 403);
    }
}

function imageFileDetails(string $temporaryPath, int $fileSize): array
{
    if (
        $fileSize <= 0
        || $fileSize > 2 * 1024 * 1024
        || !is_uploaded_file($temporaryPath)
    ) {
        jsonResponse([
            'error' => 'Ongeldig bestand of bestand groter dan 2 MB',
        ], 422);
    }

    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $fileInfo->file($temporaryPath);

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!is_string($mimeType) || !isset($allowedTypes[$mimeType])) {
        jsonResponse([
            'error' => 'Alleen JPG, PNG en WebP zijn toegestaan',
        ], 422);
    }

    $imageInfo = @getimagesize($temporaryPath);

    if (
        $imageInfo === false
        || ($imageInfo['mime'] ?? '') !== $mimeType
    ) {
        jsonResponse([
            'error' => 'Het bestand is geen geldige afbeelding',
        ], 422);
    }

    $width = (int) $imageInfo[0];
    $height = (int) $imageInfo[1];

    if (
        $width <= 0
        || $height <= 0
        || $width > 6000
        || $height > 6000
        || $width * $height > 20000000
    ) {
        jsonResponse([
            'error' => 'De afmetingen van de afbeelding zijn te groot',
        ], 422);
    }

    return [
        'mime_type' => $mimeType,
        'extension' => $allowedTypes[$mimeType],
    ];
}

function pdfFileDetails(string $temporaryPath, int $fileSize): void
{
    if (
        $fileSize <= 0
        || $fileSize > 10 * 1024 * 1024
        || !is_uploaded_file($temporaryPath)
    ) {
        jsonResponse([
            'error' => 'Ongeldig bestand of bestand groter dan 10 MB',
        ], 422);
    }

    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $fileInfo->file($temporaryPath);
    $header = file_get_contents($temporaryPath, false, null, 0, 5);

    if ($mimeType !== 'application/pdf' || $header !== '%PDF-') {
        jsonResponse([
            'error' => 'Upload uitsluitend een geldig PDF-bestand',
        ], 422);
    }
}

function safeOriginalPdfName(mixed $value): string
{
    $name = is_string($value) ? basename($value) : 'document.pdf';
    $name = trim($name);
    $name = preg_replace('/[\x00-\x1F\x7F]/', '', $name) ?? '';

    if ($name === '') {
        $name = 'document.pdf';
    }

    return substr($name, 0, 255);
}

function projectDocumentInput(array $input, bool $partial = false): array
{
    $fields = [];

    if (!$partial || array_key_exists('title', $input)) {
        if (!is_string($input['title'] ?? null)) {
            jsonResponse([
                'error' => 'Een documenttitel is verplicht',
            ], 422);
        }

        $title = trim($input['title']);

        if ($title === '' || strlen($title) > 150) {
            jsonResponse([
                'error' => 'De documenttitel is ongeldig of te lang',
            ], 422);
        }

        $fields['title'] = $title;
    }

    if (array_key_exists('sort_order', $input)) {
        $sortOrder = $input['sort_order'];

        if (
            (!is_int($sortOrder) && !is_string($sortOrder))
            || (is_string($sortOrder) && !preg_match('/^\d+$/', $sortOrder))
            || (int) $sortOrder > 1000000
        ) {
            jsonResponse([
                'error' => 'Ongeldige documentvolgorde',
            ], 422);
        }

        $fields['sort_order'] = (int) $sortOrder;
    }

    return $fields;
}

require __DIR__ . '/../routes/contact.php';

/**
 * Controleert de extra casestudyvelden.
 *
 * Ontbrekende velden krijgen null.
 * Daardoor blijven bestaande casestudygegevens bij een UPDATE behouden.
 */
function caseStudyInput(array $input): array
{
    $fields = [];

    foreach (['role', 'challenge', 'solution', 'result'] as $field) {
        if (!array_key_exists($field, $input)) {
            $fields[$field] = null;
            continue;
        }

        if (!is_string($input[$field])) {
            jsonResponse([
                'error' => 'Ongeldige casestudygegevens',
            ], 422);
        }

        $value = trim($input[$field]);

        $maxLength = $field === 'role' ? 150 : 10000;

        if (strlen($value) > $maxLength) {
            jsonResponse([
                'error' => 'Een casestudyveld is te lang',
            ], 422);
        }

        $fields[$field] = $value;
    }

    if (!array_key_exists('technologies', $input)) {
        $fields['technologies'] = null;

        return $fields;
    }

    $technologies = $input['technologies'];

    if (
        !is_array($technologies)
        || !array_is_list($technologies)
        || count($technologies) > 20
    ) {
        jsonResponse([
            'error' => 'Ongeldige technologieën',
        ], 422);
    }

    $cleanTechnologies = [];

    foreach ($technologies as $technology) {
        if (!is_string($technology)) {
            jsonResponse([
                'error' => 'Ongeldige technologieën',
            ], 422);
        }

        $technology = trim($technology);

        if ($technology === '' || strlen($technology) > 60) {
            jsonResponse([
                'error' => 'Controleer de technologieën',
            ], 422);
        }

        $cleanTechnologies[] = $technology;
    }

    $fields['technologies'] = json_encode(
        $cleanTechnologies,
        JSON_THROW_ON_ERROR
    );

    return $fields;
}

function validProjectLinkUrl(string $url): bool
{
    if (strlen($url) > 2048 || filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }

    $parts = parse_url($url);

    return is_array($parts)
        && strtolower($parts['scheme'] ?? '') === 'https'
        && is_string($parts['host'] ?? null)
        && $parts['host'] !== ''
        && !isset($parts['user'], $parts['pass']);
}

function projectLinkInput(array $input, bool $partial = false): array
{
    $fields = [];

    if (!$partial || array_key_exists('title', $input)) {
        if (!is_string($input['title'] ?? null)) {
            jsonResponse([
                'error' => 'Een linktitel is verplicht',
            ], 422);
        }

        $title = trim($input['title']);

        if ($title === '' || strlen($title) > 150) {
            jsonResponse([
                'error' => 'De linktitel is ongeldig of te lang',
            ], 422);
        }

        $fields['title'] = $title;
    }

    if (!$partial || array_key_exists('url', $input)) {
        if (!is_string($input['url'] ?? null)) {
            jsonResponse([
                'error' => 'Een HTTPS-URL is verplicht',
            ], 422);
        }

        $url = trim($input['url']);

        if (!validProjectLinkUrl($url)) {
            jsonResponse([
                'error' => 'Gebruik een geldige HTTPS-URL',
            ], 422);
        }

        $fields['url'] = $url;
    }

    if (!$partial || array_key_exists('type', $input)) {
        if (
            !is_string($input['type'] ?? null)
            || !in_array(
                $input['type'],
                ['document', 'website', 'prototype', 'other'],
                true
            )
        ) {
            jsonResponse([
                'error' => 'Ongeldig linktype',
            ], 422);
        }

        $fields['type'] = $input['type'];
    }

    if (array_key_exists('sort_order', $input)) {
        $sortOrder = $input['sort_order'];

        if (
            (!is_int($sortOrder) && !is_string($sortOrder))
            || (is_string($sortOrder) && !preg_match('/^\d+$/', $sortOrder))
            || (int) $sortOrder > 1000000
        ) {
            jsonResponse([
                'error' => 'Ongeldige linkvolgorde',
            ], 422);
        }

        $fields['sort_order'] = (int) $sortOrder;
    }

    return $fields;
}

function requireProject(PDO $pdo, int $projectId): void
{
    $statement = $pdo->prepare(
        'SELECT id
         FROM projects
         WHERE id = :id
         LIMIT 1'
    );
    $statement->execute([
        'id' => $projectId,
    ]);

    if (!$statement->fetch()) {
        jsonResponse([
            'error' => 'Project niet gevonden',
        ], 404);
    }
}

/*
|--------------------------------------------------------------------------
| Openbare API: health-check
|--------------------------------------------------------------------------
*/

if ($path === '/api/health' && $method === 'GET') {
    jsonResponse([
        'status' => 'ok',
        'message' => 'RGB Visuals API werkt',
    ]);
}

/*
|--------------------------------------------------------------------------
| Openbare API: gepubliceerde projecten
|--------------------------------------------------------------------------
*/

if ($path === '/api/projects' && $method === 'GET') {
    try {
        $statement = database()->query(
            "SELECT
                id,
                title,
                slug,
                summary,
                description,
                client,
                year,
                role,
                technologies,
                challenge,
                solution,
                result,
                cover_image,
                created_at
             FROM projects
             WHERE status = 'published'
             ORDER BY created_at DESC, id DESC"
        );

        jsonResponse([
            'projects' => $statement->fetchAll(),
        ]);
    } catch (Throwable $exception) {
        error_log(
            'RGB Visuals projectenfout: ' . $exception->getMessage()
        );

        jsonResponse([
            'error' => 'Projecten konden niet worden opgehaald',
        ], 500);
    }
}

/*
|--------------------------------------------------------------------------
| Openbare API: galerij van een gepubliceerd project
|--------------------------------------------------------------------------
*/

if (
    preg_match(
        '~^/api/projects/([1-9][0-9]*)/images$~',
        $path,
        $matches
    )
    && $method === 'GET'
) {
    try {
        $statement = database()->prepare(
            'SELECT
                project_images.id,
                project_images.alt_text
             FROM project_images
             INNER JOIN projects
                ON projects.id = project_images.project_id
             WHERE project_images.project_id = :project_id
               AND projects.status = :status
             ORDER BY project_images.sort_order ASC, project_images.id ASC'
        );

        $projectId = (int) $matches[1];

        $statement->execute([
            'project_id' => $projectId,
            'status' => 'published',
        ]);

        $images = array_map(
            static function (array $image) use ($projectId): array {
                $imageId = (int) $image['id'];

                return [
                    'id' => $imageId,
                    'alt_text' => $image['alt_text'],
                    'image_url' => sprintf(
                        '/api/projects/%d/images/%d/file',
                        $projectId,
                        $imageId
                    ),
                ];
            },
            $statement->fetchAll()
        );

        jsonResponse([
            'images' => $images,
        ]);
    } catch (Throwable $exception) {
        error_log(
            'RGB Visuals openbare galerij ophalen mislukt: '
            . $exception->getMessage()
        );

        jsonResponse([
            'error' => 'Galerij kon niet worden opgehaald',
        ], 500);
    }
}

if (
    preg_match(
        '~^/api/projects/([1-9][0-9]*)/images/([1-9][0-9]*)/file$~',
        $path,
        $matches
    )
    && $method === 'GET'
) {
    try {
        $statement = database()->prepare(
            'SELECT project_images.filename
             FROM project_images
             INNER JOIN projects
                ON projects.id = project_images.project_id
             WHERE project_images.project_id = :project_id
               AND project_images.id = :image_id
               AND projects.status = :status
             LIMIT 1'
        );

        $statement->execute([
            'project_id' => (int) $matches[1],
            'image_id' => (int) $matches[2],
            'status' => 'published',
        ]);

        $image = $statement->fetch();

        if (!$image || !is_string($image['filename'])) {
            jsonResponse([
                'error' => 'Afbeelding niet gevonden',
            ], 404);
        }

        if (
            !preg_match(
                '/\A[a-f0-9]{40}\.(jpg|png|webp)\z/',
                $image['filename'],
                $fileMatches
            )
        ) {
            jsonResponse([
                'error' => 'Afbeelding niet gevonden',
            ], 404);
        }

        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
        ];
        $filePath = __DIR__ . '/../storage/gallery/' . $image['filename'];

        if (!is_file($filePath) || !is_readable($filePath)) {
            jsonResponse([
                'error' => 'Afbeelding niet gevonden',
            ], 404);
        }

        $fileInfo = new finfo(FILEINFO_MIME_TYPE);
        $actualType = $fileInfo->file($filePath);

        if ($actualType !== $mimeTypes[$fileMatches[1]]) {
            jsonResponse([
                'error' => 'Afbeelding niet gevonden',
            ], 404);
        }

        header('Content-Type: ' . $actualType);
        header('Cache-Control: public, max-age=3600');

        $fileSize = filesize($filePath);

        if ($fileSize !== false) {
            header('Content-Length: ' . $fileSize);
        }

        readfile($filePath);
        exit;
    } catch (Throwable $exception) {
        error_log(
            'RGB Visuals openbare galerijafbeelding ophalen mislukt: '
            . $exception->getMessage()
        );

        jsonResponse([
            'error' => 'Afbeelding kon niet worden opgehaald',
        ], 404);
    }
}

/*
|--------------------------------------------------------------------------
| Openbare API: links van een gepubliceerd project
|--------------------------------------------------------------------------
*/

if (
    preg_match(
        '~^/api/projects/([1-9][0-9]*)/links$~',
        $path,
        $matches
    )
    && $method === 'GET'
) {
    try {
        $statement = database()->prepare(
            'SELECT
                project_links.id,
                project_links.title,
                project_links.url,
                project_links.type,
                project_links.sort_order
             FROM project_links
             INNER JOIN projects
                ON projects.id = project_links.project_id
             WHERE project_links.project_id = :project_id
               AND projects.status = :status
             ORDER BY project_links.sort_order ASC, project_links.id ASC'
        );

        $statement->execute([
            'project_id' => (int) $matches[1],
            'status' => 'published',
        ]);

        $links = array_map(
            static function (array $link): array {
                $link['id'] = (int) $link['id'];
                $link['sort_order'] = (int) $link['sort_order'];

                return $link;
            },
            $statement->fetchAll()
        );

        jsonResponse([
            'links' => $links,
        ]);
    } catch (Throwable $exception) {
        error_log(
            'RGB Visuals openbare projectlinks ophalen mislukt: '
            . $exception->getMessage()
        );

        jsonResponse([
            'error' => 'Projectlinks konden niet worden opgehaald',
        ], 500);
    }
}

/*
|--------------------------------------------------------------------------
| Openbare API: documenten van een gepubliceerd project
|--------------------------------------------------------------------------
*/

if (
    preg_match(
        '~^/api/projects/([1-9][0-9]*)/documents$~',
        $path,
        $matches
    )
    && $method === 'GET'
) {
    try {
        $projectId = (int) $matches[1];
        $statement = database()->prepare(
            'SELECT
                project_documents.id,
                project_documents.title,
                project_documents.original_name,
                project_documents.sort_order
             FROM project_documents
             INNER JOIN projects
                ON projects.id = project_documents.project_id
             WHERE project_documents.project_id = :project_id
               AND projects.status = :status
             ORDER BY project_documents.sort_order ASC, project_documents.id ASC'
        );
        $statement->execute([
            'project_id' => $projectId,
            'status' => 'published',
        ]);

        $documents = array_map(
            static function (array $document) use ($projectId): array {
                $documentId = (int) $document['id'];

                return [
                    'id' => $documentId,
                    'title' => $document['title'],
                    'original_name' => $document['original_name'],
                    'sort_order' => (int) $document['sort_order'],
                    'download_url' => sprintf(
                        '/api/projects/%d/documents/%d/download',
                        $projectId,
                        $documentId
                    ),
                ];
            },
            $statement->fetchAll()
        );

        jsonResponse([
            'documents' => $documents,
        ]);
    } catch (Throwable $exception) {
        error_log(
            'RGB Visuals openbare documenten ophalen mislukt: '
            . $exception->getMessage()
        );

        jsonResponse([
            'error' => 'Documenten konden niet worden opgehaald',
        ], 500);
    }
}

if (
    preg_match(
        '~^/api/projects/([1-9][0-9]*)/documents/([1-9][0-9]*)/download$~',
        $path,
        $matches
    )
    && $method === 'GET'
) {
    try {
        $statement = database()->prepare(
            'SELECT
                project_documents.filename,
                project_documents.original_name
             FROM project_documents
             INNER JOIN projects
                ON projects.id = project_documents.project_id
             WHERE project_documents.project_id = :project_id
               AND project_documents.id = :document_id
               AND projects.status = :status
             LIMIT 1'
        );
        $statement->execute([
            'project_id' => (int) $matches[1],
            'document_id' => (int) $matches[2],
            'status' => 'published',
        ]);

        $document = $statement->fetch();

        if (
            !$document
            || !is_string($document['filename'])
            || !is_string($document['original_name'])
        ) {
            jsonResponse([
                'error' => 'Document niet gevonden',
            ], 404);
        }

        if (!preg_match('/\A[a-f0-9]{40}\.pdf\z/', $document['filename'])) {
            jsonResponse([
                'error' => 'Document niet gevonden',
            ], 404);
        }

        $filePath = __DIR__ . '/../storage/documents/' . $document['filename'];

        if (!is_file($filePath) || !is_readable($filePath)) {
            jsonResponse([
                'error' => 'Document niet gevonden',
            ], 404);
        }

        $fileInfo = new finfo(FILEINFO_MIME_TYPE);

        if (
            $fileInfo->file($filePath) !== 'application/pdf'
            || file_get_contents($filePath, false, null, 0, 5) !== '%PDF-'
        ) {
            jsonResponse([
                'error' => 'Document niet gevonden',
            ], 404);
        }

        $safeName = safeOriginalPdfName($document['original_name']);
        $asciiName = preg_replace('/[^A-Za-z0-9._-]/', '_', $safeName) ?? 'document.pdf';
        $encodedName = rawurlencode($safeName);

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $asciiName . '"; filename*=UTF-8\'\'' . $encodedName);
        header('X-Content-Type-Options: nosniff');

        $fileSize = filesize($filePath);

        if ($fileSize !== false) {
            header('Content-Length: ' . $fileSize);
        }

        readfile($filePath);
        exit;
    } catch (Throwable $exception) {
        error_log(
            'RGB Visuals openbaar document downloaden mislukt: '
            . $exception->getMessage()
        );

        jsonResponse([
            'error' => 'Document kon niet worden gedownload',
        ], 404);
    }
}

/*
|--------------------------------------------------------------------------
| Sessies starten voor authenticatie
|--------------------------------------------------------------------------
*/

if (
    $path === '/api/auth/login'
    || $path === '/api/auth/me'
    || $path === '/api/auth/logout'
) {
    require __DIR__ . '/../config/session.php';
}

/*
|--------------------------------------------------------------------------
| Inloggen
|--------------------------------------------------------------------------
*/

if ($path === '/api/auth/login' && $method === 'POST') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (!preg_match('~^application/json(?:\s*;|$)~i', $contentType)) {
        jsonResponse([
            'error' => 'Gebruik application/json',
        ], 415);
    }

    $input = json_decode(
        file_get_contents('php://input'),
        true
    );

    if (
        !is_array($input)
        || !is_string($input['email'] ?? null)
        || !is_string($input['password'] ?? null)
    ) {
        jsonResponse([
            'error' => 'Ongeldige invoer',
        ], 400);
    }

    $email = trim($input['email']);
    $password = $input['password'];

    if (
        !filter_var($email, FILTER_VALIDATE_EMAIL)
        || $password === ''
        || strlen($email) > 190
        || strlen($password) > 4096
    ) {
        jsonResponse([
            'error' => 'Ongeldige invoer',
        ], 400);
    }

    try {
        $statement = database()->prepare(
            'SELECT id, name, email, password_hash
             FROM admin_users
             WHERE email = :email
               AND is_active = 1
             LIMIT 1'
        );
        
        $statement->execute([
            'email' => $email,
        ]);

        $admin = $statement->fetch();

        if (
            !$admin
            || !password_verify($password, $admin['password_hash'])
        ) {
            jsonResponse([
                'error' => 'E-mailadres of wachtwoord onjuist',
            ], 401);
        }

        session_regenerate_id(true);

        $_SESSION['admin_id'] = (int) $admin['id'];

        jsonResponse([
            'authenticated' => true,
            'user' => [
                'id' => (int) $admin['id'],
                'name' => $admin['name'],
                'email' => $admin['email'],
            ],
        ]);
    } catch (Throwable $exception) {
        error_log(
            'RGB Visuals loginfout: ' . $exception->getMessage()
        );
        jsonResponse([
            'error' => 'Inloggen is tijdelijk niet mogelijk',
        ], 500);
    }
}

/*
|--------------------------------------------------------------------------
| Huidige sessie controleren
|--------------------------------------------------------------------------
*/

if ($path === '/api/auth/me' && $method === 'GET') {
    if (!isset($_SESSION['admin_id'])) {
        jsonResponse([
            'authenticated' => false,
        ], 401);
    }

    try {
        $statement = database()->prepare(
            'SELECT id, name, email
             FROM admin_users
             WHERE id = :id
               AND is_active = 1
             LIMIT 1'
        );

        $statement->execute([
            'id' => (int) $_SESSION['admin_id'],
        ]);

        $admin = $statement->fetch();

        if (!$admin) {
            $_SESSION = [];

            session_regenerate_id(true);

            jsonResponse([
                'authenticated' => false,
            ], 401);
        }

        jsonResponse([
            'authenticated' => true,
            'user' => [
                'id' => (int) $admin['id'],
                'name' => $admin['name'],
                'email' => $admin['email'],
            ],
        ]);
    } catch (Throwable $exception) {
        error_log(
            'RGB Visuals sessiefout: ' . $exception->getMessage()
        );

        jsonResponse([
            'error' => 'Sessie kon niet worden gecontroleerd',
        ], 500);
    }
}

/*
|--------------------------------------------------------------------------
| Uitloggen
|--------------------------------------------------------------------------
*/

if ($path === '/api/auth/logout' && $method === 'POST') {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if ($origin !== '') {
        $allowedOrigins = [
            'http://localhost:5173',
            'http://127.0.0.1:5173',
        ];

        if (!in_array($origin, $allowedOrigins, true)) {
            jsonResponse([
                'error' => 'Ongeldige aanvraag',
            ], 403);
        }
    }

    $_SESSION = [];

    session_regenerate_id(true);

    jsonResponse([
        'authenticated' => false,
    ]);
}

/*
|--------------------------------------------------------------------------
| CMS: project aanmaken
|--------------------------------------------------------------------------
*/

if ($path === '/api/admin/projects' && $method === 'POST') {
    require __DIR__ . '/../config/session.php';

    if (!isset($_SESSION['admin_id'])) {
        jsonResponse([
            'error' => 'Niet ingelogd',
        ], 401);
    }

    try {
        $pdo = database();

        // Controleer of de beheerder nog actief is.
        $adminStatement = $pdo->prepare(
            'SELECT id
             FROM admin_users
             WHERE id = :id
               AND is_active = 1
             LIMIT 1'
        );

        $adminStatement->execute([
            'id' => (int) $_SESSION['admin_id'],
        ]);

        if (!$adminStatement->fetch()) {
            jsonResponse([
                'error' => 'Geen toegang',
            ], 403);
        }

        // Alleen JSON accepteren.
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (!preg_match('~^application/json(?:\s*;|$)~i', $contentType)) {
            jsonResponse([
                'error' => 'Gebruik application/json',
            ], 415);
        }

        // Lokale ontwikkelomgeving: toegestane herkomst.
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if (
            $origin !== ''
            && !in_array(
                $origin,
                [
                    'http://localhost:5173',
                    'http://127.0.0.1:5173',
                ],
                true
            )
        ) {
            jsonResponse([
                'error' => 'Ongeldige aanvraag',
            ], 403);
        }

        $rawBody = file_get_contents('php://input');

        if ($rawBody === false || strlen($rawBody) > 65536) {
            jsonResponse([
                'error' => 'Aanvraag is te groot',
            ], 413);
        }

        $input = json_decode($rawBody, true);

        if (!is_array($input)) {
            jsonResponse([
                'error' => 'Ongeldige JSON',
            ], 400);
        }

        // Basisgegevens.
        $title = $input['title'] ?? null;
        $description = $input['description'] ?? null;
        $client = $input['client'] ?? null;
        $year = $input['year'] ?? null;
        $status = $input['status'] ?? null;

        if (
            !is_string($title)
            || !is_string($description)
            || !is_string($client)
            || !is_string($year)
            || !is_string($status)
        ) {
            jsonResponse([
                'error' => 'Ongeldige projectgegevens',
            ], 400);
        }

        $title = trim($title);
        $description = trim($description);
        $client = trim($client);

        if (
            $title === ''
            || strlen($title) > 150
            || $description === ''
            || strlen($description) > 10000
            || $client === ''
            || strlen($client) > 150
            || !preg_match('/^\d{4}$/', $year)
            || (int) $year < 2000
            || (int) $year > 2100
            || !in_array($status, ['draft', 'published'], true)
        ) {
            jsonResponse([
                'error' => 'Controleer de ingevulde velden',
            ], 422);
        }

        // Controleer de vijf casestudyvelden.
        $caseStudy = caseStudyInput($input);

        // Maak de basis voor de project-URL.
        $slugBase = strtolower($title);

        $slugBase = preg_replace(
            '/[^a-z0-9]+/',
            '-',
            $slugBase
        );

        $slugBase = trim($slugBase ?? '', '-');

        if ($slugBase === '') {
            $slugBase = 'project';
        }

        $slugBase = substr($slugBase, 0, 160);

        // Maak het project aan.
        $statement = $pdo->prepare(
            'INSERT INTO projects
                (
                    title,
                    slug,
                    summary,
                    description,
                    client,
                    year,
                    status,
                    role,
                    technologies,
                    challenge,
                    solution,
                    result
                )
             VALUES
                (
                    :title,
                    :slug,
                    :summary,
                    :description,
                    :client,
                    :year,
                    :status,
                    :role,
                    :technologies,
                    :challenge,
                    :solution,
                    :result
                )'
        );

        // Tijdelijke unieke slug voor de eerste INSERT.
        $temporarySlug = 'nieuw-project-' . bin2hex(random_bytes(12));

        $statement->execute([
            'title' => $title,
            'slug' => $temporarySlug,
            'summary' => $description,
            'description' => $description,
            'client' => $client,
            'year' => (int) $year,
            'status' => $status,
            ...$caseStudy,
        ]);

        // Maak de definitieve slug met het project-ID.
        $projectId = (int) $pdo->lastInsertId();

        $finalSlug = $slugBase . '-' . $projectId;

        $updateStatement = $pdo->prepare(
            'UPDATE projects
             SET slug = :slug
             WHERE id = :id'
        );

        $updateStatement->execute([
            'slug' => $finalSlug,
            'id' => $projectId,
        ]);

        jsonResponse([
            'message' => 'Project opgeslagen',
            'project' => [
                'id' => $projectId,
                'title' => $title,
                'slug' => $finalSlug,
                'summary' => $description,
                'description' => $description,
                'client' => $client,
                'year' => (int) $year,
                'status' => $status,
            ],
        ], 201);
    } catch (Throwable $exception) {
        error_log(
            'RGB Visuals project opslaan mislukt: '
            . $exception->getMessage()
        );

        jsonResponse([
            'error' => 'Project kon niet worden opgeslagen',
        ], 500);
    }
}

/*
|--------------------------------------------------------------------------
| CMS: alle projecten ophalen
|--------------------------------------------------------------------------
*/

if ($path === '/api/admin/projects' && $method === 'GET') {
    require __DIR__ . '/../config/session.php';

    if (!isset($_SESSION['admin_id'])) {
        jsonResponse([
            'error' => 'Niet ingelogd',
        ], 401);
    }

    try {
        $pdo = database();

        $adminStatement = $pdo->prepare(
            'SELECT id
             FROM admin_users
             WHERE id = :id
               AND is_active = 1
             LIMIT 1'
        );

        $adminStatement->execute([
            'id' => (int) $_SESSION['admin_id'],
        ]);

        if (!$adminStatement->fetch()) {
            jsonResponse([
                'error' => 'Geen toegang',
            ], 403);
        }

        $statement = $pdo->query(
            'SELECT
                id,
                title,
                slug,
                summary,
                description,
                client,
                year,
                role,
                technologies,
                challenge,
                solution,
                result,
                cover_image,
                status,
                created_at,
                updated_at
             FROM projects
             ORDER BY created_at DESC, id DESC'
        );

        jsonResponse([
            'projects' => $statement->fetchAll(),
        ]);
    } catch (Throwable $exception) {
        error_log(
            'RGB Visuals adminprojecten ophalen mislukt: '
            . $exception->getMessage()
        );

        jsonResponse([
            'error' => 'Projecten konden niet worden opgehaald',
        ], 500);
    }
}

/*
|--------------------------------------------------------------------------
| CMS: bestaand project bewerken
|--------------------------------------------------------------------------
*/

if (
    preg_match(
        '~^/api/admin/projects/([1-9][0-9]*)$~',
        $path,
        $matches
    )
    && $method === 'PUT'
) {
    require __DIR__ . '/../config/session.php';

    if (!isset($_SESSION['admin_id'])) {
        jsonResponse([
            'error' => 'Niet ingelogd',
        ], 401);
    }

    // Lokale ontwikkelomgeving: toegestane herkomst.
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if (
        $origin !== ''
        && !in_array(
            $origin,
            [
                'http://localhost:5173',
                'http://127.0.0.1:5173',
            ],
            true
        )
    ) {
        jsonResponse([
            'error' => 'Ongeldige aanvraag',
        ], 403);
    }

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (!preg_match('~^application/json(?:\s*;|$)~i', $contentType)) {
        jsonResponse([
            'error' => 'Gebruik application/json',
        ], 415);
    }

    $rawBody = file_get_contents('php://input');

    if ($rawBody === false || strlen($rawBody) > 65536) {
        jsonResponse([
            'error' => 'Aanvraag is te groot',
        ], 413);
    }

    $input = json_decode($rawBody, true);

    if (!is_array($input)) {
        jsonResponse([
            'error' => 'Ongeldige JSON',
        ], 400);
    }

    // Basisgegevens.
    $title = $input['title'] ?? null;
    $description = $input['description'] ?? null;
    $client = $input['client'] ?? null;
    $year = $input['year'] ?? null;
    $status = $input['status'] ?? null;

    if (
        !is_string($title)
        || !is_string($description)
        || !is_string($client)
        || !is_string($year)
        || !is_string($status)
    ) {
        jsonResponse([
            'error' => 'Ongeldige projectgegevens',
        ], 400);
    }

    $title = trim($title);
    $description = trim($description);
    $client = trim($client);

    if (
        $title === ''
        || strlen($title) > 150
        || $description === ''
        || strlen($description) > 10000
        || $client === ''
        || strlen($client) > 150
        || !preg_match('/^\d{4}$/', $year)
        || (int) $year < 2000
        || (int) $year > 2100
        || !in_array($status, ['draft', 'published'], true)
    ) {
        jsonResponse([
            'error' => 'Controleer de ingevulde velden',
        ], 422);
    }

    try {
        $pdo = database();

        // Controleer of de beheerder nog actief is.
        $adminStatement = $pdo->prepare(
            'SELECT id
             FROM admin_users
             WHERE id = :id
               AND is_active = 1
             LIMIT 1'
        );

        $adminStatement->execute([
            'id' => (int) $_SESSION['admin_id'],
        ]);

        if (!$adminStatement->fetch()) {
            jsonResponse([
                'error' => 'Geen toegang',
            ], 403);
        }

        $projectId = (int) $matches[1];

        // Controleer de vijf casestudyvelden.
        $caseStudy = caseStudyInput($input);

        // Werk het project bij.
        $statement = $pdo->prepare(
            'UPDATE projects
             SET
                title = :title,
                summary = :summary,
                description = :description,
                client = :client,
                year = :year,
                status = :status,
                role = COALESCE(:role, role),
                technologies = COALESCE(:technologies, technologies),
                challenge = COALESCE(:challenge, challenge),
                solution = COALESCE(:solution, solution),
                result = COALESCE(:result, result)
             WHERE id = :id'
        );

        $statement->execute([
            'title' => $title,
            'summary' => $description,
            'description' => $description,
            'client' => $client,
            'year' => (int) $year,
            'status' => $status,
            'id' => $projectId,
            ...$caseStudy,
        ]);

        // Haal het bijgewerkte project op.
        $checkStatement = $pdo->prepare(
            'SELECT
                id,
                title,
                slug,
                summary,
                description,
                client,
                year,
                role,
                technologies,
                challenge,
                solution,
                result,
                status
             FROM projects
             WHERE id = :id
             LIMIT 1'
        );

        $checkStatement->execute([
            'id' => $projectId,
        ]);

        $project = $checkStatement->fetch();

        if (!$project) {
            jsonResponse([
                'error' => 'Project niet gevonden',
            ], 404);
        }

        jsonResponse([
            'message' => 'Project bijgewerkt',
            'project' => $project,
        ]);
    } catch (Throwable $exception) {
        error_log(
            'RGB Visuals project bijwerken mislukt: '
            . $exception->getMessage()
        );

        jsonResponse([
            'error' => 'Project kon niet worden bijgewerkt',
        ], 500);
    }
}

/*
|--------------------------------------------------------------------------
| Onbekende endpoint
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| CMS: projectgalerij beheren
|--------------------------------------------------------------------------
*/

if (
    preg_match(
        '~^/api/admin/projects/([1-9][0-9]*)/images$~',
        $path,
        $matches
    )
    && in_array($method, ['GET', 'POST'], true)
) {
    require __DIR__ . '/../config/session.php';

    enforceAdminOrigin();

    $projectId = (int) $matches[1];
    $newFilePath = null;

    try {
        $pdo = database();
        enforceAdminAccess($pdo);

        $projectStatement = $pdo->prepare(
            'SELECT id
             FROM projects
             WHERE id = :id
             LIMIT 1'
        );
        $projectStatement->execute(['id' => $projectId]);

        if (!$projectStatement->fetch()) {
            jsonResponse([
                'error' => 'Project niet gevonden',
            ], 404);
        }

        if ($method === 'GET') {
            $statement = $pdo->prepare(
                'SELECT id, project_id, filename, alt_text, sort_order, created_at
                 FROM project_images
                 WHERE project_id = :project_id
                 ORDER BY sort_order ASC, id ASC'
            );
            $statement->execute(['project_id' => $projectId]);

            $images = array_map(
                static function (array $image) use ($projectId): array {
                    $image['id'] = (int) $image['id'];
                    $image['project_id'] = (int) $image['project_id'];
                    $image['sort_order'] = (int) $image['sort_order'];
                    $image['image_url'] = sprintf(
                        '/api/admin/projects/%d/images/%d/file',
                        $projectId,
                        $image['id']
                    );

                    return $image;
                },
                $statement->fetchAll()
            );

            jsonResponse(['images' => $images]);
        }

        if (($_SERVER['HTTP_X_RGB_UPLOAD'] ?? '') !== '1') {
            jsonResponse([
                'error' => 'Ongeldige uploadaanvraag',
            ], 403);
        }

        if (
            !isset($_FILES['image'])
            || !is_array($_FILES['image'])
            || is_array($_FILES['image']['error'] ?? null)
        ) {
            jsonResponse([
                'error' => 'Selecteer één afbeelding',
            ], 400);
        }

        $file = $_FILES['image'];
        $temporaryPath = $file['tmp_name'] ?? null;
        $fileSize = $file['size'] ?? null;

        if (
            ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
            || !is_string($temporaryPath)
            || !is_int($fileSize)
        ) {
            jsonResponse([
                'error' => 'Upload mislukt. Controleer of de afbeelding kleiner is dan 2 MB.',
            ], 400);
        }

        $fileDetails = imageFileDetails($temporaryPath, $fileSize);
        $storageDirectory = __DIR__ . '/../storage/gallery';

        if (
            !is_dir($storageDirectory)
            || !is_writable($storageDirectory)
        ) {
            throw new RuntimeException('Galerijopslag is niet beschikbaar');
        }

        $fileName = bin2hex(random_bytes(20)) . '.' . $fileDetails['extension'];
        $newFilePath = $storageDirectory . '/' . $fileName;

        if (!move_uploaded_file($temporaryPath, $newFilePath)) {
            throw new RuntimeException('Afbeelding kon niet worden opgeslagen');
        }

        $orderStatement = $pdo->prepare(
            'SELECT COALESCE(MAX(sort_order), -1) + 1
             FROM project_images
             WHERE project_id = :project_id'
        );
        $orderStatement->execute(['project_id' => $projectId]);
        $sortOrder = (int) $orderStatement->fetchColumn();

        $insertStatement = $pdo->prepare(
            'INSERT INTO project_images
                (project_id, filename, alt_text, sort_order)
             VALUES
                (:project_id, :filename, NULL, :sort_order)'
        );
        $insertStatement->execute([
            'project_id' => $projectId,
            'filename' => $fileName,
            'sort_order' => $sortOrder,
        ]);

        jsonResponse([
            'message' => 'Galerijafbeelding opgeslagen',
            'image' => [
                'id' => (int) $pdo->lastInsertId(),
                'project_id' => $projectId,
                'filename' => $fileName,
                'alt_text' => null,
                'sort_order' => $sortOrder,
                'image_url' => sprintf(
                    '/api/admin/projects/%d/images/%d/file',
                    $projectId,
                    (int) $pdo->lastInsertId()
                ),
            ],
        ], 201);
    } catch (Throwable $exception) {
        if ($newFilePath !== null && is_file($newFilePath)) {
            @unlink($newFilePath);
        }

        error_log(
            'RGB Visuals galerij uploaden mislukt: '
            . $exception->getMessage()
        );

        jsonResponse([
            'error' => 'Afbeelding kon niet worden opgeslagen',
        ], 500);
    }
}

if (
    preg_match(
        '~^/api/admin/projects/([1-9][0-9]*)/images/([1-9][0-9]*)$~',
        $path,
        $matches
    )
    && in_array($method, ['PATCH', 'DELETE'], true)
) {
    require __DIR__ . '/../config/session.php';
    enforceAdminOrigin();

    $projectId = (int) $matches[1];
    $imageId = (int) $matches[2];

    try {
        $pdo = database();
        enforceAdminAccess($pdo);

        $imageStatement = $pdo->prepare(
            'SELECT id, filename
             FROM project_images
             WHERE id = :image_id
               AND project_id = :project_id
             LIMIT 1'
        );
        $imageStatement->execute([
            'image_id' => $imageId,
            'project_id' => $projectId,
        ]);
        $image = $imageStatement->fetch();

        if (!$image) {
            jsonResponse([
                'error' => 'Galerijafbeelding niet gevonden',
            ], 404);
        }

        if ($method === 'PATCH') {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

            if (!preg_match('~^application/json(?:\s*;|$)~i', $contentType)) {
                jsonResponse([
                    'error' => 'Gebruik application/json',
                ], 415);
            }

            $input = json_decode(file_get_contents('php://input'), true);

            if (!is_array($input) || array_is_list($input)) {
                jsonResponse([
                    'error' => 'Ongeldige galerijgegevens',
                ], 422);
            }

            $updates = [];
            $parameters = [
                'image_id' => $imageId,
                'project_id' => $projectId,
            ];

            if (array_key_exists('alt_text', $input)) {
                if ($input['alt_text'] !== null && !is_string($input['alt_text'])) {
                    jsonResponse([
                        'error' => 'Ongeldige alt-tekst',
                    ], 422);
                }

                $altText = $input['alt_text'] === null
                    ? null
                    : trim($input['alt_text']);

                if ($altText !== null && strlen($altText) > 255) {
                    jsonResponse([
                        'error' => 'De alt-tekst is te lang',
                    ], 422);
                }

                $updates[] = 'alt_text = :alt_text';
                $parameters['alt_text'] = $altText === '' ? null : $altText;
            }

            if (array_key_exists('sort_order', $input)) {
                $sortOrder = $input['sort_order'];

                if (
                    (!is_int($sortOrder) && !is_string($sortOrder))
                    || (is_string($sortOrder) && !preg_match('/^\d+$/', $sortOrder))
                    || (int) $sortOrder > 1000000
                ) {
                    jsonResponse([
                        'error' => 'Ongeldige volgorde',
                    ], 422);
                }

                $updates[] = 'sort_order = :sort_order';
                $parameters['sort_order'] = (int) $sortOrder;
            }

            if ($updates === []) {
                jsonResponse([
                    'error' => 'Geen wijziging opgegeven',
                ], 422);
            }

            $statement = $pdo->prepare(
                'UPDATE project_images
                 SET ' . implode(', ', $updates) . '
                 WHERE id = :image_id
                   AND project_id = :project_id'
            );
            $statement->execute($parameters);

            jsonResponse([
                'message' => 'Galerijafbeelding bijgewerkt',
            ]);
        }

        $deleteStatement = $pdo->prepare(
            'DELETE FROM project_images
             WHERE id = :image_id
               AND project_id = :project_id'
        );
        $deleteStatement->execute([
            'image_id' => $imageId,
            'project_id' => $projectId,
        ]);

        $fileName = $image['filename'];

        if (
            is_string($fileName)
            && preg_match('/\A[a-f0-9]{40}\.(jpg|png|webp)\z/', $fileName)
        ) {
            $filePath = __DIR__ . '/../storage/gallery/' . $fileName;

            if (is_file($filePath) && !@unlink($filePath)) {
                error_log('RGB Visuals: galerijbestand kon niet worden verwijderd');
            }
        }

        jsonResponse([
            'message' => 'Galerijafbeelding verwijderd',
        ]);
    } catch (Throwable $exception) {
        error_log(
            'RGB Visuals galerijwijziging mislukt: '
            . $exception->getMessage()
        );

        jsonResponse([
            'error' => 'Galerijafbeelding kon niet worden gewijzigd',
        ], 500);
    }
}

if (
    preg_match(
        '~^/api/admin/projects/([1-9][0-9]*)/images/([1-9][0-9]*)/file$~',
        $path,
        $matches
    )
    && $method === 'GET'
) {
    require __DIR__ . '/../config/session.php';

    try {
        $pdo = database();
        enforceAdminAccess($pdo);

        $statement = $pdo->prepare(
            'SELECT filename
             FROM project_images
             WHERE project_id = :project_id
               AND id = :image_id
             LIMIT 1'
        );
        $statement->execute([
            'project_id' => (int) $matches[1],
            'image_id' => (int) $matches[2],
        ]);
        $image = $statement->fetch();

        if (!$image || !is_string($image['filename'])) {
            jsonResponse([
                'error' => 'Afbeelding niet gevonden',
            ], 404);
        }

        if (
            !preg_match(
                '/\A[a-f0-9]{40}\.(jpg|png|webp)\z/',
                $image['filename'],
                $fileMatches
            )
        ) {
            jsonResponse([
                'error' => 'Afbeelding niet gevonden',
            ], 404);
        }

        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
        ];
        $filePath = __DIR__ . '/../storage/gallery/' . $image['filename'];

        if (!is_file($filePath) || !is_readable($filePath)) {
            jsonResponse([
                'error' => 'Afbeelding niet gevonden',
            ], 404);
        }

        $fileInfo = new finfo(FILEINFO_MIME_TYPE);
        $actualType = $fileInfo->file($filePath);

        if ($actualType !== $mimeTypes[$fileMatches[1]]) {
            jsonResponse([
                'error' => 'Afbeelding niet gevonden',
            ], 404);
        }

        header('Content-Type: ' . $actualType);
        header('Cache-Control: private, no-store');

        $fileSize = filesize($filePath);

        if ($fileSize !== false) {
            header('Content-Length: ' . $fileSize);
        }

        readfile($filePath);
        exit;
    } catch (Throwable $exception) {
        error_log(
            'RGB Visuals galerijafbeelding ophalen mislukt: '
            . $exception->getMessage()
        );

        jsonResponse([
            'error' => 'Afbeelding kon niet worden opgehaald',
        ], 500);
    }
}


/*
|--------------------------------------------------------------------------
| CMS: projectlinks beheren
|--------------------------------------------------------------------------
*/

if (
    preg_match(
        '~^/api/admin/projects/([1-9][0-9]*)/links$~',
        $path,
        $matches
    )
    && in_array($method, ['GET', 'POST'], true)
) {
    require __DIR__ . '/../config/session.php';
    enforceAdminOrigin();

    $projectId = (int) $matches[1];

    try {
        $pdo = database();
        enforceAdminAccess($pdo);
        requireProject($pdo, $projectId);

        if ($method === 'GET') {
            $statement = $pdo->prepare(
                'SELECT id, project_id, title, url, type, sort_order, created_at
                 FROM project_links
                 WHERE project_id = :project_id
                 ORDER BY sort_order ASC, id ASC'
            );
            $statement->execute([
                'project_id' => $projectId,
            ]);

            $links = array_map(
                static function (array $link): array {
                    $link['id'] = (int) $link['id'];
                    $link['project_id'] = (int) $link['project_id'];
                    $link['sort_order'] = (int) $link['sort_order'];

                    return $link;
                },
                $statement->fetchAll()
            );

            jsonResponse([
                'links' => $links,
            ]);
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (!preg_match('~^application/json(?:\s*;|$)~i', $contentType)) {
            jsonResponse([
                'error' => 'Gebruik application/json',
            ], 415);
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if (!is_array($input) || array_is_list($input)) {
            jsonResponse([
                'error' => 'Ongeldige linkgegevens',
            ], 422);
        }

        $fields = projectLinkInput($input);

        if (!array_key_exists('sort_order', $fields)) {
            $orderStatement = $pdo->prepare(
                'SELECT COALESCE(MAX(sort_order), -1) + 1
                 FROM project_links
                 WHERE project_id = :project_id'
            );
            $orderStatement->execute([
                'project_id' => $projectId,
            ]);
            $fields['sort_order'] = (int) $orderStatement->fetchColumn();
        }

        $statement = $pdo->prepare(
            'INSERT INTO project_links
                (project_id, title, url, type, sort_order)
             VALUES
                (:project_id, :title, :url, :type, :sort_order)'
        );
        $statement->execute([
            'project_id' => $projectId,
            ...$fields,
        ]);

        jsonResponse([
            'message' => 'Projectlink opgeslagen',
            'link' => [
                'id' => (int) $pdo->lastInsertId(),
                'project_id' => $projectId,
                ...$fields,
            ],
        ], 201);
    } catch (Throwable $exception) {
        error_log(
            'RGB Visuals projectlink opslaan mislukt: '
            . $exception->getMessage()
        );

        jsonResponse([
            'error' => 'Projectlink kon niet worden opgeslagen',
        ], 500);
    }
}

if (
    preg_match(
        '~^/api/admin/projects/([1-9][0-9]*)/links/([1-9][0-9]*)$~',
        $path,
        $matches
    )
    && in_array($method, ['PATCH', 'DELETE'], true)
) {
    require __DIR__ . '/../config/session.php';
    enforceAdminOrigin();

    $projectId = (int) $matches[1];
    $linkId = (int) $matches[2];

    try {
        $pdo = database();
        enforceAdminAccess($pdo);
        requireProject($pdo, $projectId);

        $linkStatement = $pdo->prepare(
            'SELECT id
             FROM project_links
             WHERE id = :link_id
               AND project_id = :project_id
             LIMIT 1'
        );
        $linkStatement->execute([
            'link_id' => $linkId,
            'project_id' => $projectId,
        ]);

        if (!$linkStatement->fetch()) {
            jsonResponse([
                'error' => 'Projectlink niet gevonden',
            ], 404);
        }

        if ($method === 'PATCH') {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

            if (!preg_match('~^application/json(?:\s*;|$)~i', $contentType)) {
                jsonResponse([
                    'error' => 'Gebruik application/json',
                ], 415);
            }

            $input = json_decode(file_get_contents('php://input'), true);

            if (!is_array($input) || array_is_list($input)) {
                jsonResponse([
                    'error' => 'Ongeldige linkgegevens',
                ], 422);
            }

            $fields = projectLinkInput($input, true);

            if ($fields === []) {
                jsonResponse([
                    'error' => 'Geen wijziging opgegeven',
                ], 422);
            }

            $updates = [];
            $parameters = [
                'link_id' => $linkId,
                'project_id' => $projectId,
            ];

            foreach (['title', 'url', 'type', 'sort_order'] as $field) {
                if (array_key_exists($field, $fields)) {
                    $updates[] = $field . ' = :' . $field;
                    $parameters[$field] = $fields[$field];
                }
            }

            $statement = $pdo->prepare(
                'UPDATE project_links
                 SET ' . implode(', ', $updates) . '
                 WHERE id = :link_id
                   AND project_id = :project_id'
            );
            $statement->execute($parameters);

            jsonResponse([
                'message' => 'Projectlink bijgewerkt',
            ]);
        }

        $statement = $pdo->prepare(
            'DELETE FROM project_links
             WHERE id = :link_id
               AND project_id = :project_id'
        );
        $statement->execute([
            'link_id' => $linkId,
            'project_id' => $projectId,
        ]);

        jsonResponse([
            'message' => 'Projectlink verwijderd',
        ]);
    } catch (Throwable $exception) {
        error_log(
            'RGB Visuals projectlink wijzigen mislukt: '
            . $exception->getMessage()
        );

        jsonResponse([
            'error' => 'Projectlink kon niet worden gewijzigd',
        ], 500);
    }
}

/*
|--------------------------------------------------------------------------
| CMS: projectdocumenten beheren
|--------------------------------------------------------------------------
*/

if (
    preg_match(
        '~^/api/admin/projects/([1-9][0-9]*)/documents$~',
        $path,
        $matches
    )
    && in_array($method, ['GET', 'POST'], true)
) {
    require __DIR__ . '/../config/session.php';
    enforceAdminOrigin();

    $projectId = (int) $matches[1];
    $newFilePath = null;

    try {
        $pdo = database();
        enforceAdminAccess($pdo);
        requireProject($pdo, $projectId);

        if ($method === 'GET') {
            $statement = $pdo->prepare(
                'SELECT id, project_id, title, original_name, sort_order, created_at
                 FROM project_documents
                 WHERE project_id = :project_id
                 ORDER BY sort_order ASC, id ASC'
            );
            $statement->execute([
                'project_id' => $projectId,
            ]);

            $documents = array_map(
                static function (array $document): array {
                    $document['id'] = (int) $document['id'];
                    $document['project_id'] = (int) $document['project_id'];
                    $document['sort_order'] = (int) $document['sort_order'];

                    return $document;
                },
                $statement->fetchAll()
            );

            jsonResponse([
                'documents' => $documents,
            ]);
        }

        if (($_SERVER['HTTP_X_RGB_UPLOAD'] ?? '') !== '1') {
            jsonResponse([
                'error' => 'Ongeldige uploadaanvraag',
            ], 403);
        }

        if (
            !isset($_FILES['document'])
            || !is_array($_FILES['document'])
            || is_array($_FILES['document']['error'] ?? null)
        ) {
            jsonResponse([
                'error' => 'Selecteer één PDF-document',
            ], 400);
        }

        $documentFields = projectDocumentInput([
            'title' => $_POST['title'] ?? null,
        ]);

        $file = $_FILES['document'];
        $temporaryPath = $file['tmp_name'] ?? null;
        $fileSize = $file['size'] ?? null;

        if (
            ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
            || !is_string($temporaryPath)
            || !is_int($fileSize)
        ) {
            jsonResponse([
                'error' => 'Upload mislukt. Controleer of het PDF-bestand kleiner is dan 10 MB.',
            ], 400);
        }

        pdfFileDetails($temporaryPath, $fileSize);

        $storageDirectory = __DIR__ . '/../storage/documents';

        if (
            !is_dir($storageDirectory)
            || !is_writable($storageDirectory)
        ) {
            throw new RuntimeException('Documentopslag is niet beschikbaar');
        }

        $fileName = bin2hex(random_bytes(20)) . '.pdf';
        $newFilePath = $storageDirectory . '/' . $fileName;

        if (!move_uploaded_file($temporaryPath, $newFilePath)) {
            throw new RuntimeException('Document kon niet worden opgeslagen');
        }

        $orderStatement = $pdo->prepare(
            'SELECT COALESCE(MAX(sort_order), -1) + 1
             FROM project_documents
             WHERE project_id = :project_id'
        );
        $orderStatement->execute([
            'project_id' => $projectId,
        ]);
        $sortOrder = (int) $orderStatement->fetchColumn();

        $insertStatement = $pdo->prepare(
            'INSERT INTO project_documents
                (project_id, title, filename, original_name, sort_order)
             VALUES
                (:project_id, :title, :filename, :original_name, :sort_order)'
        );
        $insertStatement->execute([
            'project_id' => $projectId,
            'title' => $documentFields['title'],
            'filename' => $fileName,
            'original_name' => safeOriginalPdfName($file['name'] ?? null),
            'sort_order' => $sortOrder,
        ]);

        jsonResponse([
            'message' => 'PDF-document opgeslagen',
            'document' => [
                'id' => (int) $pdo->lastInsertId(),
                'project_id' => $projectId,
                'title' => $documentFields['title'],
                'original_name' => safeOriginalPdfName($file['name'] ?? null),
                'sort_order' => $sortOrder,
            ],
        ], 201);
    } catch (Throwable $exception) {
        if ($newFilePath !== null && is_file($newFilePath)) {
            @unlink($newFilePath);
        }

        error_log(
            'RGB Visuals PDF uploaden mislukt: '
            . $exception->getMessage()
        );

        jsonResponse([
            'error' => 'PDF-document kon niet worden opgeslagen',
        ], 500);
    }
}

if (
    preg_match(
        '~^/api/admin/projects/([1-9][0-9]*)/documents/([1-9][0-9]*)$~',
        $path,
        $matches
    )
    && in_array($method, ['PATCH', 'DELETE'], true)
) {
    require __DIR__ . '/../config/session.php';
    enforceAdminOrigin();

    $projectId = (int) $matches[1];
    $documentId = (int) $matches[2];

    try {
        $pdo = database();
        enforceAdminAccess($pdo);
        requireProject($pdo, $projectId);

        $documentStatement = $pdo->prepare(
            'SELECT id, filename
             FROM project_documents
             WHERE id = :document_id
               AND project_id = :project_id
             LIMIT 1'
        );
        $documentStatement->execute([
            'document_id' => $documentId,
            'project_id' => $projectId,
        ]);
        $document = $documentStatement->fetch();

        if (!$document) {
            jsonResponse([
                'error' => 'Document niet gevonden',
            ], 404);
        }

        if ($method === 'PATCH') {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

            if (!preg_match('~^application/json(?:\s*;|$)~i', $contentType)) {
                jsonResponse([
                    'error' => 'Gebruik application/json',
                ], 415);
            }

            $input = json_decode(file_get_contents('php://input'), true);

            if (!is_array($input) || array_is_list($input)) {
                jsonResponse([
                    'error' => 'Ongeldige documentgegevens',
                ], 422);
            }

            $fields = projectDocumentInput($input, true);

            if ($fields === []) {
                jsonResponse([
                    'error' => 'Geen wijziging opgegeven',
                ], 422);
            }

            $updates = [];
            $parameters = [
                'document_id' => $documentId,
                'project_id' => $projectId,
            ];

            foreach (['title', 'sort_order'] as $field) {
                if (array_key_exists($field, $fields)) {
                    $updates[] = $field . ' = :' . $field;
                    $parameters[$field] = $fields[$field];
                }
            }

            $statement = $pdo->prepare(
                'UPDATE project_documents
                 SET ' . implode(', ', $updates) . '
                 WHERE id = :document_id
                   AND project_id = :project_id'
            );
            $statement->execute($parameters);

            jsonResponse([
                'message' => 'Document bijgewerkt',
            ]);
        }

        $deleteStatement = $pdo->prepare(
            'DELETE FROM project_documents
             WHERE id = :document_id
               AND project_id = :project_id'
        );
        $deleteStatement->execute([
            'document_id' => $documentId,
            'project_id' => $projectId,
        ]);

        $fileName = $document['filename'];

        if (
            is_string($fileName)
            && preg_match('/\A[a-f0-9]{40}\.pdf\z/', $fileName)
        ) {
            $filePath = __DIR__ . '/../storage/documents/' . $fileName;

            if (is_file($filePath) && !@unlink($filePath)) {
                error_log('RGB Visuals: PDF-bestand kon niet worden verwijderd');
            }
        }

        jsonResponse([
            'message' => 'Document verwijderd',
        ]);
    } catch (Throwable $exception) {
        error_log(
            'RGB Visuals document wijzigen mislukt: '
            . $exception->getMessage()
        );

        jsonResponse([
            'error' => 'Document kon niet worden gewijzigd',
        ], 500);
    }
}

/*
|--------------------------------------------------------------------------
| CMS: omslagafbeelding uploaden
|--------------------------------------------------------------------------
*/

if (
    preg_match(
        '~^/api/admin/projects/([1-9][0-9]*)/cover$~',
        $path,
        $matches
    )
    && $method === 'POST'
) {
    require __DIR__ . '/../config/session.php';

    if (!isset($_SESSION['admin_id'])) {
        jsonResponse([
            'error' => 'Niet ingelogd',
        ], 401);
    }

    // Beperk aanvragen tot onze lokale ontwikkelomgeving.
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if (
        $origin !== ''
        && !in_array(
            $origin,
            [
                'http://localhost:5173',
                'http://127.0.0.1:5173',
            ],
            true
        )
    ) {
        jsonResponse([
            'error' => 'Ongeldige aanvraag',
        ], 403);
    }

    // Een gewone HTML-formulieraanvraag kan deze header niet meesturen.
    if (($_SERVER['HTTP_X_RGB_UPLOAD'] ?? '') !== '1') {
        jsonResponse([
            'error' => 'Ongeldige uploadaanvraag',
        ], 403);
    }

    $newFilePath = null;

    try {
        $pdo = database();

        // Controleer of het admin-account nog actief is.
        $adminStatement = $pdo->prepare(
            'SELECT id
             FROM admin_users
             WHERE id = :id AND is_active = 1
             LIMIT 1'
        );

        $adminStatement->execute([
            'id' => (int) $_SESSION['admin_id'],
        ]);

        if (!$adminStatement->fetch()) {
            jsonResponse([
                'error' => 'Geen toegang',
            ], 403);
        }

        $projectId = (int) $matches[1];

        // Controleer of het project bestaat.
        $projectStatement = $pdo->prepare(
            'SELECT id
             FROM projects
             WHERE id = :id
             LIMIT 1'
        );

        $projectStatement->execute([
            'id' => $projectId,
        ]);

        if (!$projectStatement->fetch()) {
            jsonResponse([
                'error' => 'Project niet gevonden',
            ], 404);
        }

        // We verwachten één bestand met de veldnaam "cover".
        if (
            !isset($_FILES['cover'])
            || !is_array($_FILES['cover'])
            || is_array($_FILES['cover']['error'] ?? null)
        ) {
            jsonResponse([
                'error' => 'Selecteer één afbeelding',
            ], 400);
        }

        $file = $_FILES['cover'];

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            jsonResponse([
                'error' => 'Upload mislukt. Controleer of de afbeelding kleiner is dan 2 MB.',
            ], 400);
        }

        $temporaryPath = $file['tmp_name'] ?? null;
        $fileSize = $file['size'] ?? null;

        if (
            !is_string($temporaryPath)
            || !is_int($fileSize)
            || $fileSize <= 0
            || $fileSize > 2 * 1024 * 1024
            || !is_uploaded_file($temporaryPath)
        ) {
            jsonResponse([
                'error' => 'Ongeldig bestand of bestand groter dan 2 MB',
            ], 422);
        }

        // Controleer de daadwerkelijke inhoud, niet de bestandsnaam.
        $fileInfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $fileInfo->file($temporaryPath);

        $allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        if (
            !is_string($mimeType)
            || !isset($allowedTypes[$mimeType])
        ) {
            jsonResponse([
                'error' => 'Alleen JPG, PNG en WebP zijn toegestaan',
            ], 422);
        }

        // Controleer ook of het bestand een herkenbare afbeelding is.
        $imageInfo = @getimagesize($temporaryPath);

        if (
            $imageInfo === false
            || ($imageInfo['mime'] ?? '') !== $mimeType
        ) {
            jsonResponse([
                'error' => 'Het bestand is geen geldige afbeelding',
            ], 422);
        }

        $width = (int) $imageInfo[0];
        $height = (int) $imageInfo[1];

        if (
            $width <= 0
            || $height <= 0
            || $width > 6000
            || $height > 6000
            || $width * $height > 20000000
        ) {
            jsonResponse([
                'error' => 'De afmetingen van de afbeelding zijn te groot',
            ], 422);
        }

        // De opslagmap staat buiten backend/public.
        $storageDirectory = __DIR__ . '/../storage/covers';

        if (
            !is_dir($storageDirectory)
            || !is_writable($storageDirectory)
        ) {
            throw new RuntimeException(
                'De opslagmap ontbreekt of is niet beschrijfbaar'
            );
        }

        // Gebruik nooit de oorspronkelijke bestandsnaam van de gebruiker.
        $extension = $allowedTypes[$mimeType];

        $fileName = bin2hex(random_bytes(20)) . '.' . $extension;

        $newFilePath = $storageDirectory . '/' . $fileName;

        if (!move_uploaded_file($temporaryPath, $newFilePath)) {
            throw new RuntimeException(
                'Afbeelding kon niet worden opgeslagen'
            );
        }

        // Koppel de opgeslagen bestandsnaam aan het project.
        $updateStatement = $pdo->prepare(
            'UPDATE projects
             SET cover_image = :cover_image
             WHERE id = :id'
        );

        $updateStatement->execute([
            'cover_image' => $fileName,
            'id' => $projectId,
        ]);

        jsonResponse([
            'message' => 'Omslagafbeelding opgeslagen',
            'project_id' => $projectId,
            'cover_image' => $fileName,
        ]);
    } catch (Throwable $exception) {
        // Verwijder het nieuwe bestand als het opslaan mislukt.
        if (
            $newFilePath !== null
            && is_file($newFilePath)
        ) {
            @unlink($newFilePath);
        }

        error_log(
            'RGB Visuals afbeelding uploaden mislukt: '
            . $exception->getMessage()
        );

        jsonResponse([
            'error' => 'Afbeelding kon niet worden opgeslagen',
        ], 500);
    }
}

/*
|--------------------------------------------------------------------------
| Openbare API: omslagafbeelding tonen
|--------------------------------------------------------------------------
*/

if (
    preg_match(
        '~^/api/projects/([1-9][0-9]*)/cover$~',
        $path,
        $matches
    )
    && $method === 'GET'
) {
    try {
        $projectId = (int) $matches[1];

        // Alleen afbeeldingen van gepubliceerde projecten tonen.
        $statement = database()->prepare(
            'SELECT cover_image
             FROM projects
             WHERE id = :id
               AND status = :status
             LIMIT 1'
        );

        $statement->execute([
            'id' => $projectId,
            'status' => 'published',
        ]);

        $project = $statement->fetch();

        if (
            !$project
            || !is_string($project['cover_image'])
            || $project['cover_image'] === ''
        ) {
            jsonResponse([
                'error' => 'Afbeelding niet gevonden',
            ], 404);
        }

        $fileName = $project['cover_image'];

        // Accepteer uitsluitend de bestandsnamen die onze uploadroute maakt.
        if (
            !preg_match(
                '/\A[a-f0-9]{40}\.(jpg|png|webp)\z/',
                $fileName,
                $fileMatches
            )
        ) {
            jsonResponse([
                'error' => 'Afbeelding niet gevonden',
            ], 404);
        }

        $filePath = __DIR__ . '/../storage/covers/' . $fileName;

        if (!is_file($filePath) || !is_readable($filePath)) {
            jsonResponse([
                'error' => 'Afbeelding niet gevonden',
            ], 404);
        }

        $allowedTypes = [
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
        ];

        $expectedType = $allowedTypes[$fileMatches[1]];

        // Controleer voor het versturen opnieuw het echte bestandstype.
        $fileInfo = new finfo(FILEINFO_MIME_TYPE);
        $actualType = $fileInfo->file($filePath);

        if ($actualType !== $expectedType) {
            error_log('RGB Visuals: ongeldig opgeslagen afbeeldingsbestand');

            jsonResponse([
                'error' => 'Afbeelding niet gevonden',
            ], 404);
        }

        // De standaard JSON-header wordt vervangen door het afbeeldingsformaat.
        header('Content-Type: ' . $expectedType);

        $fileSize = filesize($filePath);

        if ($fileSize !== false) {
            header('Content-Length: ' . $fileSize);
        }

        readfile($filePath);
        exit;
    } catch (Throwable $exception) {
        error_log(
            'RGB Visuals afbeelding ophalen mislukt: '
            . $exception->getMessage()
        );

        jsonResponse([
            'error' => 'Afbeelding kon niet worden opgehaald',
        ], 500);
    }
}


/*
|--------------------------------------------------------------------------
| Setup: eerste CMS-beheerder aanmaken
|--------------------------------------------------------------------------
|
| POST /api/setup/admin
|
| Deze route:
| - staat standaard uit;
| - vereist een geheim setup-token;
| - werkt alleen als admin_users nog leeg is;
| - slaat het wachtwoord gehasht op.
|
*/

if ($path === '/api/setup/admin' && $method === 'POST') {
    // Docker: gebruik omgevingsvariabelen.
    // Laragon: gebruik eventueel een lokaal configuratiebestand.
    $localSetupFile = __DIR__ . '/../config/setup.local.php';

    $localSetup = is_file($localSetupFile)
        ? require $localSetupFile
        : [];

    $setupEnabled =
        getenv('RGB_SETUP_ENABLED') === '1'
        || ($localSetup['enabled'] ?? false) === true;

    if (!$setupEnabled) {
        jsonResponse([
            'error' => 'Setup is uitgeschakeld',
        ], 404);
    }

    $setupToken = getenv('RGB_SETUP_TOKEN');

    if (!is_string($setupToken) || $setupToken === '') {
        $setupToken = $localSetup['token'] ?? '';
    }

    $providedToken = $_SERVER['HTTP_X_SETUP_TOKEN'] ?? '';

    if (
        !is_string($setupToken)
        || strlen($setupToken) < 32
        || !is_string($providedToken)
        || !hash_equals($setupToken, $providedToken)
    ) {
        jsonResponse([
            'error' => 'Geen toegang',
        ], 403);
    }

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (!preg_match('~^application/json(?:\s*;|$)~i', $contentType)) {
        jsonResponse([
            'error' => 'Gebruik application/json',
        ], 415);
    }

    $rawBody = file_get_contents('php://input');

    if (
        $rawBody === false
        || strlen($rawBody) > 8192
    ) {
        jsonResponse([
            'error' => 'Ongeldige aanvraaggrootte',
        ], 413);
    }

    $input = json_decode($rawBody, true);

    if (
        !is_array($input)
        || array_is_list($input)
    ) {
        jsonResponse([
            'error' => 'Ongeldige JSON',
        ], 400);
    }

    $name = $input['name'] ?? null;
    $email = $input['email'] ?? null;
    $password = $input['password'] ?? null;

    if (
        !is_string($name)
        || !is_string($email)
        || !is_string($password)
    ) {
        jsonResponse([
            'error' => 'Naam, e-mailadres en wachtwoord zijn verplicht',
        ], 422);
    }

    $name = trim($name);
    $email = trim($email);

    if (
        $name === ''
        || strlen($name) > 150
        || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || strlen($email) > 190
        || strlen($password) < 12
        || strlen($password) > 128
    ) {
        jsonResponse([
            'error' => 'Controleer de naam, het e-mailadres en het wachtwoord (minimaal 12 tekens)',
        ], 422);
    }

    $pdo = null;
    $lockHeld = false;

    $response = [
        'error' => 'Beheerder kon niet worden aangemaakt',
    ];

    $responseStatus = 500;

    try {
        $pdo = database();

        // Voorkom dat twee gelijktijdige setup-aanvragen
        // allebei de eerste beheerder aanmaken.
        $lockStatement = $pdo->query(
            "SELECT GET_LOCK('rgb_visuals_first_admin_setup', 5)"
        );

        $lockHeld = (int) $lockStatement->fetchColumn() === 1;

        if (!$lockHeld) {
            $response = [
                'error' => 'Setup is tijdelijk bezet. Probeer opnieuw.',
            ];

            $responseStatus = 503;
        } else {
            // Ook inactieve accounts tellen mee.
            // Setup mag uitsluitend op een volledig lege tabel.
            $existingStatement = $pdo->query(
                'SELECT COUNT(*) FROM admin_users'
            );

            $adminCount = (int) $existingStatement->fetchColumn();

            if ($adminCount > 0) {
                $response = [
                    'error' => 'Setup is niet beschikbaar: er bestaat al een beheerder',
                ];

                $responseStatus = 409;
            } else {
                $passwordHash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $insertStatement = $pdo->prepare(
                    'INSERT INTO admin_users
                        (
                            name,
                            email,
                            password_hash,
                            is_active,
                            created_at
                        )
                     VALUES
                        (
                            :name,
                            :email,
                            :password_hash,
                            1,
                            NOW()
                        )'
                );

                $insertStatement->execute([
                    'name' => $name,
                    'email' => $email,
                    'password_hash' => $passwordHash,
                ]);

                $adminId = (int) $pdo->lastInsertId();

                $response = [
                    'message' => 'Eerste beheerder aangemaakt. Schakel setup nu uit.',
                    'user' => [
                        'id' => $adminId,
                        'name' => $name,
                        'email' => $email,
                    ],
                ];

                $responseStatus = 201;
            }
        }
    } catch (Throwable $exception) {
        error_log(
            'RGB Visuals setupfout: ' . $exception->getMessage()
        );

        $response = [
            'error' => 'Beheerder kon niet worden aangemaakt',
        ];

        $responseStatus = 500;
    } finally {
        if ($lockHeld && $pdo instanceof PDO) {
            try {
                $pdo->query(
                    "SELECT RELEASE_LOCK('rgb_visuals_first_admin_setup')"
                );
            } catch (Throwable $exception) {
                error_log(
                    'RGB Visuals setup-lock vrijgeven mislukt: '
                    . $exception->getMessage()
                );
            }
        }
    }

    jsonResponse($response, $responseStatus);
}

jsonResponse([
    'error' => 'Endpoint niet gevonden',
], 404);