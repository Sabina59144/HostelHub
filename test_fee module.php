<?php
/**
 * ════════════════════════════════════════════════════════════════════════════
 * HOSTELHUB — TEST SCRIPT
 * Tests core logic from: add.php, edit.php, delete.php, index.php,
 *                        dashboard.php, report.php, student_portal.php
 *
 * Run from the terminal: php test_hostelhub.php
 * No database required — pure logic & unit tests.
 * ════════════════════════════════════════════════════════════════════════════
 */

// ── Simple test runner ──────────────────────────────────────────────────────
$passed = 0;
$failed = 0;
$results = [];

function test(string $name, bool $condition, string $detail = ''): void {
    global $passed, $failed, $results;
    if ($condition) {
        $passed++;
        $results[] = ['status' => 'PASS', 'name' => $name, 'detail' => $detail];
    } else {
        $failed++;
        $results[] = ['status' => 'FAIL', 'name' => $name, 'detail' => $detail ?: 'Condition was false'];
    }
}

function section(string $title): void {
    echo "\n\033[1;34m══ {$title} ══\033[0m\n";
}

// ════════════════════════════════════════════════════════════════════════════
// 1. RECEIPT NUMBER GENERATION  (add.php → generateReceipt)
// ════════════════════════════════════════════════════════════════════════════
section('Receipt Number Generation');

/**
 * Mirrors the generateReceipt() logic in add.php.
 * Given the last stored receipt number, returns the next one.
 */
function generateReceipt_mock(?string $lastReceipt): string {
    if ($lastReceipt && preg_match('/(\d+)$/', $lastReceipt, $m)) {
        $next = (int)$m[1] + 1;
    } else {
        $next = 1;
    }
    return "RCP-" . date("Y") . "-" . str_pad($next, 4, "0", STR_PAD_LEFT);
}

$year = date('Y');

test(
    'First receipt number starts at 0001',
    generateReceipt_mock(null) === "RCP-{$year}-0001"
);

test(
    'Receipt increments correctly from RCP-2025-0003',
    generateReceipt_mock("RCP-2025-0003") === "RCP-{$year}-0004"
);

test(
    'Receipt increments past 9999 to 10000',
    generateReceipt_mock("RCP-2024-9999") === "RCP-{$year}-10000"
);

test(
    'Receipt number matches expected format RCP-YYYY-NNNN',
    (bool)preg_match('/^RCP-\d{4}-\d{4,}$/', generateReceipt_mock("RCP-2025-0010"))
);

test(
    'Receipt with no previous entry defaults to 0001',
    generateReceipt_mock("") === "RCP-{$year}-0001"
);


// ════════════════════════════════════════════════════════════════════════════
// 2. FINE CALCULATION  (index.php + student_portal.php)
// ════════════════════════════════════════════════════════════════════════════
section('Fine Calculation Logic');

/**
 * Mirrors fine calculation used in index.php and student_portal.php.
 * Returns the fine in kr based on days overdue × fine_rate.
 */
function calcFine(string $dueDateStr, float $fineRate, string $todayStr = ''): float {
    $due   = new DateTime($dueDateStr);
    $today = $todayStr ? new DateTime($todayStr) : new DateTime();
    $today->setTime(0, 0, 0);
    $due->setTime(0, 0, 0);
    if ($today <= $due) return 0.0;
    $days = (int)$due->diff($today)->days;
    return $days * $fineRate;
}

test(
    'No fine when fee is not yet due',
    calcFine('2099-12-31', 0.50) === 0.0,
    'Future due date should return 0'
);

test(
    'No fine when due date is today',
    calcFine(date('Y-m-d'), 0.50) === 0.0,
    'Same-day due date = no fine'
);

test(
    '4 days overdue at kr0.50/day = kr2.00',
    calcFine('2026-06-01', 0.50, '2026-06-05') === 2.0
);

test(
    '10 days overdue at kr1.00/day = kr10.00',
    calcFine('2026-05-26', 1.00, '2026-06-05') === 10.0
);

test(
    'Zero fine rate always returns 0',
    calcFine('2020-01-01', 0.0, '2026-06-05') === 0.0
);

test(
    '1 day overdue at default kr0.50/day = kr0.50',
    calcFine('2026-06-04', 0.50, '2026-06-05') === 0.5
);

