<?php
/**
 * UserValidator.php
 * -----------------------------------------------------------------------------
 * Validation functions for HostelHub users table.
 * These tests are based on the database structure:
 * user_id int(11), username varchar(50), password varchar(255),
 * full_name varchar(100), role enum('admin','staff'),
 * is_active tinyint(1), created_at datetime.
 * -----------------------------------------------------------------------------
 */

class UserValidator {

    public static function result(bool $valid, string $message): array {
        return [
            'valid' => $valid,
            'message' => $message
        ];
    }

    public static function validateUserId($value): array {
        if (!is_numeric($value)) {
            return self::result(false, "User ID must be an integer.");
        }

        $value = (int)$value;

        if ($value < 1) {
            return self::result(false, "User ID must be greater than 0.");
        }

        if ($value > 2147483647) {
            return self::result(false, "User ID exceeds integer limit.");
        }

        return self::result(true, "User ID accepted.");
    }

    public static function validateUsername($value): array {
        if (!is_string($value)) {
            return self::result(false, "Username must be a string.");
        }

        $value = trim($value);
        $length = strlen($value);

        if ($length < 1) {
            return self::result(false, "Username is required.");
        }

        if ($length > 50) {
            return self::result(false, "Username must not exceed 50 characters.");
        }

        return self::result(true, "Username accepted.");
    }

    public static function validatePassword($value): array {
        if (!is_string($value)) {
            return self::result(false, "Password must be a string.");
        }

        $length = strlen($value);

        if ($length < 1) {
            return self::result(false, "Password is required.");
        }

        if ($length < 8) {
            return self::result(false, "Password must be at least 8 characters.");
        }

        if ($length > 255) {
            return self::result(false, "Password must not exceed 255 characters.");
        }

        return self::result(true, "Password accepted.");
    }

    public static function validateFullName($value): array {
        if (!is_string($value)) {
            return self::result(false, "Full name must be a string.");
        }

        $value = trim($value);
        $length = strlen($value);

        if ($length < 1) {
            return self::result(false, "Full name is required.");
        }

        if ($length > 100) {
            return self::result(false, "Full name must not exceed 100 characters.");
        }

        return self::result(true, "Full name accepted.");
    }

    public static function validateRole($value): array {
        if (!is_string($value)) {
            return self::result(false, "Role must be a string.");
        }

        if (!in_array($value, ['admin', 'staff'])) {
            return self::result(false, "Invalid role selected.");
        }

        return self::result(true, "Role accepted.");
    }

    public static function validateIsActive($value): array {
        if ($value === 0 || $value === 1 || $value === '0' || $value === '1') {
            return self::result(true, "Account status accepted.");
        }

        return self::result(false, "Account status must be 0 or 1.");
    }

    public static function validateCreatedAt($value): array {
        if (!is_string($value)) {
            return self::result(false, "Created date must be a valid datetime string.");
        }

        $date = DateTime::createFromFormat('Y-m-d H:i:s', $value);
       $errors = DateTime::getLastErrors();

if ($errors === false) {
    $warningCount = 0;
    $errorCount = 0;
} else {
    $warningCount = $errors['warning_count'];
    $errorCount = $errors['error_count'];
}

if (!$date || $warningCount > 0 || $errorCount > 0) {
            return self::result(false, "Created date is not a valid datetime.");
        }

        $min = new DateTime('1900-01-01 00:00:00');
        $max = new DateTime('2099-12-31 23:59:59');

        if ($date < $min) {
            return self::result(false, "Created date is before minimum allowed date.");
        }

        if ($date > $max) {
            return self::result(false, "Created date is after maximum allowed date.");
        }

        return self::result(true, "Created date accepted.");
    }
}
?>