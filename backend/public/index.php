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
    $config = require __DIR__ . '/../config/database.local.php';

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

jsonResponse([
    'error' => 'Endpoint niet gevonden',
], 404);