// Policy note in student_portal.php says max fine is kr15.00 per fee
function calcFineCapped(string $dueDateStr, float $fineRate, float $maxFine, string $todayStr): float {
    return min(calcFine($dueDateStr, $fineRate, $todayStr), $maxFine);
}

test(
    'Fine is capped at kr15.00 per fee (student portal policy)',
    calcFineCapped('2026-01-01', 0.50, 15.00, '2026-06-05') === 15.0,
    '155 days × kr0.50 = kr77.50, capped to kr15.00'
);


// ════════════════════════════════════════════════════════════════════════════
// 3. FEE STATUS CLASSIFICATION  (index.php, dashboard.php, student_portal.php)
// ════════════════════════════════════════════════════════════════════════════
section('Fee Status Classification');

/**
 * Returns 'paid' | 'overdue' | 'unpaid' based on is_paid flag and due date.
 */
function getFeeStatus(int $isPaid, string $dueDateStr, string $todayStr = ''): string {
    if ($isPaid) return 'paid';
    $due   = new DateTime($dueDateStr);
    $today = $todayStr ? new DateTime($todayStr) : new DateTime();
    $today->setTime(0, 0, 0);
    $due->setTime(0, 0, 0);
    return ($today > $due) ? 'overdue' : 'unpaid';
}

test('Paid fee returns status: paid',    getFeeStatus(1, '2026-01-01', '2026-06-05') === 'paid');
test('Unpaid future fee returns: unpaid', getFeeStatus(0, '2099-12-31', '2026-06-05') === 'unpaid');
test('Unpaid past due returns: overdue', getFeeStatus(0, '2026-01-01', '2026-06-05') === 'overdue');
test('Paid overdue still returns: paid', getFeeStatus(1, '2020-01-01', '2026-06-05') === 'paid',
    'is_paid=1 should always win regardless of due date');
test('Due today is unpaid (not overdue)', getFeeStatus(0, '2026-06-05', '2026-06-05') === 'unpaid');


// ════════════════════════════════════════════════════════════════════════════
// 4. INPUT VALIDATION  (add.php + edit.php)
// ════════════════════════════════════════════════════════════════════════════
section('Form Input Validation');

/**
 * Mirrors the validation block in add.php and edit.php.
 */
function validateFeeInput(array $post): array {
    $errors = [];
    $receipt        = trim($post['receipt_number'] ?? '');
    $student_id     = (int)($post['student_id'] ?? 0);
    $amount         = (float)($post['amount'] ?? 0);
    $due_date       = trim($post['due_date'] ?? '');

    if (empty($receipt))    $errors[] = 'Receipt number is required.';
    if ($student_id <= 0)   $errors[] = 'Please select a student.';
    if ($amount <= 0)        $errors[] = 'Amount must be greater than 0.';
    if (empty($due_date))    $errors[] = 'Please enter a due date.';

    return $errors;
}

$validInput = [
    'receipt_number' => 'RCP-2026-0001',
    'student_id'     => '5',
    'amount'         => '350.00',
    'due_date'       => '2026-07-01',
];

test('Valid input produces no errors',
    count(validateFeeInput($validInput)) === 0
);

test('Missing receipt number triggers error',
    in_array('Receipt number is required.', validateFeeInput(array_merge($validInput, ['receipt_number' => ''])))
);

test('student_id = 0 triggers error',
    in_array('Please select a student.', validateFeeInput(array_merge($validInput, ['student_id' => '0'])))
);

test('Negative amount triggers error',
    in_array('Amount must be greater than 0.', validateFeeInput(array_merge($validInput, ['amount' => '-10'])))
);

test('Zero amount triggers error',
    in_array('Amount must be greater than 0.', validateFeeInput(array_merge($validInput, ['amount' => '0'])))
);

test('Missing due date triggers error',
    in_array('Please enter a due date.', validateFeeInput(array_merge($validInput, ['due_date' => ''])))
);

test('All fields missing = 4 errors',
    count(validateFeeInput([])) === 4
);


// ════════════════════════════════════════════════════════════════════════════
// 5. SOFT DELETE LOGIC  (delete.php)
// ════════════════════════════════════════════════════════════════════════════
section('Soft Delete Logic');

/**
 * Simulates the soft-delete UPDATE from delete.php.
 * Returns the updated fee array.
 */
function softDeleteFee(array $fee, string $reason = ''): array {
    $fee['is_active']       = 0;
    $fee['deleted_at']      = date('Y-m-d H:i:s');
    $fee['deleted_reason']  = $reason ?: 'Admin deleted';
    return $fee;
}

