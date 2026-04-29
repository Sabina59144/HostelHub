
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HostelHub — Room Module</title>
    <a href="style.css"></a>
</head>
<body>

<!-- ── Navigation Bar ── -->
<nav class="navbar">
    <h1>🏨 HostelHub</h1>
    <div>
        <!-- Show the logged-in user's name from the session -->
        <span class="user-info">Logged in as: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></span>
        <!-- Link back to the main dashboard -->
        <a href="../dashboard.php">🏠 Dashboard</a>
        <!-- Logout link -->
        <a href="../logout.php">🚪 Logout</a>
    </div>
</nav>

<!-- ── Main Content ── -->
<div class="container">

   

    <!-- Page heading -->
    <div class="page-title">
        <h2>🛏️ Room Module</h2>
        <p>Manage hostel room records — add, view, edit, and search rooms</p>
    </div>

    <!-- Summary cards showing quick stats from the database -->
    <div class="summary-cards">
        <div class="card">
            <div class="number"><?php echo $totalRooms; ?></div>
            <div class="label">Total Rooms</div>
        </div>
        <div class="card">
            <div class="number"><?php echo $totalAvail; ?></div>
            <div class="label">Available Rooms</div>
        </div>
        <div class="card">
            <div class="number"><?php echo $totalEnsuite; ?></div>
            <div class="label">Ensuite Rooms</div>
        </div>
    </div>

    <!-- Module action buttons — these pages will be built in Weeks 2-4 -->
    <div class="actions">
        <a href="add_room.php" class="action-btn">
            <div class="icon">➕</div>
            <div class="btn-label">Add Room</div>
            <div class="btn-desc">Register a new room</div>
        </a>
        <a href="list_rooms.php" class="action-btn">
            <div class="icon">📋</div>
            <div class="btn-label">All Rooms</div>
            <div class="btn-desc">View all room records</div>
        </a>
        <a href="search_room.php" class="action-btn">
            <div class="icon">🔍</div>
            <div class="btn-label">Find Room</div>
            <div class="btn-desc">Search by room number</div>
        </a>
        <a href="filter_rooms.php" class="action-btn">
            <div class="icon">🗂️</div>
            <div class="btn-label">Filter Rooms</div>
            <div class="btn-desc">Filter by type or availability</div>
        </a>
    </div>

</div><!-- end container -->

</body>
</html>
<?php
mysqli_close($conn);
?>
