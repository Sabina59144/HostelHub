<?php


/* ── Auth & DB ──────────────────────────────────────── */
require_once __DIR__ . '/../includes/session.php';
requireLogin();
require_once __DIR__ . '/../includes/db.php';

/**
 * Toggle payment status of a fee record
 * Switches between paid/unpaid state and updates timestamp
 */
if (isset($_GET['toggle_pay'])) {
    $id   = $_GET['toggle_pay'];
    $stmt = $db->prepare("SELECT is_paid FROM fees WHERE receipt_number = ?");
    $stmt->execute([$id]);
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        if ($row['is_paid']) {
            // Mark as unpaid - clear paid_at timestamp
            $db->prepare("UPDATE fees SET is_paid = 0, paid_at = NULL WHERE receipt_number = ?")->execute([$id]);
        } else {
            // Mark as paid - set current timestamp
            $db->prepare("UPDATE fees SET is_paid = 1, paid_at = NOW() WHERE receipt_number = ?")->execute([$id]);
        }
    }
    header("Location: index.php" . (isset($_SERVER['QUERY_STRING']) ? '?' . preg_replace('/toggle_pay=[^&]*&?/', '', $_SERVER['QUERY_STRING']) : ""));
    exit;
}

$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$today   = new DateTime();

/* ── FILTER VARIABLES ───────────────────────────────– */
$search          = trim($_GET['search'] ?? '');           // Search query for receipt/student
$filterStudentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;  // Filter by student ID
$filterStatus    = $_GET['filter']    ?? '';              // paid | unpaid | overdue
$filterType      = $_GET['fee_type']  ?? '';              // rent | deposit | utility | etc
$filterStudentName = '';

// Get student name if filtering by ID
if ($filterStudentId) {
    $sRow = $db->prepare("SELECT full_name FROM students WHERE student_id = ?");
    $sRow->execute([$filterStudentId]);
    $filterStudentName = $sRow->fetchColumn() ?: '';
}

/* ── BUILD DYNAMIC QUERY ────────────────────────────– */
// Start with base condition
$where  = ["f.is_active = 1"];
$params = [];

// Add student filter
if ($filterStudentId) {
    $where[]  = "f.student_id = ?";
    $params[] = $filterStudentId;
}

// Add search filter (receipt number, student name, or student ID)
if (!empty($search)) {
    $where[]  = "(f.receipt_number LIKE ? OR s.full_name LIKE ? OR s.student_number LIKE ?)";
    $params[] = "%$search%"; 
    $params[] = "%$search%"; 
    $params[] = "%$search%";
}

// Add fee type filter
if ($filterType) {
    $where[]  = "f.fee_type = ?";
    $params[] = $filterType;
}

// Add payment status filter
if ($filterStatus === 'paid') {
    $where[] = "f.is_paid = 1";
} elseif ($filterStatus === 'unpaid') {
    // Unpaid AND due date is in future
    $where[] = "f.is_paid = 0 AND f.due_date >= CURDATE()";
} elseif ($filterStatus === 'overdue') {
    // Unpaid AND due date is in past
    $where[] = "f.is_paid = 0 AND f.due_date < CURDATE()";
}

// Execute query
$sql  = "SELECT f.*, s.full_name, s.student_number FROM fees f LEFT JOIN students s ON s.student_id = f.student_id";
$sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY f.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$fees = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ── CALCULATE SUMMARY STATISTICS ───────────────────– */
$cnt = [
    'total' => count($fees),           // Total records
    'paid' => 0,                       // Count of paid fees
    'unpaid' => 0,                     // Count of pending unpaid fees
    'overdue' => 0,                    // Count of overdue fees
    'total_amt' => 0,                  // Sum of all amounts
    'paid_amt' => 0                    // Sum of paid amounts
];

// Loop through fees to calculate statistics
foreach ($fees as $f) {
    $due = new DateTime($f['due_date']);
    $cnt['total_amt'] += $f['amount'];
    
    if ($f['is_paid']) {
        $cnt['paid']++;
        $cnt['paid_amt'] += $f['amount'];
    } elseif ($today > $due) {
        $cnt['overdue']++;
    } else {
        $cnt['unpaid']++;
    }
}

