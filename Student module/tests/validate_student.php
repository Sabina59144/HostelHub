<?php
/**
 * validate_student.php
 * ──────────────────────────────────────────────────────────────
 * Centralised validation functions for the Student Module.
 * Used by both the application forms (add/edit) and the test suite.
 *
 * Each function returns an array:
 *   ['valid' => bool, 'message' => string]
 * ──────────────────────────────────────────────────────────────
 */

/**
 * Validate student full name.
 *   - Required
 *   - Min 2 characters, Max 50 characters
 *   - Letters, spaces, hyphens and apostrophes only
 */
function validate_full_name(string $name): array {
    $name = trim($name);

    if ($name === '') {
        return ['valid' => false, 'message' => 'Full name is required.'];
    }
    if (strlen($name) < 2) {
        return ['valid' => false, 'message' => 'Name must be at least 2 characters.'];
    }
    if (strlen($name) > 50) {
        return ['valid' => false, 'message' => 'Name must not exceed 50 characters.'];
    }
    if (!preg_match("/^[a-zA-Z\s\-']+$/", $name)) {
        return ['valid' => false, 'message' => 'Name must contain letters, spaces, hyphens or apostrophes only.'];
    }

    return ['valid' => true, 'message' => 'Full name is valid.'];
}

/**
 * Validate phone number.
 *   - Required
 *   - 10–11 digits only (UK format)
 *   - Strips spaces before checking
 */
function validate_phone(string $phone): array {
    $phone = trim($phone);
    $digits = preg_replace('/\s+/', '', $phone); // strip internal spaces

    if ($digits === '') {
        return ['valid' => false, 'message' => 'Phone number is required.'];
    }
    if (!preg_match('/^[0-9]{10,11}$/', $digits)) {
        if (preg_match('/[^0-9\s]/', $phone)) {
            return ['valid' => false, 'message' => 'Phone number must contain digits only.'];
        }
        $len = strlen($digits);
        if ($len < 10) {
            return ['valid' => false, 'message' => 'Phone number must be at least 10 digits.'];
        }
        return ['valid' => false, 'message' => 'Phone number must not exceed 11 digits.'];
    }

    return ['valid' => true, 'message' => 'Phone number is valid.'];
}

/**
 * Validate email address.
 *   - Required
 *   - Min 6 characters
 *   - Max 100 characters
 *   - Must pass filter_var(FILTER_VALIDATE_EMAIL)
 */
function validate_email_address(string $email): array {
    $email = trim($email);

    if ($email === '') {
        return ['valid' => false, 'message' => 'Email address is required.'];
    }
    if (strlen($email) < 6) {
        return ['valid' => false, 'message' => 'Email address must be at least 6 characters.'];
    }
    if (strlen($email) > 100) {
        return ['valid' => false, 'message' => 'Email address must not exceed 100 characters.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['valid' => false, 'message' => 'Invalid email format — must contain @ and a valid domain.'];
    }

    return ['valid' => true, 'message' => 'Email address is valid.'];
}

/**
 * Validate room number.
 *   - Optional (empty string → valid, no room assigned)
 *   - If provided: must be an integer between 1 and 500
 */
function validate_room_number(string $room): array {
    $room = trim($room);

    if ($room === '') {
        return ['valid' => true, 'message' => 'No room assigned (optional field).'];
    }
    if (!is_numeric($room) || strpos($room, '.') !== false) {
        return ['valid' => false, 'message' => 'Room number must be a whole number.'];
    }
    $n = (int)$room;
    if ($n < 1) {
        return ['valid' => false, 'message' => 'Room number must be at least 1.'];
    }
    if ($n > 500) {
        return ['valid' => false, 'message' => 'Room number must not exceed 500.'];
    }

    return ['valid' => true, 'message' => 'Room number is valid.'];
}

/**
 * Validate student number format.
 *   - Required
 *   - Must match STU-YYYY-XXX (e.g. STU-2024-001)
 */
function validate_student_number(string $num): array {
    $num = trim($num);

    if ($num === '') {
        return ['valid' => false, 'message' => 'Student number is required.'];
    }
    if (!preg_match('/^STU-\d{4}-\d{3}$/', $num)) {
        return ['valid' => false, 'message' => 'Student number must follow the format STU-YYYY-XXX.'];
    }

    return ['valid' => true, 'message' => 'Student number is valid.'];
}
