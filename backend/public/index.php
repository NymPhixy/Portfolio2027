<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

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

if ($path === '/api/health' && $method === 'GET') {
    jsonResponse([
        'status' => 'ok',
        'message' => 'RGB Visuals API werkt',
    ]);
}

if ($path === '/api/projects' && $method === 'GET') {
    try {
        $statement = database()->query(
            "SELECT id, title, slug, summary, description, cover_image, created_at
             FROM projects
             WHERE status = 'published'
             ORDER BY created_at DESC, id DESC"
        );

        jsonResponse([
            'projects' => $statement->fetchAll(),
        ]);
    } catch (Throwable $exception) {
        error_log('RGB Visuals projectenfout: ' . $exception->getMessage());

        jsonResponse([
            'error' => 'Projecten konden niet worden opgehaald',
        ], 500);
    }
}

if (
    $path === '/api/auth/login'
    || $path === '/api/auth/me'
    || $path === '/api/auth/logout'
) {
    require __DIR__ . '/../config/session.php';
}

if ($path === '/api/auth/login' && $method === 'POST') {
    // Alleen JSON-verzoeken accepteren.
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (!preg_match('~^application/json(?:\s*;|$)~i', $contentType)) {
        jsonResponse(['error' => 'Gebruik application/json'], 415);
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (
        !is_array($input)
        || !is_string($input['email'] ?? null)
        || !is_string($input['password'] ?? null)
    ) {
        jsonResponse(['error' => 'Ongeldige invoer'], 400);
    }

    $email = trim($input['email']);
    $password = $input['password'];

    if (
        !filter_var($email, FILTER_VALIDATE_EMAIL)
        || $password === ''
        || strlen($email) > 190
        || strlen($password) > 4096
    ) {
        jsonResponse(['error' => 'Ongeldige invoer'], 400);
    }

    try {
        $statement = database()->prepare(
            'SELECT id, name, email, password_hash
             FROM admin_users
             WHERE email = :email AND is_active = 1
             LIMIT 1'
        );

        $statement->execute(['email' => $email]);
        $admin = $statement->fetch();

        if (
            !$admin
            || !password_verify($password, $admin['password_hash'])
        ) {
            jsonResponse(['error' => 'E-mailadres of wachtwoord onjuist'], 401);
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
        error_log('RGB Visuals loginfout: ' . $exception->getMessage());

        jsonResponse(['error' => 'Inloggen is tijdelijk niet mogelijk'], 500);
    }
}

if ($path === '/api/auth/me' && $method === 'GET') {
    if (!isset($_SESSION['admin_id'])) {
        jsonResponse(['authenticated' => false], 401);
    }

    try {
        $statement = database()->prepare(
            'SELECT id, name, email
             FROM admin_users
             WHERE id = :id AND is_active = 1
             LIMIT 1'
        );

        $statement->execute([
            'id' => (int) $_SESSION['admin_id'],
        ]);

        $admin = $statement->fetch();

        if (!$admin) {
            $_SESSION = [];
            session_regenerate_id(true);

            jsonResponse(['authenticated' => false], 401);
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
        error_log('RGB Visuals sessiefout: ' . $exception->getMessage());

        jsonResponse(['error' => 'Sessie kon niet worden gecontroleerd'], 500);
    }
}

if ($path === '/api/auth/logout' && $method === 'POST') {
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if ($origin !== '') {
    $allowedOrigins = [
        'http://localhost:5173',
        'http://127.0.0.1:5173',
    ];

    if (!in_array($origin, $allowedOrigins, true)) {
        jsonResponse(['error' => 'Ongeldige aanvraag'], 403);
    }
}

    $_SESSION = [];

    session_regenerate_id(true);

    jsonResponse(['authenticated' => false]);
}


if ($path === '/api/admin/projects' && $method === 'POST') {
    require __DIR__ . '/../config/session.php';

    // Alleen ingelogde, actieve beheerders mogen projecten aanmaken.
    if (!isset($_SESSION['admin_id'])) {
        jsonResponse(['error' => 'Niet ingelogd'], 401);
    }

    try {
        $pdo = database();

        $adminStatement = $pdo->prepare(
            'SELECT id FROM admin_users
             WHERE id = :id AND is_active = 1
             LIMIT 1'
        );
        $adminStatement->execute([
            'id' => (int) $_SESSION['admin_id'],
        ]);

        if (!$adminStatement->fetch()) {
            jsonResponse(['error' => 'Geen toegang'], 403);
        }

        // Deze route accepteert uitsluitend JSON.
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (!preg_match('~^application/json(?:\s*;|$)~i', $contentType)) {
            jsonResponse(['error' => 'Gebruik application/json'], 415);
        }

        // Basisbescherming tegen aanvragen vanaf een andere website.
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if ($origin !== '' && !in_array($origin, [
            'http://localhost:5173',
            'http://127.0.0.1:5173',
        ], true)) {
            jsonResponse(['error' => 'Ongeldige aanvraag'], 403);
        }

        $rawBody = file_get_contents('php://input');

        if ($rawBody === false || strlen($rawBody) > 65536) {
            jsonResponse(['error' => 'Aanvraag is te groot'], 413);
        }

        $input = json_decode($rawBody, true);

        if (!is_array($input)) {
            jsonResponse(['error' => 'Ongeldige JSON'], 400);
        }

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
            jsonResponse(['error' => 'Ongeldige projectgegevens'], 400);
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
            jsonResponse(['error' => 'Controleer de ingevulde velden'], 422);
        }

        // Maak een eenvoudige slug; het project-ID maakt hem uniek.
        $slugBase = strtolower($title);
        $slugBase = preg_replace('/[^a-z0-9]+/', '-', $slugBase);
        $slugBase = trim($slugBase ?? '', '-');

        if ($slugBase === '') {
            $slugBase = 'project';
        }

        $slugBase = substr($slugBase, 0, 160);

        $statement = $pdo->prepare(
            'INSERT INTO projects
                (title, slug, summary, description, client, year, status)
             VALUES
                (:title, :slug, :summary, :description, :client, :year, :status)'
        );

        // Een tijdelijke unieke slug voor de eerste INSERT.
        $temporarySlug = 'nieuw-project-' . bin2hex(random_bytes(12));

        $statement->execute([
            'title' => $title,
            'slug' => $temporarySlug,
            'summary' => $description,
            'description' => $description,
            'client' => $client,
            'year' => (int) $year,
            'status' => $status,
        ]);

        $projectId = (int) $pdo->lastInsertId();
        $finalSlug = $slugBase . '-' . $projectId;

        $updateStatement = $pdo->prepare(
            'UPDATE projects SET slug = :slug WHERE id = :id'
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
        error_log('RGB Visuals project opslaan mislukt: ' . $exception->getMessage());

        jsonResponse(['error' => 'Project kon niet worden opgeslagen'], 500);
    }
}

if ($path === '/api/admin/projects' && $method === 'GET') {
    require __DIR__ . '/../config/session.php';

    if (!isset($_SESSION['admin_id'])) {
        jsonResponse(['error' => 'Niet ingelogd'], 401);
    }

    try {
        $pdo = database();

        $adminStatement = $pdo->prepare(
            'SELECT id FROM admin_users
             WHERE id = :id AND is_active = 1
             LIMIT 1'
        );

        $adminStatement->execute([
            'id' => (int) $_SESSION['admin_id'],
        ]);

        if (!$adminStatement->fetch()) {
            jsonResponse(['error' => 'Geen toegang'], 403);
        }

        $statement = $pdo->query(
            'SELECT id, title, slug, summary, description,
                    client, year, cover_image, status,
                    created_at, updated_at
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

if (
    preg_match('~^/api/admin/projects/([1-9][0-9]*)$~', $path, $matches)
    && $method === 'PUT'
) {
    require __DIR__ . '/../config/session.php';

    if (!isset($_SESSION['admin_id'])) {
        jsonResponse(['error' => 'Niet ingelogd'], 401);
    }

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if ($origin !== '' && !in_array($origin, [
        'http://localhost:5173',
        'http://127.0.0.1:5173',
    ], true)) {
        jsonResponse(['error' => 'Ongeldige aanvraag'], 403);
    }

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (!preg_match('~^application/json(?:\s*;|$)~i', $contentType)) {
        jsonResponse(['error' => 'Gebruik application/json'], 415);
    }

    $rawBody = file_get_contents('php://input');

    if ($rawBody === false || strlen($rawBody) > 65536) {
        jsonResponse(['error' => 'Aanvraag is te groot'], 413);
    }

    $input = json_decode($rawBody, true);

    if (!is_array($input)) {
        jsonResponse(['error' => 'Ongeldige JSON'], 400);
    }

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
        jsonResponse(['error' => 'Ongeldige projectgegevens'], 400);
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
        jsonResponse(['error' => 'Controleer de ingevulde velden'], 422);
    }

    try {
        $pdo = database();

        $adminStatement = $pdo->prepare(
            'SELECT id FROM admin_users
             WHERE id = :id AND is_active = 1
             LIMIT 1'
        );

        $adminStatement->execute([
            'id' => (int) $_SESSION['admin_id'],
        ]);

        if (!$adminStatement->fetch()) {
            jsonResponse(['error' => 'Geen toegang'], 403);
        }

        $projectId = (int) $matches[1];

        $statement = $pdo->prepare(
            'UPDATE projects
             SET title = :title,
                 summary = :summary,
                 description = :description,
                 client = :client,
                 year = :year,
                 status = :status
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
        ]);

        $checkStatement = $pdo->prepare(
            'SELECT id, title, slug, summary, description,
                    client, year, status
             FROM projects
             WHERE id = :id
             LIMIT 1'
        );

        $checkStatement->execute(['id' => $projectId]);
        $project = $checkStatement->fetch();

        if (!$project) {
            jsonResponse(['error' => 'Project niet gevonden'], 404);
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

        jsonResponse(['error' => 'Project kon niet worden bijgewerkt'], 500);
    }
}
jsonResponse(['error' => 'Endpoint niet gevonden'], 404);