// Fine calculation constants
$FINE_RATE = 0.50; // £0.50 per day
$FINE_CAP = 15.00; // Maximum fine of £15.00
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Fee Records — HostelHub</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Mono:wght@400;500&family=Outfit:wght@400;500;600&display=swap" rel="stylesheet">
<style>
:root{--bg:#0e1117;--surface:#161b27;--card:#1c2235;--border:#2a3148;--accent:#4f7aff;--success:#22d3a5;--warning:#fbbf24;--danger:#f87171;--text:#e8eaf6;--muted:#8892b0;--faint:#3a4260;}
*{box-sizing:border-box;margin:0;padding:0;}
body{background:var(--bg);color:var(--text);font-family:'Outfit',sans-serif;min-height:100vh;}
.topnav{background:var(--surface);border-bottom:1px solid var(--border);padding:0 32px;height:60px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;}
.brand{font-family:'Syne',sans-serif;font-weight:800;font-size:20px;color:var(--text);display:flex;align-items:center;gap:8px;}
.brand span{color:var(--accent);}
.nav-links{display:flex;gap:4px;}
.nav-links a{padding:6px 14px;border-radius:8px;font-size:13px;font-weight:500;color:var(--muted);text-decoration:none;transition:all 0.15s;}
.nav-links a:hover{background:var(--card);color:var(--text);}
.nav-links a.active{background:var(--accent);color:#fff;}
.page{max-width:1280px;margin:0 auto;padding:28px 32px;}
.page-hdr{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:20px;flex-wrap:wrap;}
.page-hdr h2{font-family:'Syne',sans-serif;font-size:24px;font-weight:800;margin-bottom:4px;}
.page-hdr p{color:var(--muted);font-size:13px;}
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:9px;font-size:13px;font-weight:600;text-decoration:none;transition:all 0.15s;border:1px solid transparent;cursor:pointer;font-family:'Outfit',sans-serif;}
.btn-primary{background:var(--accent);color:#fff;}
.btn-primary:hover{background:#3d68e8;}
.btn-ghost{background:var(--card);color:var(--text);border-color:var(--border);}
.btn-ghost:hover{border-color:var(--accent);color:var(--accent);}

/* FILTER ROW */
.filter-row{display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;align-items:center;}
.filter-row form{display:flex;gap:8px;flex:1;min-width:280px;align-items:center;}
.filter-row input{flex:1;padding:9px 14px;border:1px solid var(--border);border-radius:9px;font-size:13px;background:var(--card);color:var(--text);font-family:'Outfit',sans-serif;outline:none;transition:border-color 0.15s;}
.filter-row input:focus{border-color:var(--accent);}
.filter-row select{padding:9px 12px;border:1px solid var(--border);border-radius:9px;font-size:13px;background:var(--card);color:var(--text);font-family:'Outfit',sans-serif;outline:none;cursor:pointer;}
.filter-row select:focus{border-color:var(--accent);}

/* STAT STRIP */
.stat-strip{display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;}
.stat-chip{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:10px 16px;font-size:12px;color:var(--muted);}
.stat-chip strong{display:block;font-family:'Syne',sans-serif;font-size:18px;font-weight:700;color:var(--text);}
.stat-chip.s-green strong{color:var(--success);}
.stat-chip.s-amber strong{color:var(--warning);}
.stat-chip.s-red strong{color:var(--danger);}

/* TABLE */
.table-wrap{background:var(--card);border:1px solid var(--border);border-radius:14px;overflow:hidden;}
.table-wrap table{width:100%;border-collapse:collapse;}
.table-wrap thead th{background:var(--surface);padding:11px 15px;font-size:10px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--muted);text-align:left;border-bottom:1px solid var(--border);white-space:nowrap;}
.table-wrap tbody td{padding:12px 15px;font-size:13px;border-bottom:1px solid rgba(42,49,72,0.5);vertical-align:middle;}
.table-wrap tbody tr:last-child td{border-bottom:none;}
.table-wrap tbody tr:hover td{background:rgba(79,122,255,0.04);}
.table-wrap tbody tr.row-late{background:rgba(248,113,113,0.04);}
.badge{display:inline-block;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700;}
.b-paid{background:rgba(34,211,165,0.15);color:var(--success);}
.b-unpaid{background:rgba(251,191,36,0.15);color:var(--warning);}
.b-overdue{background:rgba(248,113,113,0.15);color:var(--danger);}
.type-pill{background:rgba(79,122,255,0.15);color:#93b4ff;padding:3px 9px;border-radius:6px;font-size:11px;font-weight:600;text-transform:capitalize;}
.rcpt{font-family:'DM Mono',monospace;font-size:11px;color:var(--muted);}
.mono{font-family:'DM Mono',monospace;font-size:12px;}
.fine-col{color:var(--danger);font-family:'DM Mono',monospace;font-size:12px;}
.total-col{font-family:'DM Mono',monospace;font-size:13px;font-weight:600;color:var(--text);}

/* ACTION LINKS */
.act-link{padding:4px 9px;border-radius:6px;font-size:11px;font-weight:600;text-decoration:none;transition:all 0.15s;display:inline-block;}
.act-edit{background:rgba(79,122,255,0.15);color:var(--accent);}
.act-edit:hover{background:rgba(79,122,255,0.25);}
.act-pay{background:rgba(34,211,165,0.15);color:var(--success);}
.act-pay:hover{background:rgba(34,211,165,0.25);}
.act-unpay{background:rgba(248,113,113,0.15);color:var(--danger);}
.act-unpay:hover{background:rgba(248,113,113,0.25);}
.act-delete{background:rgba(248,113,113,0.15);color:var(--danger);}
.act-delete:hover{background:rgba(248,113,113,0.25);}

/* EMPTY STATE */
.empty-state{padding:48px 32px;text-align:center;color:var(--muted);}
.empty-state .icon{font-size:2.5rem;margin-bottom:12px;display:block;}
.empty-state h3{color:var(--text);margin-bottom:6px;}
.empty-state p{font-size:13px;}
</style>
</head>
<body>
<nav class="topnav">
    <div class="brand">🏠 Hostel<span>Hub</span></div>
    <div class="nav-links">
        <a href="dashboard.php">Dashboard</a>
        <a href="index.php" class="active">Fee Records</a>
        <?php if ($isAdmin): ?><a href="add.php">Add Fee</a><?php endif; ?>
        <a href="report.php">Report</a>
        <a href="../students/index.php">Students</a>
    </div>
    <div style="font-size:13px;color:var(--muted);">
        <strong style="color:var(--text);"><?= htmlspecialchars($_SESSION['full_name'] ?? '') ?></strong>
        &nbsp;<a href="../logout.php" style="color:var(--danger);font-size:12px;text-decoration:none;">Sign out</a>
    </div>
</nav>

<div class="page">

    <?php if ($filterStudentId && $filterStudentName): ?>
    <div style="margin-bottom:14px;"><a href="../students/view.php?id=<?= $filterStudentId ?>" style="color:var(--muted);font-size:13px;font-weight:600;text-decoration:none;">← Back to <?= htmlspecialchars($filterStudentName) ?></a></div>
    <?php endif; ?>

    <div class="page-hdr">
        <div>
            <h2><?= $filterStudentName ? 'Fees — ' . htmlspecialchars($filterStudentName) : 'Fee Records' ?></h2>
            <p>Manage hostel fees </p>
        </div>
        <?php if ($isAdmin): ?>
        <a href="add.php<?= $filterStudentId ? '?student_id='.$filterStudentId : '' ?>" class="btn btn-primary">＋ Add Fee</a>
        <?php endif; ?>
    </div>

    <!-- STAT STRIP: Summary Statistics -->
    <div class="stat-strip">
        <div class="stat-chip"><strong><?= $cnt['total'] ?></strong>Total Records</div>
        <div class="stat-chip s-green"><strong><?= $cnt['paid'] ?></strong>Paid</div>
        <div class="stat-chip s-amber"><strong><?= $cnt['unpaid'] ?></strong>Pending</div>
        <div class="stat-chip s-red"><strong><?= $cnt['overdue'] ?></strong>Overdue</div>
        <div class="stat-chip"><strong style="color:var(--accent);">£<?= number_format($cnt['paid_amt'],0) ?></strong>Collected</div>
        <div class="stat-chip"><strong style="color:var(--warning);">£<?= number_format($cnt['total_amt']-$cnt['paid_amt'],0) ?></strong>Outstanding</div>
    </div>

    <!-- FILTER ROW: Search and Filter Controls -->
    <div class="filter-row">
        <form method="GET">
            <?php if ($filterStudentId): ?><input type="hidden" name="student_id" value="<?= $filterStudentId ?>"><?php endif; ?>
            <input type="text" name="search" placeholder="Search by receipt, student name, student number…" value="<?= htmlspecialchars($search) ?>">
            
            <!-- Status Filter -->
            <select name="filter" onchange="this.form.submit()">
                <option value="">All Status</option>
                <option value="paid"    <?= $filterStatus==='paid'    ? 'selected':'' ?>>✅ Paid</option>
                <option value="unpaid"  <?= $filterStatus==='unpaid'  ? 'selected':'' ?>>⏳ Unpaid</option>
                <option value="overdue" <?= $filterStatus==='overdue' ? 'selected':'' ?>>⚠ Overdue</option>
            </select>
            
            <!-- Fee Type Filter -->
            <select name="fee_type" onchange="this.form.submit()">
                <option value="">All Types</option>
                <?php foreach (['rent','deposit','utility','fine','laundry','other'] as $t): ?>
                <option value="<?= $t ?>" <?= $filterType===$t ? 'selected':'' ?>><?= ucfirst($t) ?></option>
                <?php endforeach; ?>
            </select>
            
            <button type="submit" class="btn btn-primary">🔍 Search</button>
            <?php if ($search || $filterStatus || $filterType): ?>
            <a href="index.php<?= $filterStudentId ? '?student_id='.$filterStudentId : '' ?>" class="btn btn-ghost">✕ Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- TABLE: Fee Records List -->
    <div class="table-wrap">
        <?php if (empty($fees)): ?>
        <!-- Empty State: No fees found -->
        <div class="empty-state"><span class="icon">💷</span><h3>No fee records found</h3><p>Try a different search or filter, or add a new fee.</p></div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Receipt #</th>
                    <th>Student</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Late Fine</th>
                    <th>Total Due</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th>Paid On</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($fees as $f):
                // Calculate fine for overdue fees
                $due       = new DateTime($f['due_date']);
                $fine      = 0;
                $status    = 'unpaid';
                $rowClass  = '';
                
                if ($f['is_paid']) {
                    // Fee is paid
                    $status = 'paid';
                } elseif ($today > $due) {
                    // Fee is overdue - calculate fine
                    $days   = $due->diff($today)->days;
                    $fine   = min($days * $FINE_RATE, $FINE_CAP);
                    $status = 'overdue'; 
                    $rowClass = 'row-late';
                }
                
                // Calculate total due (amount + fine)
                $total    = $f['amount'] + $fine;
                
                // Status labels and badge classes
                $labels   = ['paid'=>'✅ Paid','unpaid'=>'⏳ Unpaid','overdue'=>'⚠ Overdue'];
                $bclass   = ['paid'=>'b-paid','unpaid'=>'b-unpaid','overdue'=>'b-overdue'];
            ?>
            <tr class="<?= $rowClass ?>">
                <td><span class="rcpt"><?= htmlspecialchars($f['receipt_number']) ?></span></td>
                <td>
                    <div style="font-size:13px;"><?= htmlspecialchars($f['full_name'] ?? '—') ?></div>
                    <?php if (!empty($f['student_number'])): ?><div class="rcpt"><?= htmlspecialchars($f['student_number']) ?></div><?php endif; ?>
                </td>
                <td><span class="type-pill"><?= ucfirst($f['fee_type']) ?></span></td>
                <td class="mono">£<?= number_format($f['amount'], 2) ?></td>
                <td class="fine-col"><?= $fine > 0 ? '+£'.number_format($fine,2) : '<span style="color:var(--faint);">—</span>' ?></td>
                <td class="total-col">£<?= number_format($total, 2) ?></td>
                <td style="font-size:12px;color:var(--muted);">
                    <?= $due->format('d M Y') ?>
                    <?php if ($status === 'overdue'): ?>
                    <div style="font-size:10px;color:var(--danger);"><?= $due->diff($today)->days ?>d late</div>
                    <?php endif; ?>
                </td>
                <td><span class="badge <?= $bclass[$status] ?>"><?= $labels[$status] ?></span></td>
                <td style="font-size:11px;color:var(--muted);">
                    <?= $f['paid_at'] ? (new DateTime($f['paid_at']))->format('d M Y') : '—' ?>
                </td>
                <td style="white-space:nowrap;">
                    <?php if ($isAdmin): ?>
                    <a href="edit.php?id=<?= urlencode($f['receipt_number']) ?>" class="act-link act-edit">Edit</a>
                    <a href="delete.php?id=<?= urlencode($f['receipt_number']) ?>" class="act-link act-delete">Delete</a>
                    <?php endif; ?>
                    <!-- Toggle payment status -->
                    <a href="?toggle_pay=<?= urlencode($f['receipt_number']) ?><?= $filterStudentId ? '&student_id='.$filterStudentId : '' ?>"
                       onclick="return confirm('<?= $f['is_paid'] ? 'Mark as unpaid?' : 'Mark as paid?' ?>')"
                       class="act-link <?= $f['is_paid'] ? 'act-unpay' : 'act-pay' ?>">
                       <?= $f['is_paid'] ? 'Unmark' : 'Mark Paid' ?>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    
    <!-- Fine Policy Note -->
    <p style="margin-top:12px;font-size:11px;color:var(--faint);text-align:center;">
        Late fine policy: £0.50/day · max £15.00 per fee · calculated automatically on overdue fees.
    </p>
</div>
</body>
</html>
