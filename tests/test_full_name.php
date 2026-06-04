<?php
/**
 * test_full_name.php  –  Portfolio 2 Test Log 1
 * Boundary value analysis for the Student Full Name field.
 * Rule: required, 2–50 characters, letters/spaces/hyphens/apostrophes.
 */
require_once __DIR__ . '/validate_student.php';
require_once __DIR__ . '/TestRunner.php';

$runner = new TestRunner();
$runner->section('Test Log 1 – Student Full Name');

$name_49  = str_repeat('A', 49);
$name_50  = str_repeat('A', 50);
$name_51  = str_repeat('A', 51);
$name_200 = str_repeat('A', 200);

$runner->run('Extreme Min',
    '(empty string – 0 chars)',
    'FAIL – "Full name is required."',
    validate_full_name(''),
    false
);

$runner->run('Min -1',
    '"A"  (1 character)',
    'FAIL – "Name must be at least 2 characters."',
    validate_full_name('A'),
    false
);

$runner->run('Min (Boundary)',
    '"Jo"  (2 characters)',
    'PASS – Record accepted',
    validate_full_name('Jo'),
    true
);

$runner->run('Min +1',
    '"Tom"  (3 characters)',
    'PASS – Record accepted',
    validate_full_name('Tom'),
    true
);

$runner->run('Max -1',
    '"' . $name_49 . '"  (49 chars)',
    'PASS – Record accepted',
    validate_full_name($name_49),
    true
);

$runner->run('Max (Boundary)',
    '"' . $name_50 . '"  (50 chars)',
    'PASS – Record accepted',
    validate_full_name($name_50),
    true
);

$runner->run('Max +1',
    '"' . $name_51 . '"  (51 chars)',
    'FAIL – "Name must not exceed 50 characters."',
    validate_full_name($name_51),
    false
);

$runner->run('Mid',
    '"Diwash Ghimire"  (14 chars)',
    'PASS – Record accepted',
    validate_full_name('Diwash Ghimire'),
    true
);

$runner->run('Extreme Max',
    '"A×200"  (200 chars)',
    'FAIL – "Name must not exceed 50 characters."',
    validate_full_name($name_200),
    false
);

$runner->run('Invalid data type',
    '"12345"  (digits only)',
    'FAIL – "Name must contain letters..."',
    validate_full_name('12345'),
    false
);

$runner->run('Other tests',
    '"   "  (whitespace only)',
    'FAIL – "Full name is required." (after trim)',
    validate_full_name('   '),
    false
);

return $runner;