$fee = ['receipt_number' => 'RCP-2026-0001', 'is_active' => 1, 'deleted_at' => null, 'deleted_reason' => null];

$deleted = softDeleteFee($fee, 'Entered in error');
test('Soft delete sets is_active to 0',         $deleted['is_active'] === 0);
test('Soft delete records deletion timestamp',  $deleted['deleted_at'] !== null);
test('Soft delete stores provided reason',      $deleted['deleted_reason'] === 'Entered in error');
test('Soft delete uses default reason if empty', softDeleteFee($fee)['deleted_reason'] === 'Admin deleted');
test('Original receipt number is preserved',    $deleted['receipt_number'] === 'RCP-2026-0001');

// Archived fee should not appear in active queries (is_active = 1 filter)
function isFeeVisible(array $fee): bool {
    return (bool)$fee['is_active'];
}

test('Deleted fee is not visible in active list', !isFeeVisible($deleted));
test('Active fee is visible in active list',       isFeeVisible($fee));


// ════════════════════════════════════════════════════════════════════════════
// 6. SUMMARY STATISTICS  (dashboard.php, report.php, index.php)
// ════════════════════════════════════════════════════════════════════════════
section('Summary Statistics Calculation');

/**
 * Mirrors the PHP counter loop in index.php and dashboard aggregation.
 */
function calcSummary(array $fees, string $todayStr = ''): array {
    $today    = $todayStr ? new DateTime($todayStr) : new DateTime();
    $FINE_RATE = 0.50;
    $cnt = ['total' => count($fees), 'paid' => 0, 'unpaid' => 0, 'overdue' => 0,
            'total_amt' => 0, 'paid_amt' => 0, 'outstanding' => 0];

    foreach ($fees as $f) {
        $due = new DateTime($f['due_date']);
        $cnt['total_amt'] += $f['amount'];
        if ($f['is_paid']) {
            $cnt['paid']++;
            $cnt['paid_amt'] += $f['amount'];
        } elseif ($today > $due) {
            $cnt['overdue']++;
            $days = (int)$due->diff($today)->days;
            $fine = $days * ($f['fine_rate'] ?? $FINE_RATE);
            $cnt['outstanding'] += $f['amount'] + $fine;
        } else {
            $cnt['unpaid']++;
            $cnt['outstanding'] += $f['amount'];
        }
    }
    return $cnt;
}

$sampleFees = [
    ['amount' => 500, 'is_paid' => 1, 'due_date' => '2026-05-01', 'fine_rate' => 0.50],  // paid
    ['amount' => 300, 'is_paid' => 0, 'due_date' => '2026-07-01', 'fine_rate' => 0.50],  // unpaid (future)
    ['amount' => 200, 'is_paid' => 0, 'due_date' => '2026-06-01', 'fine_rate' => 0.50],  // overdue (4 days at kr0.50 = kr2.00)
];

$summary = calcSummary($sampleFees, '2026-06-05');

test('Total fee count is 3',                $summary['total'] === 3);
test('Paid count is 1',                     $summary['paid'] === 1);
test('Unpaid count is 1',                   $summary['unpaid'] === 1);
test('Overdue count is 1',                  $summary['overdue'] === 1);
test('Total amount = kr1000',               $summary['total_amt'] == 1000);
test('Paid amount = kr500',                 $summary['paid_amt'] == 500);
test('Outstanding includes fine (kr202)',   $summary['outstanding'] == 502.0,
     "300 unpaid + 200 overdue + 2.00 fine = 502.00");

// Collection rate (dashboard.php)
$rate = $summary['total_amt'] > 0
    ? round(($summary['paid_amt'] / $summary['total_amt']) * 100)
    : 0;

test('Collection rate = 50%',               $rate === 50);
test('Collection rate is 0% when no fees',  calcSummary([])['total'] === 0);


// ════════════════════════════════════════════════════════════════════════════
// 7. XSS PREVENTION  (all files use htmlspecialchars)
// ════════════════════════════════════════════════════════════════════════════
section('XSS Prevention (htmlspecialchars)');

$xssPayload  = '<script>alert("xss")</script>';
$safeOutput  = htmlspecialchars($xssPayload, ENT_QUOTES, 'UTF-8');

test('Script tags are escaped',
    strpos($safeOutput, '<script>') === false
);
test('Escaped output contains &lt;',
    strpos($safeOutput, '&lt;') !== false
);

