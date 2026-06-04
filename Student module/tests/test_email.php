<?php
/**
 * test_email.php  –  Portfolio 2 Test Log 3
 * Boundary value analysis for the Email Address field.
 * Rule: required, 6–100 chars, valid format (must include @).
 */
require_once __DIR__ . '/validate_student.php';
require_once __DIR__ . '/TestRunner.php';

$runner = new TestRunner();
$runner->section('Test Log 3 – Email Address');

// '@x.uk' = 5 chars, so local part must be 95 'a's for exactly 100 chars total
$email_100 = str_repeat('a', 95) . '@x.uk';   // 100 chars (max boundary)
// 96 'a's + '@x.uk' = 101 chars (max+1)
$email_101 = str_repeat('a', 96) . '@x.uk';
// 200 'a's + '@b.com' = 206 chars (extreme max)
$email_206 = str_repeat('a', 200) . '@b.com';

$runner->run('Extreme Min',
    '(empty string)',
    'FAIL – "Email address is required."',
    validate_email_address(''),
    false
);

$runner->run('Min -1',
    '"a@b.c"  (5 chars)',
    'FAIL – too short',
    validate_email_address('a@b.c'),
    false
);

$runner->run('Min (Boundary)',
    '"a@b.cd"  (6 chars)',
    'PASS – accepted',
    validate_email_address('a@b.cd'),
    true
);

$runner->run('Min +1',
    '"ab@b.cd"  (7 chars)',
    'PASS – accepted',
    validate_email_address('ab@b.cd'),
    true
);

$runner->run('Max -1',
    '"diwashghimire99@email.co"  (24 chars)',
    'PASS – accepted',
    validate_email_address('diwashghimire99@email.co'),
    true
);

$runner->run('Max (Boundary)',
    '"aaaa...@x.uk"  (100 chars)',
    'PASS – accepted',
    validate_email_address($email_100),
    true
);

$runner->run('Max +1',
    '"aaaa...@x.uk"  (101 chars)',
    'FAIL – too long',
    validate_email_address($email_101),
    false
);

$runner->run('Mid',
    '"diwashghimire81@gmail.com"  (25 chars)',
    'PASS – accepted',
    validate_email_address('diwashghimire81@gmail.com'),
    true
);

$runner->run('Extreme Max',
    '"aaa...@b.com"  (206 chars)',
    'FAIL – too long',
    validate_email_address($email_206),
    false
);

$runner->run('Invalid data type',
    '"notanemail"  (no @ symbol)',
    'FAIL – invalid format',
    validate_email_address('notanemail'),
    false
);

$runner->run('Other tests',
    '"test@domain"  (no TLD)',
    'FAIL – invalid format',
    validate_email_address('test@domain'),
    false
);

return $runner;
