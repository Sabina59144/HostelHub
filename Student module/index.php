
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HostelHub — Student Module</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>

<!-- ── Navigation Bar ── -->
<nav class="navbar">
    <h1>HostelHub</h1>
    <div>
        <!-- Show the logged-in user's name from the session -->
        <span class="user-info">Logged in as: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></span>
        <!-- Link back to the main dashboard -->
        <a href="../dashboard.php">Dashboard</a>
        <!-- Logout link -->
        <a href="../logout.php">Logout</a>
    </div>
</nav>

<!-- ── Main Content ── -->
<div class="container">

    <!-- Week 1 setup banner -->
    <div class="week-banner">
        <strong>Week 1 — Setup Complete </strong>
        Database table created · Folder structure set up · DB connection working · Module pages planned
    </div>

    <!-- Page heading -->
    <div class="page-title">
        <h2> Student Module</h2>
        <p>Manage hostel student records — add, view, edit, and search students</p>
    </div>

    <!-- Summary cards showing quick stats from the database -->
    <div class="summary-cards">
        <div class="card">
            <div class="number"><?php echo $totalStudents; ?></div>
            <div class="label">Total Students</div>
        </div>
        <div class="card">
            <div class="number"><?php echo $totalActive; ?></div>
            <div class="label">Active Students</div>
        </div>
        <div class="card">
            <div class="number"><?php echo $totalUnassigned; ?></div>
            <div class="label">No Room Assigned</div>
        </div>
    </div>

    <!-- Module action buttons — these pages will be built in Weeks 2-4 -->
    <div class="actions">
        <a href="add_student.php" class="action-btn">
            <div class="icon"></div>
            <div class="btn-label">Add Student</div>
            <div class="btn-desc">Register a new student</div>
        </a>
        <a href="list_students.php" class="action-btn">
            <div class="icon">📋</div>
            <div class="btn-label">All Students</div>
            <div class="btn-desc">View all student records</div>
        </a>
        <a href="search_student.php" class="action-btn">
            <div class="icon">🔍</div>
            <div class="btn-label">Find Student</div>
            <div class="btn-desc">Search by name or ID</div>
        </a>
        <a href="filter_students.php" class="action-btn">
            <div class="icon">🗂️</div>
            <div class="btn-label">Filter Students</div>
            <div class="btn-desc">Filter by status or room</div>
        </a>
    </div>

</div><!-- end container -->

</body>
</html>