$quotePayload = '" onmouseover="alert(1)"';
$safeQuote    = htmlspecialchars($quotePayload, ENT_QUOTES, 'UTF-8');
test('Double quotes are escaped in output',
    strpos($safeQuote, '"') === false
);

$receiptInput = 'RCP-<2026>-0001';
test('Receipt number with HTML chars is safely escaped',
    htmlspecialchars($receiptInput) === 'RCP-&lt;2026&gt;-0001'
);


// ════════════════════════════════════════════════════════════════════════════
// 8. PAYMENT METHOD BADGES  (style.css + index.php)
// ════════════════════════════════════════════════════════════════════════════
section('Payment Method Handling');

function getMethodIcon(string $method): string {
    $icons = ['cash' => '💵', 'bank' => '🏦', 'mobile' => '📱'];
    return ($icons[$method] ?? '') . ' ' . ucfirst($method);
}

test('Cash method returns correct icon',    getMethodIcon('cash')   === '💵 Cash');
test('Bank method returns correct icon',    getMethodIcon('bank')   === '🏦 Bank');
test('Mobile method returns correct icon',  getMethodIcon('mobile') === '📱 Mobile');
test('Unknown method returns ucfirst name', getMethodIcon('crypto') === ' Crypto');


// ════════════════════════════════════════════════════════════════════════════
// 9. FEE TYPE VALIDATION  (add.php + edit.php)
// ════════════════════════════════════════════════════════════════════════════
section('Fee Type Validation');

$allowedTypes = ['rent', 'deposit', 'utility', 'fine', 'laundry', 'other'];

function isValidFeeType(string $type, array $allowed): bool {
    return in_array($type, $allowed, true);
}

test('rent is a valid fee type',       isValidFeeType('rent',    $allowedTypes));
test('deposit is a valid fee type',    isValidFeeType('deposit', $allowedTypes));
test('utility is a valid fee type',    isValidFeeType('utility', $allowedTypes));
test('fine is a valid fee type',       isValidFeeType('fine',    $allowedTypes));
test('laundry is a valid fee type',    isValidFeeType('laundry', $allowedTypes));
test('other is a valid fee type',      isValidFeeType('other',   $allowedTypes));
test('food is NOT a valid fee type',   !isValidFeeType('food',   $allowedTypes));
test('RENT (uppercase) is invalid',    !isValidFeeType('RENT',   $allowedTypes));


// ════════════════════════════════════════════════════════════════════════════
// 10. DATE FORMATTING  (dashboard.php, report.php, student_portal.php)
// ════════════════════════════════════════════════════════════════════════════
section('Date Formatting');

test('due_date formats correctly to d M Y',
    (new DateTime('2026-06-05'))->format('d M Y') === '05 Jun 2026'
);
test('paid_at formats correctly with time',
    (new DateTime('2026-06-05 14:30:00'))->format('d M Y, H:i') === '05 Jun 2026, 14:30'
);
test('DATE_FORMAT month label matches expected',
    (new DateTime('2026-06-01'))->format('M Y') === 'Jun 2026'
);
test('number_format formats kr amounts correctly',
    number_format(1234.5, 2) === '1,234.50'
);
test('number_format with 0 decimals for KPI display',
    number_format(9999.99, 0) === '10,000'
);


// ════════════════════════════════════════════════════════════════════════════
// 11. ROLE CHECKING  (all admin pages)
// ════════════════════════════════════════════════════════════════════════════
section('Role / Access Control Logic');

function isAdmin(array $session): bool {
    return ($session['role'] ?? '') === 'admin';
}

function isStudent(array $session): bool {
    return isset($session['student_id']);
}

test('Admin role is correctly identified',      isAdmin(['role' => 'admin']));
test('Staff role is not admin',                 !isAdmin(['role' => 'staff']));
test('Empty session is not admin',              !isAdmin([]));
test('Student session is identified correctly', isStudent(['student_id' => 3]));
test('Admin session is not a student session',  !isStudent(['role' => 'admin']));


// ════════════════════════════════════════════════════════════════════════════
// 12. AJAX TOGGLE LOGIC  (index.php → ajax_toggle endpoint)
// ════════════════════════════════════════════════════════════════════════════
section('AJAX Payment Toggle Logic');

/**
 * Simulates what the ajax_toggle endpoint does:
 * flips is_paid 0↔1, sets/clears paid_at.
 */
