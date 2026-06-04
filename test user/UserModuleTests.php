<?php
/**
 * UserModuleTests.php
 * -----------------------------------------------------------------------------
 * Portfolio 2 Test Scripts for HostelHub User Management Module.
 * Each section represents one test log / data type.
 * -----------------------------------------------------------------------------
 */

require_once "TestRunner.php";
require_once "UserValidator.php";

$runner = new TestRunner();

/**
 * INTEGER TEST: user_id int(11)
 */
$runner->section("Test Log 1 - User ID INTEGER Validation");

$runner->run("Extreme Min", "1", "Accepted", UserValidator::validateUserId(1), true);
$runner->run("Min -1", "0", "Validation error displayed", UserValidator::validateUserId(0), false);
$runner->run("Min (Boundary)", "1", "Accepted", UserValidator::validateUserId(1), true);
$runner->run("Min +1", "2", "Accepted", UserValidator::validateUserId(2), true);
$runner->run("Mid", "100", "Accepted", UserValidator::validateUserId(100), true);
$runner->run("Max -1", "2147483646", "Accepted", UserValidator::validateUserId(2147483646), true);
$runner->run("Max (Boundary)", "2147483647", "Accepted", UserValidator::validateUserId(2147483647), true);
$runner->run("Max +1", "2147483648", "Validation error displayed", UserValidator::validateUserId(2147483648), false);
$runner->run("Extreme Max", "2147483647", "Accepted", UserValidator::validateUserId(2147483647), true);
$runner->run("Invalid Data Type", "ABC", "Validation error displayed", UserValidator::validateUserId("ABC"), false);

/**
 * VARCHAR TEST: username varchar(50)
 */
$runner->section("Test Log 2 - Username VARCHAR(50) Validation");

$runner->run("Extreme Min", '""', "Username required message displayed", UserValidator::validateUsername(""), false);
$runner->run("Min -1", "0 characters", "Username required message displayed", UserValidator::validateUsername(""), false);
$runner->run("Min (Boundary)", "1 character", "Username accepted", UserValidator::validateUsername("a"), true);
$runner->run("Min +1", "2 characters", "Username accepted", UserValidator::validateUsername("ab"), true);
$runner->run("Mid", "25 characters", "Username accepted", UserValidator::validateUsername(str_repeat("u", 25)), true);
$runner->run("Max -1", "49 characters", "Username accepted", UserValidator::validateUsername(str_repeat("u", 49)), true);
$runner->run("Max (Boundary)", "50 characters", "Username accepted", UserValidator::validateUsername(str_repeat("u", 50)), true);
$runner->run("Max +1", "51 characters", "Validation error displayed", UserValidator::validateUsername(str_repeat("u", 51)), false);
$runner->run("Extreme Max", "50 characters", "Username accepted", UserValidator::validateUsername(str_repeat("u", 50)), true);
$runner->run("Invalid Data Type", "12345 as integer", "Validation error displayed", UserValidator::validateUsername(12345), false);

/**
 * VARCHAR TEST: password varchar(255)
 */
$runner->section("Test Log 3 - Password VARCHAR(255) Validation");

$runner->run("Extreme Min", '""', "Password required message displayed", UserValidator::validatePassword(""), false);
$runner->run("Min -1", "7 characters", "Password must be at least 8 characters", UserValidator::validatePassword("1234567"), false);
$runner->run("Min (Boundary)", "8 characters", "Password accepted", UserValidator::validatePassword("12345678"), true);
$runner->run("Min +1", "9 characters", "Password accepted", UserValidator::validatePassword("123456789"), true);
$runner->run("Mid", "128 characters", "Password accepted", UserValidator::validatePassword(str_repeat("P", 128)), true);
$runner->run("Max -1", "254 characters", "Password accepted", UserValidator::validatePassword(str_repeat("P", 254)), true);
$runner->run("Max (Boundary)", "255 characters", "Password accepted", UserValidator::validatePassword(str_repeat("P", 255)), true);
$runner->run("Max +1", "256 characters", "Validation error displayed", UserValidator::validatePassword(str_repeat("P", 256)), false);
$runner->run("Extreme Max", "255 characters", "Password accepted", UserValidator::validatePassword(str_repeat("P", 255)), true);
$runner->run("Invalid Data Type", "NULL", "Validation error displayed", UserValidator::validatePassword(null), false);

/**
 * VARCHAR TEST: full_name varchar(100)
 */
$runner->section("Test Log 4 - Full Name VARCHAR(100) Validation");

