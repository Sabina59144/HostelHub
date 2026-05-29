<?php
// Load the session manager and enforce that the user is logged in
require_once __DIR__ . '/../includes/session.php';

// Restrict this page to admin-only access; redirects or exits if not admin
requireRole('admin');

// Load the database connection (PDO instance stored in $db)
require_once __DIR__ . '/../includes/db.php';

// If no receipt ID was provided in the URL, redirect to fee list and stop execution
if (empty($_GET['id'])) { header("Location: index.php"); exit; }

// Sanitize and store the receipt number from the URL query string
$id   = $_GET['id'];

// Prepare a SQL query to fetch the fee record and its associated student name
$stmt = $db->prepare("
    SELECT f.*, s.full_name FROM fees f
    LEFT JOIN students s ON s.student_id = f.student_id
    WHERE f.receipt_number = ?
");

// Execute the query with the provided receipt number
$stmt->execute([$id]);

// Fetch the result as an associative array
$fee = $stmt->fetch(PDO::FETCH_ASSOC);

// If no record was found (invalid ID), redirect back to the fee list
if (!$fee) { header("Location: index.php"); exit; }

// Handle the form submission when the admin clicks "Confirm Delete"
if (isset($_POST['confirm_delete'])) {
    // Soft delete: set is_active=0 and record the deletion timestamp and reason
    // This preserves the record in the database for audit/restore purposes
    $db->prepare("UPDATE fees SET is_active = 0, deleted_at = NOW(), deleted_reason = ? WHERE receipt_number = ?")
       ->execute([$_POST['reason'] ?? 'Admin deleted', $id]); // Use provided reason or a default message

    // Redirect to the fee list with a 'deleted=1' flag to show a success message
    header("Location: index.php?deleted=1");
    exit; // Always exit after a redirect to prevent further code execution
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<!-- Ensure proper scaling on mobile devices -->
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Delete Fee — HostelHub</title>
<!-- Preconnect to Google Fonts for faster font loading -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
:root{--bg:#f0f4f8;--surface:#f8fafc;--card:#fff;--border:#e8edf3;--accent:#1a56db;--danger:#dc2626;--text:#0f1923;--muted:#64748b;}

*{box-sizing:border-box;margin:0;padding:0;}

body{background:var(--bg);color:var(--text);font-family:'DM Sans',sans-serif;min-height:100vh;}

.topnav{background:#fff;border-bottom:1px solid var(--border);padding:0 32px;height:60px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 1px 4px rgba(0,0,0,0.06);}

.brand{font-family:'Playfair Display',serif;font-weight:700;font-size:20px;color:var(--text);}
.brand span{color:var(--accent);}

.page{max-width:540px;margin:0 auto;padding:36px 24px;}
.page-hdr{margin-bottom:24px;}
.page-hdr h2{font-family:'Playfair Display',serif;font-size:26px;font-weight:700;color:var(--danger);margin-bottom:4px;}
.page-hdr p{color:var(--muted);font-size:13px;}

.form-card{background:#fff;border:1px solid #fecaca;border-radius:16px;padding:28px 30px;box-shadow:0 2px 12px rgba(0,0,0,0.06);}

.warn{background:#fff1f2;border:1px solid #fecaca;border-radius:10px;padding:14px 18px;margin-bottom:22px;font-size:13px;color:var(--danger);}
.warn strong{display:block;margin-bottom:4px;font-size:14px;}

.detail-row{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border);font-size:13px;}
.detail-row:last-of-type{border-bottom:none;}
.detail-row span:first-child{color:var(--muted);font-weight:600;}

.form-group{margin:20px 0 0;}
.form-group label{display:block;font-size:11px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--muted);margin-bottom:6px;}
.form-group input{width:100%;padding:10px 14px;border:1px solid var(--border);border-radius:9px;font-size:13px;font-family:'DM Sans',sans-serif;background:var(--surface);color:var(--text);outline:none;}
.form-group input:focus{border-color:var(--danger);box-shadow:0 0 0 3px rgba(220,38,38,0.1);}

.btn-delete{width:100%;padding:13px;background:var(--danger);color:#fff;border:none;border-radius:11px;font-size:15px;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;transition:background 0.2s;margin-top:16px;}
.btn-delete:hover{background:#b91c1c;}

.back-link{display:block;text-align:center;margin-top:14px;font-size:13px;color:var(--muted);text-decoration:none;}
.back-link:hover{color:var(--accent);}
</style>
</head>
<body>

<!-- Top navigation bar -->
<nav class="topnav">
    <!-- Brand logo: "🏠 HostelHub" with "Hub" in accent colour -->
    <div class="brand">🏠 Hostel<span>Hub</span></div>

    <!-- Navigation links on the right side of the nav bar -->
    <div style="display:flex;gap:12px;align-items:center;">
        <!-- Link back to the main dashboard -->
        <a href="../dashboard.php" style="color:#64748b;font-size:13px;text-decoration:none;">← Home</a>
        <!-- Link back to the fee records list -->
        <a href="index.php" style="color:#64748b;font-size:13px;text-decoration:none;">← Fee Records</a>
    </div>
</nav>

<!-- Main content area -->
<div class="page">

    <!-- Page header: title and subtitle -->
    <div class="page-hdr">
        <h2>Delete Fee Record</h2>
        <p>This will archive the record and remove it from active views.</p>
    </div>

    <!-- Card containing the warning, fee details, and delete form -->
    <div class="form-card">

        <!-- Warning banner explaining the consequences of deletion -->
        <div class="warn">
            <strong>⚠️ Are you sure?</strong>
            This fee record will be archived and no longer visible in the fee list. It can be restored by an administrator from the database.
        </div>

        <!-- Receipt number: displayed in monospace font for readability -->
        <div class="detail-row">
            <span>Receipt</span>
            <!-- htmlspecialchars prevents XSS by escaping special characters -->
            <span style="font-family:'DM Mono',monospace;font-size:12px;"><?= htmlspecialchars($fee['receipt_number']) ?></span>
        </div>

        <!-- Student full name associated with this fee record -->
        <div class="detail-row">
            <span>Student</span>
            <!-- Falls back to '—' if the student name is null (LEFT JOIN miss) -->
            <span><?= htmlspecialchars($fee['full_name'] ?? '—') ?></span>
        </div>

        <!-- Fee type (e.g., "room", "meal") with first letter capitalised -->
        <div class="detail-row">
            <span>Fee Type</span>
            <span><?= ucfirst(htmlspecialchars($fee['fee_type'])) ?></span>
        </div>

        <!-- Fee amount formatted to 2 decimal places, prefixed with currency -->
        <div class="detail-row">
            <span>Amount</span>
            <span style="font-family:'DM Mono',monospace;">kr <?= number_format($fee['amount'], 2) ?></span>
        </div>

        <!-- Due date for this fee record -->
        <div class="detail-row">
            <span>Due Date</span>
            <span><?= htmlspecialchars($fee['due_date']) ?></span>
        </div>

        <!-- Payment status: ✅ Paid if is_paid=1, otherwise ⏳ Unpaid -->
        <div class="detail-row">
            <span>Status</span>
            <span><?= $fee['is_paid'] ? '✅ Paid' : '⏳ Unpaid' ?></span>
        </div>

        <!-- Deletion confirmation form (POST to same page) -->
        <form method="POST">

            <!-- Optional reason field so the admin can explain why they are deleting -->
            <div class="form-group">
                <label>Reason for deletion (optional)</label>
                <input type="text" name="reason" placeholder="e.g. Entered in error, duplicate…">
            </div>

            <!-- Submit button: triggers the PHP deletion logic at the top of the file -->
            <!-- onclick uses a native browser confirm() dialog as a second safety check -->
            <button type="submit" name="confirm_delete" class="btn-delete"
                    onclick="return confirm('Permanently archive this fee record?')">
                🗑 Confirm Delete
            </button>
        </form>

        <!-- Cancel link: goes back to fee list without deleting anything -->
        <a href="index.php" class="back-link">← Cancel, go back</a>
    </div><!-- /.form-card -->
</div><!-- /.page -->

<!-- HostelHub Footer -->
<footer style="background:#fff;border-top:1px solid #e8edf3;margin-top:48px;padding:24px 32px;text-align:center;font-family:'DM Sans',sans-serif;">
    <div style="max-width:1100px;margin:0 auto;">
        <div style="display:flex;align-items:center;justify-content:center;gap:8px;margin-bottom:6px;">
            <span style="font-family:'Playfair Display',serif;font-size:16px;font-weight:700;color:#0f1923;">🏠 Hostel<span style="color:#1a56db;">Hub</span></span>
        </div>
        <p style="font-size:11px;color:#64748b;margin:0;">Hostel Fee Management System &nbsp;·&nbsp; &copy; <?= date('Y') ?> HostelHub</p>
    </div>
</footer>

</body>
</html>