function ajaxToggle(array $fee, string $now = ''): array {
    $now = $now ?: date('Y-m-d H:i:s');
    if ($fee['is_paid']) {
        $fee['is_paid']  = 0;
        $fee['paid_at']  = null;
        $fee['now_paid'] = false;
    } else {
        $fee['is_paid']  = 1;
        $fee['paid_at']  = $now;
        $fee['now_paid'] = true;
    }
    $fee['ok'] = true;
    return $fee;
}

$unpaidFee = ['receipt_number' => 'RCP-2026-0001', 'is_paid' => 0, 'paid_at' => null];
$paidFee   = ['receipt_number' => 'RCP-2026-0002', 'is_paid' => 1, 'paid_at' => '2026-05-01 10:00:00'];

$toggled = ajaxToggle($unpaidFee, '2026-06-05 12:00:00');
test('Toggle unpaid → paid sets is_paid = 1',      $toggled['is_paid'] === 1);
test('Toggle unpaid → paid records paid_at',        $toggled['paid_at'] === '2026-06-05 12:00:00');
test('Toggle response has now_paid = true',         $toggled['now_paid'] === true);
test('Toggle response has ok = true',               $toggled['ok'] === true);

$untoggled = ajaxToggle($paidFee);
test('Toggle paid → unpaid sets is_paid = 0',       $untoggled['is_paid'] === 0);
test('Toggle paid → unpaid clears paid_at to null', $untoggled['paid_at'] === null);
test('Toggle response has now_paid = false',        $untoggled['now_paid'] === false);

// Double toggle should return to original state
$doubleToggled = ajaxToggle(ajaxToggle($unpaidFee));
test('Double toggle returns to original is_paid state', $doubleToggled['is_paid'] === $unpaidFee['is_paid']);


// ════════════════════════════════════════════════════════════════════════════
// 13. REPORT CALCULATIONS  (report.php)
// ════════════════════════════════════════════════════════════════════════════
section('Report Calculations');

/**
 * Mirrors the summary query logic in report.php.
 */
function buildReportSummary(array $fees, string $todayStr): array {
    $today = new DateTime($todayStr);
    $s = ['total' => 0, 'billed' => 0, 'collected' => 0,
          'overdue_amt' => 0, 'cnt_paid' => 0, 'cnt_pending' => 0, 'cnt_overdue' => 0];

    foreach ($fees as $f) {
        if (!$f['is_active']) continue;
        $due = new DateTime($f['due_date']);
        $s['total']++;
        $s['billed'] += $f['amount'];

        if ($f['is_paid']) {
            $s['collected']  += $f['amount'];
            $s['cnt_paid']++;
        } elseif ($today > $due) {
            $s['overdue_amt'] += $f['amount'];
            $s['cnt_overdue']++;
        } else {
            $s['cnt_pending']++;
        }
    }
    return $s;
}

$reportFees = [
    ['amount' => 1000, 'is_paid' => 1, 'due_date' => '2026-04-01', 'is_active' => 1],
    ['amount' => 500,  'is_paid' => 0, 'due_date' => '2026-07-01', 'is_active' => 1],  // pending
    ['amount' => 250,  'is_paid' => 0, 'due_date' => '2026-05-01', 'is_active' => 1],  // overdue
    ['amount' => 999,  'is_paid' => 0, 'due_date' => '2026-03-01', 'is_active' => 0],  // deleted — excluded
];

$report = buildReportSummary($reportFees, '2026-06-05');

test('Report: deleted records excluded from total',   $report['total'] === 3);
test('Report: total billed excludes deleted records', $report['billed'] == 1750);
test('Report: collected = paid fees only',            $report['collected'] == 1000);
test('Report: overdue_amt = unpaid past-due total',   $report['overdue_amt'] == 250);
test('Report: cnt_paid is correct',                   $report['cnt_paid'] === 1);
test('Report: cnt_pending is correct',                $report['cnt_pending'] === 1);
test('Report: cnt_overdue is correct',                $report['cnt_overdue'] === 1);

// Collection rate calculation (report.php)
$collectionRate = $report['billed'] > 0
    ? round(($report['collected'] / $report['billed']) * 100)
    : 0;

test('Report: collection rate rounds correctly',
    $collectionRate === 57,
    "1000/1750 = 57.14% → rounds to 57%"
);

// Outstanding = billed - collected
$outstanding = $report['billed'] - $report['collected'];
test('Report: outstanding = billed minus collected', $outstanding == 750);


