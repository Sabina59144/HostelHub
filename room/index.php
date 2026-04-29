<?php
include 'db.php';

$msg = "";

/* Success Messages */
if(isset($_GET['msg'])){
    if($_GET['msg']=="added") $msg="Room Added Successfully";
    if($_GET['msg']=="updated") $msg="Room Updated Successfully";
    if($_GET['msg']=="deleted") $msg="Room Deleted Successfully";
}

/* Search + Filter */
$search = isset($_GET['search']) ? $_GET['search'] : "";
$type = isset($_GET['type']) ? $_GET['type'] : "";

$sql = "SELECT * FROM rooms WHERE 1";

if($search != ""){
    $sql .= " AND room_number LIKE '%$search%'";
}

if($type != ""){
    $sql .= " AND room_type='$type'";
}

$result = mysqli_query($conn,$sql);

/* Edit Data */
$editData = null;

if(isset($_GET['edit'])){
    $id = $_GET['edit'];
    $editResult = mysqli_query($conn,"SELECT * FROM rooms WHERE room_id='$id'");
    $editData = mysqli_fetch_assoc($editResult);
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Room Management</title>
</head>
<body>

<h1>Room Management System</h1>

<!-- Success Message -->
<?php if($msg!=""){ ?>
<p><?php echo $msg; ?></p>
<?php } ?>

<!-- Search + Filter -->
<form method="GET">

<input type="text" name="search" placeholder="Search Room Number"
value="<?php echo $search; ?>">

<select name="type">
<option value="">All Types</option>
<option value="single">Single</option>
<option value="double">Double</option>
<option value="triple">Triple</option>
</select>

<button type="submit">Search</button>

</form>

<hr>

<!-- Add Room Form -->
<h2>Add Room</h2>

<form action="save-room.php" method="POST">

Room Number:
<input type="text" name="room_number" required><br><br>

Type:
<select name="room_type" required>
<option value="">Select</option>
<option value="single">Single</option>
<option value="double">Double</option>
<option value="triple">Triple</option>
</select><br><br>

Capacity:
<input type="number" name="capacity" required><br><br>

Price:
<input type="number" step="0.01" name="price" required><br><br>

Ensuite:
<select name="ensuite">
<option value="1">Yes</option>
<option value="0">No</option>
</select><br><br>

Available From:
<input type="date" name="available_from" required><br><br>

<button type="submit">Add Room</button>

</form>

<hr>

<!-- Edit Popup Form -->
<?php if($editData){ ?>

<h2>Edit Room</h2>

<form action="update-room.php" method="POST">

<input type="hidden" name="id"
value="<?php echo $editData['room_id']; ?>">

Room Number:
<input type="text" name="room_number"
value="<?php echo $editData['room_number']; ?>" required><br><br>

Type:
<select name="room_type">

<option value="single"
<?php if($editData['room_type']=="single") echo "selected"; ?>>
Single
</option>

<option value="double"
<?php if($editData['room_type']=="double") echo "selected"; ?>>
Double
</option>

<option value="triple"
<?php if($editData['room_type']=="triple") echo "selected"; ?>>
Triple
</option>

</select><br><br>

Capacity:
<input type="number" name="capacity"
value="<?php echo $editData['capacity']; ?>"><br><br>

Price:
<input type="number" step="0.01" name="price"
value="<?php echo $editData['price_per_month']; ?>"><br><br>

Ensuite:
<select name="ensuite">
<option value="1" <?php if($editData['ensuite_facility']==1) echo "selected"; ?>>Yes</option>
<option value="0" <?php if($editData['ensuite_facility']==0) echo "selected"; ?>>No</option>
</select><br><br>

Available From:
<input type="date" name="available_from"
value="<?php echo $editData['available_from']; ?>"><br><br>

<button type="submit">Update Room</button>

<a href="index.php">Cancel</a>

</form>

<hr>

<?php } ?>

<!-- Room Table -->
<h2>All Rooms</h2>

<table border="1" cellpadding="10">

<tr>
<th>ID</th>
<th>Room No</th>
<th>Type</th>
<th>Capacity</th>
<th>Price</th>
<th>Status</th>
<th>Action</th>
</tr>

<?php while($row=mysqli_fetch_assoc($result)){ ?>

<tr>

<td><?php echo $row['room_id']; ?></td>
<td><?php echo $row['room_number']; ?></td>
<td><?php echo $row['room_type']; ?></td>
<td><?php echo $row['capacity']; ?></td>
<td><?php echo $row['price_per_month']; ?></td>

<td>
<?php
if($row['available_from'] <= date('Y-m-d'))
echo "Available";
else
echo "Coming Soon";
?>
</td>

<td>
<a href="index.php?edit=<?php echo $row['room_id']; ?>">Edit</a>
|
<a href="delete-room.php?id=<?php echo $row['room_id']; ?>"
onclick="return confirm('Delete?')">Delete</a>
</td>

</tr>

<?php } ?>

</table>

</body>
</html>