$runner->run("Extreme Min", '""', "Full name required message displayed", UserValidator::validateFullName(""), false);
$runner->run("Min -1", "0 characters", "Validation error displayed", UserValidator::validateFullName(""), false);
$runner->run("Min (Boundary)", "1 character", "Full name accepted", UserValidator::validateFullName("A"), true);
$runner->run("Min +1", "2 characters", "Full name accepted", UserValidator::validateFullName("AB"), true);
$runner->run("Mid", "50 characters", "Full name accepted", UserValidator::validateFullName(str_repeat("A", 50)), true);
$runner->run("Max -1", "99 characters", "Full name accepted", UserValidator::validateFullName(str_repeat("A", 99)), true);
$runner->run("Max (Boundary)", "100 characters", "Full name accepted", UserValidator::validateFullName(str_repeat("A", 100)), true);
$runner->run("Max +1", "101 characters", "Validation error displayed", UserValidator::validateFullName(str_repeat("A", 101)), false);
$runner->run("Extreme Max", "100 characters", "Full name accepted", UserValidator::validateFullName(str_repeat("A", 100)), true);
$runner->run("Invalid Data Type", "12345 as integer", "Validation error displayed", UserValidator::validateFullName(12345), false);

/**
 * ENUM TEST: role enum('admin','staff')
 */
$runner->section("Test Log 5 - Role ENUM Validation");

$runner->run("Extreme Min", '""', "Invalid role message displayed", UserValidator::validateRole(""), false);
$runner->run("Min -1", "manager", "Invalid role message displayed", UserValidator::validateRole("manager"), false);
$runner->run("Min (Boundary)", "admin", "Role accepted", UserValidator::validateRole("admin"), true);
$runner->run("Min +1", "staff", "Role accepted", UserValidator::validateRole("staff"), true);
$runner->run("Mid", "admin", "Role accepted", UserValidator::validateRole("admin"), true);
$runner->run("Max -1", "admin", "Role accepted", UserValidator::validateRole("admin"), true);
$runner->run("Max (Boundary)", "staff", "Role accepted", UserValidator::validateRole("staff"), true);
$runner->run("Max +1", "superadmin", "Validation error displayed", UserValidator::validateRole("superadmin"), false);
$runner->run("Extreme Max", "staff", "Role accepted", UserValidator::validateRole("staff"), true);
$runner->run("Invalid Data Type", "123 as integer", "Validation error displayed", UserValidator::validateRole(123), false);

/**
 * BOOLEAN TEST: is_active tinyint(1)
 */
$runner->section("Test Log 6 - is_active BOOLEAN/TINYINT Validation");

$runner->run("Extreme Min", "0", "Account inactive accepted", UserValidator::validateIsActive(0), true);
$runner->run("Min -1", "-1", "Validation error displayed", UserValidator::validateIsActive(-1), false);
$runner->run("Min (Boundary)", "0", "Account inactive accepted", UserValidator::validateIsActive(0), true);
$runner->run("Min +1", "1", "Account active accepted", UserValidator::validateIsActive(1), true);
$runner->run("Mid", "1", "Account active accepted", UserValidator::validateIsActive(1), true);
$runner->run("Max -1", "0", "Account inactive accepted", UserValidator::validateIsActive(0), true);
$runner->run("Max (Boundary)", "1", "Account active accepted", UserValidator::validateIsActive(1), true);
$runner->run("Max +1", "2", "Validation error displayed", UserValidator::validateIsActive(2), false);
$runner->run("Extreme Max", "1", "Account active accepted", UserValidator::validateIsActive(1), true);
$runner->run("Invalid Data Type", "abc", "Validation error displayed", UserValidator::validateIsActive("abc"), false);

/**
 * DATETIME TEST: created_at datetime
 */
$runner->section("Test Log 7 - created_at DATETIME Validation");

$runner->run("Extreme Min", "1900-01-01 00:00:00", "Date accepted", UserValidator::validateCreatedAt("1900-01-01 00:00:00"), true);
$runner->run("Min -1", "1899-12-31 23:59:59", "Validation error displayed", UserValidator::validateCreatedAt("1899-12-31 23:59:59"), false);
$runner->run("Min (Boundary)", "1900-01-01 00:00:00", "Date accepted", UserValidator::validateCreatedAt("1900-01-01 00:00:00"), true);
$runner->run("Min +1", "1900-01-02 00:00:00", "Date accepted", UserValidator::validateCreatedAt("1900-01-02 00:00:00"), true);
$runner->run("Mid", "2025-05-20 12:00:00", "Date accepted", UserValidator::validateCreatedAt("2025-05-20 12:00:00"), true);
$runner->run("Max -1", "2099-12-30 23:59:59", "Date accepted", UserValidator::validateCreatedAt("2099-12-30 23:59:59"), true);
$runner->run("Max (Boundary)", "2099-12-31 23:59:59", "Date accepted", UserValidator::validateCreatedAt("2099-12-31 23:59:59"), true);
$runner->run("Max +1", "2100-01-01 00:00:00", "Validation error displayed", UserValidator::validateCreatedAt("2100-01-01 00:00:00"), false);
$runner->run("Extreme Max", "2099-12-31 23:59:59", "Date accepted", UserValidator::validateCreatedAt("2099-12-31 23:59:59"), true);
$runner->run("Invalid Data Type", "ABCDEF", "Validation error displayed", UserValidator::validateCreatedAt("ABCDEF"), false);

$runner->render();
?>