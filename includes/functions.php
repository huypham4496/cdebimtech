<?php
// includes/functions.php

/**
 * Trả về PDO đã kết nối
 */
function getPDO(): PDO
{
    return new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
}

/**
 * Đăng ký user mới, tương ứng với trường trong DB:
 *   username, first_name, last_name, dob, address,
 *   company, phone, invite_code, email, password_hash, role, avatar
 */
function registerUser(
    PDO    $pdo,
    string $username,
    string $first_name,
    string $last_name,
    string $dob,
    string $address,
    string $company,
    string $phone,
    ?string $invite_code,
    string $email,
    string $password_hash,
    string $role = 'user',
    ?string $avatar = null
): bool {
    $sql = "INSERT INTO users
      (username, first_name, last_name, dob, address, company, phone,
       invite_code, email, password_hash, role, avatar)
     VALUES
      (:username, :first, :last, :dob, :address, :company, :phone,
       :invite, :email, :phash, :role, :avatar)";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        ':username'  => $username,
        ':first'     => $first_name,
        ':last'      => $last_name,
        ':dob'       => $dob ?: null,
        ':address'   => $address ?: null,
        ':company'   => $company ?: null,
        ':phone'     => $phone ?: null,
        ':invite'    => $invite_code ?: null,
        ':email'     => $email,
        ':phash'     => $password_hash,
        ':role'      => $role,
        ':avatar'    => $avatar ?: null,
    ]);
}


/**
 * Attempt to log in a user.
 *
 * @param PDO    $pdo
 * @param string $email
 * @param string $password  Plain text password
 * @return array|false      User record on success, false on failure
 */
function loginUser(PDO $pdo, string $email, string $password)
{
    $sql = "SELECT id, username, first_name, last_name, email, password, avatar, role
            FROM users
            WHERE email = :email
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user && password_verify($password, $user['password'])) {
        // Remove password before returning
        unset($user['password']);
        return $user;
    }
    return false;
}

/**
 * Update user settings.
 *
 * @param PDO    $pdo
 * @param int    $userId
 * @param array  $fields       Associative array field=>value to update
 * @return bool
 */
function updateUserSettings(PDO $pdo, int $userId, array $fields): bool
{
    $columns = [];
    $params  = [];
    foreach ($fields as $col => $val) {
        $columns[]           = "$col = :$col";
        $params[":$col"]     = $val;
    }
    $params[':id'] = $userId;
    $sql = "UPDATE users SET " . implode(', ', $columns) . " WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

/**
 * Fetch user by ID.
 *
 * @param PDO $pdo
 * @param int $userId
 * @return array|false
 */
function getUserById(PDO $pdo, int $userId)
{
    $stmt = $pdo->prepare("SELECT id, username, first_name, last_name, email, avatar, dob, address, company, phone, role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
