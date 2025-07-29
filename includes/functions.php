<?php
// includes/functions.php
require_once __DIR__ . '/../config.php';

/**
 * Đăng ký user mới
 */
function registerUser($data, $files) {
    global $pdo;

    // Kiểm tra mật khẩu
    if ($data['password'] !== $data['confirm_password']) {
        return 'Password và Confirm không khớp.';
    }

    $invite = trim($data['invite_code']);
    $cccd_number = trim($data['cccd_number']);
    $cccd_image  = null;

    // Nếu không có invite code, bắt buộc upload CCCD
    if (empty($invite)) {
        if (empty($files['cccd_image']['name'])) {
            return 'Vui lòng upload ảnh CCCD.';
        }
        // Upload file
        $ext = pathinfo($files['cccd_image']['name'], PATHINFO_EXTENSION);
        $target = 'assets/uploads/cccd_' . time() . ".{$ext}";
        move_uploaded_file($files['cccd_image']['tmp_name'], __DIR__ . '/../' . $target);
        $cccd_image = $target;
    }

    // Hash password
    $hash = password_hash($data['password'], PASSWORD_DEFAULT);

    $stmt = $pdo->prepare(
        "INSERT INTO users
        (first_name, last_name, phone, email, cccd_number, cccd_image,
         company, password_hash, dob, address, invite_code)
        VALUES
        (:first_name, :last_name, :phone, :email, :cccd_number, :cccd_image,
         :company, :password_hash, :dob, :address, :invite_code)"
    );

    $stmt->execute([
        ':first_name'    => $data['first_name'],
        ':last_name'     => $data['last_name'],
        ':phone'         => $data['phone'],
        ':email'         => $data['email'],
        ':cccd_number'   => $cccd_number ?: null,
        ':cccd_image'    => $cccd_image,
        ':company'       => $data['company'],
        ':password_hash' => $hash,
        ':dob'           => $data['dob'],
        ':address'       => $data['address'],
        ':invite_code'   => $invite ?: null,
    ]);

    return true;
}

/**
 * Đăng nhập user
 */
function loginUser($email, $password) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        return $user;
    }
    return false;
}