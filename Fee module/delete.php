<?php
require_once __DIR__ . '/../includes/session.php';
requireRole('admin');
require_once __DIR__ . '/../includes/db.php';

if (empty($_GET['id'])) { header("Location: index.php"); exit; }

$id   = $_GET['id'];
$stmt = $db->prepare("
    SELECT f.*, s.full_name FROM fees f
    LEFT JOIN students s ON s.student_id = f.student_id
    WHERE f.receipt_number = ?
");
$stmt->execute([$id]);
$fee = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$fee) { header("Location: index.php"); exit; }

if (isset($_POST['confirm_delete'])) {
    // Soft delete (set is_active=0) to preserve records
    $db->prepare("UPDATE fees SET is_active = 0, deleted_at = NOW(), deleted_reason = ? WHERE receipt_number = ?")
       ->execute([$_POST['reason'] ?? 'Admin deleted', $id]);
    header("Location: index.php?deleted=1");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Delete Fee — HostelHub</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Mono:wght@400&family=Outfit:wght@400;500;600&display=swap" rel="stylesheet">
<style>
:root{--bg:#0e1117;--surface:#161b27;--card:#1c2235;--border:#2a3148;--accent:#4f7aff;--danger:#f87171;--text:#e8eaf6;--muted:#8892b0;}
*{box-sizing:border-box;margin:0;padding:0;}
body{background:var(--bg);color:var(--text);font-family:'Outfit',sans-serif;min-height:100vh;}
.topnav{background:var(--surface);border-bottom:1px solid var(--border);padding:0 32px;height:60px;display:flex;align-items:center;justify-content:space-between;}
.brand{font-family:'Syne',sans-serif;font-weight:800;font-size:20px;color:var(--text);}
.brand span{color:var(--accent);}
.page{max-width:540px;margin:0 auto;padding:36px 24px;}
.page-hdr{margin-bottom:24px;}
.page-hdr h2{font-family:'Syne',sans-serif;font-size:26px;font-weight:800;color:var(--danger);margin-bottom:4px;}
.page-hdr p{color:var(--muted);font-size:13px;}
.form-card{background:var(--card);border:1px solid rgba(248,113,113,0.3);border-radius:16px;padding:28px 30px;}
.warn{background:rgba(248,113,113,0.08);border:1px solid rgba(248,113,113,0.25);border-radius:10px;padding:14px 18px;margin-bottom:22px;font-size:13px;color:var(--danger);}
.warn strong{display:block;margin-bottom:4px;font-size:14px;}
.detail-row{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid rgba(42,49,72,0.5);font-size:13px;}
.detail-row:last-of-type{border-bottom:none;}
.detail-row span:first-child{color:var(--muted);font-weight:600;}
.form-group{margin:20px 0 0;}
.form-group label{display:block;font-size:11px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--muted);margin-bottom:6px;}
.form-group input{width:100%;padding:10px 14px;border:1px solid var(--border);border-radius:9px;font-size:13px;font-family:'Outfit',sans-serif;background:var(--surface);color:var(--text);outline:none;}
.form-group input:focus{border-color:var(--danger);box-shadow:0 0 0 3px rgba(248,113,113,0.15);}
.btn-delete{width:100%;padding:13px;background:var(--danger);color:#fff;border:none;border-radius:11px;font-size:15px;font-weight:700;cursor:pointer;font-family:'Outfit',sans-serif;transition:background 0.2s;margin-top:16px;}
.btn-delete:hover{background:#e85252;}
.back-link{display:block;text-align:center;margin-top:14px;font-size:13px;color:var(--muted);text-decoration:none;}
.back-link:hover{color:var(--accent);}
</style>
</head>
<body>
<nav class="topnav">
    <div class="brand">🏠 Hostel<span>Hub</span></div>
    <a href="index.php" style="color:var(--muted);font-size:13px;text-decoration:none;">← Fee Records</a>
</nav>
<div class="page">
    <div class="page-hdr">
        <h2>Delete Fee Record</h2>
        <p>This will archive the record and remove it from active views.</p>
    </div>
    <div class="form-card">
        <div class="warn">
            <strong>⚠️ Are you sure?</strong>
            This fee record will be archived and no longer visible in the fee list. It can be restored by an administrator from the database.
        </div>
        <div class="detail-row"><span>Receipt</span><span style="font-family:'DM Mono',monospace;font-size:12px;"><?= htmlspecialchars($fee['receipt_number']) ?></span></div>
        <div class="detail-row"><span>Student</span><span><?= htmlspecialchars($fee['full_name'] ?? '—') ?></span></div>
        <div class="detail-row"><span>Fee Type</span><span><?= ucfirst(htmlspecialchars($fee['fee_type'])) ?></span></div>
        <div class="detail-row"><span>Amount</span><span style="font-family:'DM Mono',monospace;">£<?= number_format($fee['amount'], 2) ?></span></div>
        <div class="detail-row"><span>Due Date</span><span><?= htmlspecialchars($fee['due_date']) ?></span></div>
        <div class="detail-row"><span>Status</span>
            <span><?= $fee['is_paid'] ? '✅ Paid' : '⏳ Unpaid' ?></span>
        </div>
        <form method="POST">
            <div class="form-group">
                <label>Reason for deletion (optional)</label>
                <input type="text" name="reason" placeholder="e.g. Entered in error, duplicate…">
            </div>
            <button type="submit" name="confirm_delete" class="btn-delete"
                    onclick="return confirm('Permanently archive this fee record?')">
                🗑 Confirm Delete
            </button>
        </form>
        <a href="index.php" class="back-link">← Cancel, go back</a>
    </div>
</div>
</body>
</html>
