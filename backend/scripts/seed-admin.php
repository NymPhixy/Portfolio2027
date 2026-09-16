
<?php
declare(strict_types=1);

/**
 * Maakt uitsluitend de eerste CMS-beheerder aan.
 * Bestaande accounts en wachtwoorden blijven ongewijzigd.
 */

function requiredEnvironment(string $name): string
{
    $value = getenv($name);

    if ($value === false || $value === '') {
        throw new RuntimeException(
            "Omgevingsvariabele ontbreekt: {$name}"
        );
    }

    return $value;
}

try {
    $name = requiredEnvironment('ADMIN_NAME');
    $email = requiredEnvironment('ADMIN_EMAIL');
    $password = requiredEnvironment('ADMIN_PASSWORD');

    if (
        strlen($name) > 150
        || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || strlen($email) > 190
        || strlen($password) < 12
        || strlen($password) > 128
    ) {
        throw new RuntimeException(
            'Controleer de naam, het e-mailadres en het adminwachtwoord.'
        );
    }

    $pdo = new PDO(
        sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            requiredEnvironment('DB_HOST'),
            (int) requiredEnvironment('DB_PORT'),
            requiredEnvironment('DB_NAME')
        ),
        requiredEnvironment('DB_USER'),
        requiredEnvironment('DB_PASSWORD'),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    $statement = $pdo->query(
        'SELECT COUNT(*) FROM admin_users'
    );

    $adminCount = (int) $statement->fetchColumn();

    if ($adminCount > 0) {
        echo "Er bestaat al een beheerder. Geen wijzigingen uitgevoerd.\n";
        exit(0);
    }

    $statement = $pdo->prepare(
        'INSERT INTO admin_users
            (name, email, password_hash, is_active)
         VALUES
            (:name, :email, :password_hash, 1)'
    );

    $statement->execute([
        'name' => $name,
        'email' => $email,
        'password_hash' => password_hash(
            $password,
            PASSWORD_DEFAULT
        ),
    ]);

    echo "Eerste CMS-beheerder aangemaakt.\n";
    exit(0);
} catch (Throwable $exception) {
    error_log(
        'Admin-seed mislukt: ' . $exception->getMessage()
    );

    exit(1);
}