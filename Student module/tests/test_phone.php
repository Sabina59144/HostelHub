<?php
/**
 * test_phone.php  –  Portfolio 2 Test Log 2
 * Boundary value analysis for the Phone Number field.
 * Rule: required, 10–11 numeric digits (UK format).
 */
require_once __DIR__ . '/validate_student.php';
require_once __DIR__ . '/TestRunner.php';

$runner = new TestRunner();
$runner->section('Test Log 2 – Phone Number');

$runner->run('Extreme Min',
    '(empty string)',
    'FAIL – "Phone number is required."',
    validate_phone(''),
    false
);

$runner->run('Min -1',
    '"074123456"  (9 digits)',
    'FAIL – too short',
    validate_phone('074123456'),
    false
);

$runner->run('Min (Boundary)',
    '"0741234567"  (10 digits)',
    'PASS – accepted',
    validate_phone('0741234567'),
    true
);

$runner->run('Min +1',
    '"07412345678"  (11 digits)',
    'PASS – accepted',
    validate_phone('07412345678'),
    true
);

$runner->run('Max -1',
    '"0741234567"  (10 digits)',
    'PASS – accepted',
    validate_phone('0741234567'),
    true
);

$runner->run('Max (Boundary)',
    '"07412345678"  (11 digits)',
    'PASS – accepted',
    validate_phone('07412345678'),
    true
);

$runner->run('Max +1',
    '"074123456789"  (12 digits)',
    'FAIL – too long',
    validate_phone('074123456789'),
    false
);

$runner->run('Mid',
    '"07412 345678"  (11 chars with space)',
    'PASS – spaces stripped, 11 digits accepted',
    validate_phone('07412 345678'),
    true
);

$runner->run('Extreme Max',
    '"00000000000000000000"  (20 digits)',
    'FAIL – too long',
    validate_phone('00000000000000000000'),
    false
);

$runner->run('Invalid data type',
    '"07abc12345"  (contains letters)',
    'FAIL – "Phone number must contain digits only."',
    validate_phone('07abc12345'),
    false
);

$runner->run('Other tests',
    '"+447412345678"  (international + prefix)',
    'FAIL – non-digit character rejected',
    validate_phone('+447412345678'),
    false
);

return $runner;