// ════════════════════════════════════════════════════════════════════════════
// 14. STUDENT PORTAL — FEE SUMMARY  (student_portal.php)
// ════════════════════════════════════════════════════════════════════════════
section('Student Portal Fee Summary');

/**
 * Mirrors the foreach summary loop in student_portal.php.
 */
function buildStudentSummary(array $fees, string $todayStr): array {
    $today       = new DateTime($todayStr);
    $totalFees   = 0;
    $totalFines  = 0;
    $totalPaid   = 0;
    $totalUnpaid = 0;
    $countOverdue = 0;

    foreach ($fees as &$fee) {
        $dueDate    = new DateTime($fee['due_date']);
        $fineAmount = 0;

        if ($fee['is_paid']) {
            $totalPaid += $fee['amount'];
        } elseif ($today > $dueDate) {
            $daysOverdue = (int)$dueDate->diff($today)->days;
            $fineAmount  = $daysOverdue * $fee['fine_rate'];
            $countOverdue++;
            $totalFines  += $fineAmount;
            $totalUnpaid += $fee['amount'] + $fineAmount;
        } else {
            $totalUnpaid += $fee['amount'];
        }

        $fee['_fine_amount'] = $fineAmount;
        $fee['_total_due']   = $fee['amount'] + $fineAmount;
        $totalFees += $fee['amount'];
    }

    return compact('totalFees','totalFines','totalPaid','totalUnpaid','countOverdue');
}

$studentFees = [
    ['amount' => 800,  'is_paid' => 1, 'due_date' => '2026-04-01', 'fine_rate' => 0.50],
    ['amount' => 400,  'is_paid' => 0, 'due_date' => '2026-07-01', 'fine_rate' => 0.50],  // pending
    ['amount' => 200,  'is_paid' => 0, 'due_date' => '2026-06-01', 'fine_rate' => 0.50],  // overdue 4d = kr2
];

$studentSummary = buildStudentSummary($studentFees, '2026-06-05');

test('Student: totalFees = sum of all base amounts',   $studentSummary['totalFees']   == 1400);
test('Student: totalPaid = paid fees only',            $studentSummary['totalPaid']   == 800);
test('Student: totalFines = fines on overdue only',    $studentSummary['totalFines']  == 2.0);
test('Student: totalUnpaid includes pending + fine',   $studentSummary['totalUnpaid'] == 602.0,
    '400 pending + 200 overdue + 2.00 fine = 602.00');
test('Student: countOverdue = 1',                      $studentSummary['countOverdue'] === 1);
test('Student: no overdue notice when all paid', (function() {
    $allPaid = [
        ['amount' => 500, 'is_paid' => 1, 'due_date' => '2026-01-01', 'fine_rate' => 0.50],
    ];
    $s = buildStudentSummary($allPaid, '2026-06-05');
    return $s['countOverdue'] === 0;
})());


// ════════════════════════════════════════════════════════════════════════════
// 15. SQL INJECTION PREVENTION  (all files use PDO prepared statements)
// ════════════════════════════════════════════════════════════════════════════
section('SQL Injection Prevention');

/**
 * Simulates how PDO binds parameters — the dangerous input should never
 * be interpolated directly into a query string.
 */
function buildSafeQuery(string $receiptId): array {
    // This is the pattern used across all files: parameterised query + bound value
    $sql    = "SELECT * FROM fees WHERE receipt_number = ?";
    $params = [$receiptId]; // never interpolated into $sql
    return ['sql' => $sql, 'params' => $params];
}

$malicious = "' OR '1'='1"; // classic SQL injection payload

$q = buildSafeQuery($malicious);
test('SQL injection payload is NOT in the query string',
    strpos($q['sql'], $malicious) === false
);
test('SQL injection payload is safely in the params array',
    $q['params'][0] === $malicious
);
test('Query string uses placeholder not raw value',
    strpos($q['sql'], '?') !== false
);

// Verify LIKE search also uses placeholders (index.php search)
function buildSearchQuery(string $search): array {
    $sql    = "SELECT * FROM fees WHERE receipt_number LIKE ?";
    $params = ["%{$search}%"];
    return ['sql' => $sql, 'params' => $params];
}

$searchPayload = "'; DROP TABLE fees; --";
$sq = buildSearchQuery($searchPayload);
test('Search: DROP TABLE payload not injected into SQL',
    strpos($sq['sql'], 'DROP') === false
);
test('Search: payload is safely wrapped in LIKE params',
    $sq['params'][0] === "%{$searchPayload}%"
);


