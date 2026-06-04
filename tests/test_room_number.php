<?php
/**
 * test_room_number.php  –  Portfolio 2 Test Log 4
 * Boundary value analysis for the Room Number field.
 * Rule: optional; if provided must be an integer 1–500.
 */
require_once __DIR__ . '/validate_student.php';
require_once __DIR__ . '/TestRunner.php';

$runner = new TestRunner();
$runner->section('Test Log 4 – Room Number');

$runner->run('Extreme Min',
    '"0"  (zero)',
    'FAIL – "Room number must be at least 1."',
    validate_room_number('0'),
    false
);

$runner->run('Min -1',
    '"0"  (same as extreme min for integer range)',
    'FAIL – "Room number must be at least 1."',
    validate_room_number('0'),
    false
);

$runner->run('Min (Boundary)',
    '"1"',
    'PASS – accepted',
    validate_room_number('1'),
    true
);

$runner->run('Min +1',
    '"2"',
    'PASS – accepted',
    validate_room_number('2'),
    true
);

$runner->run('Max -1',
    '"499"',
    'PASS – accepted',
    validate_room_number('499'),
    true
);

$runner->run('Max (Boundary)',
    '"500"',
    'PASS – accepted',
    validate_room_number('500'),
    true
);

$runner->run('Max +1',
    '"501"',
    'FAIL – "Room number must not exceed 500."',
    validate_room_number('501'),
    false
);

$runner->run('Mid',
    '"250"',
    'PASS – accepted',
    validate_room_number('250'),
    true
);

$runner->run('Extreme Max',
    '"99999"',
    'FAIL – "Room number must not exceed 500."',
    validate_room_number('99999'),
    false
);

$runner->run('Invalid data type',
    '"ABC"  (non-numeric)',
    'FAIL – "Room number must be a whole number."',
    validate_room_number('ABC'),
    false
);

$runner->run('Other tests',
    '"-5"  (negative integer)',
    'FAIL – "Room number must be at least 1."',
    validate_room_number('-5'),
    false
);

return $runner;
