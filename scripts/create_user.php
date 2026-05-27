<?php
declare(strict_types=1);

// scripts/create_user.php
// Usage:
// php scripts/create_user.php email password role first_name last_name employee_id

require_once __DIR__ . '/../vendor/autoload.php';

// Load env if present
if (class_exists(\Dotenv\Dotenv::class)) {
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();
}

require_once __DIR__ . '/../config/database.php';

$argv0 = $argv[0] ?? 'create_user.php';

if ($argc < 3) {
    echo "Usage: php {$argv0} email password [role] [first_name] [last_name] [employee_id]\n";
    exit(1);
}

$email      = $argv[1];
$password   = $argv[2];
$roleName   = $argv[3] ?? 'Admin';
$firstName  = $argv[4] ?? 'Test';
$lastName   = $argv[5] ?? 'User';
$employeeId = $argv[6] ?? 'EMP001';

try {
    $db = Database::connect();

    // Ensure role exists (simple upsert)
    $stmt = $db->prepare('SELECT id FROM roles WHERE role_name = :role LIMIT 1');
    $stmt->execute([':role' => $roleName]);
    $role = $stmt->fetch();

    if ($role) {
        $roleId = (int) $role['id'];
    } else {
        $stmt = $db->prepare('INSERT INTO roles (role_name) VALUES (:role)');
        $stmt->execute([':role' => $roleName]);
        $roleId = (int) $db->lastInsertId();
    }

    // Hash password using bcrypt
    $hash = password_hash($password, PASSWORD_BCRYPT);

    // Insert user — adjust columns if your schema differs
    $insertSql = "INSERT INTO users (employee_id, first_name, last_name, email, password, is_active, role_id, department_id) VALUES (:employee_id, :first_name, :last_name, :email, :password, :is_active, :role_id, :department_id)";

    $stmt = $db->prepare($insertSql);
    $stmt->execute([
        ':employee_id' => $employeeId,
        ':first_name'  => $firstName,
        ':last_name'   => $lastName,
        ':email'       => $email,
        ':password'    => $hash,
        ':is_active'   => 1,
        ':role_id'     => $roleId,
        ':department_id' => null,
    ]);

    $userId = (int) $db->lastInsertId();

    echo "User created successfully. ID: {$userId}\n";
    echo "Email: {$email}\n";
    echo "Password: {$password}\n";
    echo "Role: {$roleName} (id={$roleId})\n";
    exit(0);

} catch (Exception $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(2);
}