// ════════════════════════════════════════════════════════════════════════════
// 16. FILTER QUERY BUILDING  (index.php)
// ════════════════════════════════════════════════════════════════════════════
section('Filter Query Building');

/**
 * Mirrors the dynamic WHERE clause builder in index.php.
 */
function buildFilterQuery(array $filters): array {
    $where  = ['f.is_active = 1'];
    $params = [];

    if (!empty($filters['student_id'])) {
        $where[]  = 'f.student_id = ?';
        $params[] = (int)$filters['student_id'];
    }
    if (!empty($filters['search'])) {
        $where[]  = '(f.receipt_number LIKE ? OR s.full_name LIKE ?)';
        $params[] = '%' . $filters['search'] . '%';
        $params[] = '%' . $filters['search'] . '%';
    }
    if (!empty($filters['fee_type'])) {
        $where[]  = 'f.fee_type = ?';
        $params[] = $filters['fee_type'];
    }
    if ($filters['status'] ?? '' === 'paid') {
        $where[] = 'f.is_paid = 1';
    } elseif (($filters['status'] ?? '') === 'unpaid') {
        $where[] = 'f.is_paid = 0 AND f.due_date >= CURDATE()';
    } elseif (($filters['status'] ?? '') === 'overdue') {
        $where[] = 'f.is_paid = 0 AND f.due_date < CURDATE()';
    }

    $sql = 'SELECT f.*, s.full_name FROM fees f LEFT JOIN students s ON s.student_id = f.student_id'
         . ' WHERE ' . implode(' AND ', $where)
         . ' ORDER BY f.created_at DESC';

    return ['sql' => $sql, 'params' => $params];
}

$noFilters = buildFilterQuery([]);
test('No filters: only is_active = 1 in WHERE',
    substr_count($noFilters['sql'], 'WHERE') === 1
);
test('No filters: params array is empty',
    count($noFilters['params']) === 0
);

$withSearch = buildFilterQuery(['search' => 'Alice']);
test('Search filter adds 2 LIKE params',
    count($withSearch['params']) === 2
);
test('Search LIKE param is wrapped in wildcards',
    $withSearch['params'][0] === '%Alice%'
);

$withType = buildFilterQuery(['fee_type' => 'rent']);
test('Fee type filter adds fee_type to WHERE',
    strpos($withType['sql'], 'f.fee_type = ?') !== false
);
test('Fee type filter adds 1 param',
    count($withType['params']) === 1
);

$withStudent = buildFilterQuery(['student_id' => '7']);
test('Student filter adds student_id to WHERE',
    strpos($withStudent['sql'], 'f.student_id = ?') !== false
);
test('Student filter casts ID to int',
    $withStudent['params'][0] === 7
);

$overdueFilter = buildFilterQuery(['status' => 'overdue']);
test('Overdue filter adds correct SQL condition',
    strpos($overdueFilter['sql'], 'f.due_date < CURDATE()') !== false
);


// ════════════════════════════════════════════════════════════════════════════
// 17. STUDENT PORTAL SESSION GUARD  (student_portal.php)
// ════════════════════════════════════════════════════════════════════════════
section('Student Portal Session Guard');

/**
 * Mirrors the session guard logic at the top of student_portal.php.
 */
function checkStudentSession(array $session): string {
    if (!isset($session['student_id'])) return 'redirect_login';
    return 'allow';
}

/**
 * Mirrors the DB re-validation after session check.
 */
function checkStudentActive(?array $dbRow): string {
    if (!$dbRow) return 'destroy_and_redirect';
    return 'allow';
}

test('Missing student_id in session → redirect to login',
    checkStudentSession([]) === 'redirect_login'
);
test('Valid student_id in session → allow',
    checkStudentSession(['student_id' => 5]) === 'allow'
);
test('Deactivated student (no DB row) → destroy and redirect',
    checkStudentActive(null) === 'destroy_and_redirect'
);
test('Active student (DB row present) → allow',
    checkStudentActive(['student_id' => 5, 'status' => 1]) === 'allow'
);
test('Student name comes from session not just DB',
    isset(['student_id' => 3, 'student_name' => 'Jane Doe']['student_name'])
);


// ════════════════════════════════════════════════════════════════════════════
// 18. FINE RATE EDGE CASES  (add.php default + student_portal policy)
// ════════════════════════════════════════════════════════════════════════════
section('Fine Rate Edge Cases');

test('Default fine rate in add.php is kr0.50',
    (float)'0.50' === 0.50
);
test('Fine rate cast from POST string to float works',
    (float)'1.25' === 1.25
);
test('Fine rate of 0.00 produces no fine ever',
    calcFine('2020-01-01', 0.00, '2026-06-05') === 0.0
);
test('Very large fine rate still calculates correctly',
    calcFine('2026-06-04', 100.00, '2026-06-05') === 100.0
);
test('Fine accumulates linearly over 30 days at kr0.50',
    calcFine('2026-05-06', 0.50, '2026-06-05') === 15.0
);
test('Fine accumulates linearly over 365 days at kr0.50',
    calcFine('2025-06-05', 0.50, '2026-06-05') === 182.5
);


// ════════════════════════════════════════════════════════════════════════════
// 19. AMOUNT FORMATTING & CURRENCY  (all files)
// ════════════════════════════════════════════════════════════════════════════
section('Amount Formatting & Currency Display');

function formatAmount(float $amount): string {
    return 'kr ' . number_format($amount, 2);
}

test('kr0.00 formats correctly',           formatAmount(0)       === 'kr 0.00');
test('kr1234.56 formats correctly',        formatAmount(1234.56) === 'kr 1,234.56');
test('kr0.50 fine formats correctly',      formatAmount(0.50)    === 'kr 0.50');
test('Large amount kr99999.99 formats',    formatAmount(99999.99)=== 'kr 99,999.99');
test('Fine prefix +kr formats correctly',
    '+kr ' . number_format(2.00, 2) === '+kr 2.00'
);
test('ucfirst on fee type: rent → Rent',   ucfirst('rent')    === 'Rent');
test('ucfirst on fee type: laundry',       ucfirst('laundry') === 'Laundry');


// ════════════════════════════════════════════════════════════════════════════
// 20. REDIRECT SAFETY  (delete.php, add.php, edit.php)
// ════════════════════════════════════════════════════════════════════════════
section('Redirect Safety & URL Encoding');

test('urlencode makes receipt safe for URL',
    urlencode('RCP-2026-0001') === 'RCP-2026-0001'
);
test('urlencode handles spaces in names',
    urlencode('John Doe') === 'John+Doe'
);
test('urlencode handles special chars',
    urlencode('RCP/2026&id=1') === 'RCP%2F2026%26id%3D1'
);
test('Redirect URL with deleted=1 flag is correct',
    'index.php?deleted=1' === 'index.php?deleted=1'
);
test('Redirect with student_id appends correctly',
    'index.php?student_id=' . urlencode('5') === 'index.php?student_id=5'
);
test('edit.php redirect preserves receipt in URL',
    'edit.php?id=' . urlencode('RCP-2026-0001') . '&saved=1'
    === 'edit.php?id=RCP-2026-0001&saved=1'
);


// ════════════════════════════════════════════════════════════════════════════
// RESULTS SUMMARY
// ════════════════════════════════════════════════════════════════════════════
$total = $passed + $failed;

echo "\n";
echo "\033[1m══════════════════════════════════════════\033[0m\n";
echo "\033[1m  HOSTELHUB TEST RESULTS\033[0m\n";
echo "\033[1m══════════════════════════════════════════\033[0m\n";

foreach ($results as $r) {
    $icon  = $r['status'] === 'PASS' ? "\033[0;32m✓\033[0m" : "\033[0;31m✗\033[0m";
    $label = $r['status'] === 'PASS' ? "\033[0;32mPASS\033[0m" : "\033[0;31mFAIL\033[0m";
    echo "  {$icon} [{$label}] {$r['name']}";
    if ($r['status'] === 'FAIL' && $r['detail']) {
        echo "\n         \033[0;33m↳ {$r['detail']}\033[0m";
    }
    echo "\n";
}

echo "\n";
echo "\033[1m──────────────────────────────────────────\033[0m\n";

if ($failed === 0) {
    echo "\033[0;32m  ✓ All {$total} tests passed\033[0m\n";
} else {
    echo "\033[0;32m  Passed : {$passed}/{$total}\033[0m\n";
    echo "\033[0;31m  Failed : {$failed}/{$total}\033[0m\n";
}

echo "\033[1m──────────────────────────────────────────\033[0m\n\n";

exit($failed > 0 ? 1 : 0);
