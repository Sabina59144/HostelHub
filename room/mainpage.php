<?php
include 'db.php';

$result = mysqli_query($conn, "SELECT * FROM rooms");
?>

<!DOCTYPE html>
<html>
<head>
<title>Room Management System</title>

<style>
body{
    font-family: Arial;
    background:#f2f2f2;
    margin:0;
    padding:20px;
}

.container{
    width:90%;
    margin:auto;
}

h1{
    text-align:center;
    color:#333;
}

.form-box, .table-box{
    background:white;
    padding:20px;
    margin-bottom:20px;
    border-radius:8px;
}

input, select{
    width:100%;
    padding:10px;
    margin:8px 0;
}

button{
    padding:10px 20px;
    background:green;
    color:white;
    border:none;
    cursor:pointer;
}

table{
    width:100%;
    border-collapse:collapse;
}

table th, table td{
    border:1px solid #ddd;
    padding:10px;
    text-align:center;
}

th{
    background:#333;
    color:white;
}

.available{
    color:green;
    font-weight:bold;
}

.pending{
    color:orange;
    font-weight:bold;
}

a{
    text-decoration:none;
    padding:5px 10px;
}

.edit{
    background:blue;
    color:white;
}

.delete{
    background:red;
    color:white;
}
</style>

</head>
<body>

<div class="container">

<h1>Room Management System</h1>

<!-- Add Room Form -->
<div class="form-box">

<h2>Add New Room</h2>

<form action="save-room.php" method="POST">

<input type="text" name="room_number" placeholder="Room Number" required>

<select name="room_type" required>
<option value="">Select Room Type</option>
<option value="single">Single</option>
<option value="double">Double</option>
<option value="triple">Triple</option>
</select>

<input type="number" name="capacity" placeholder="Capacity" required>

<input type="number" step="0.01" name="price" placeholder="Monthly Price" required>

<select name="ensuite" required>
<option value="">Ensuite Facility</option>
<option value="1">Yes</option>
<option value="0">No</option>
</select>

<input type="date" name="available_from" required>

<button type="submit">Add Room</button>

</form>

</div>

<!-- Room List -->
<div class="table-box">

<h2>All Rooms</h2>

<table>

<tr>
<th>ID</th>
<th>Room No</th>
<th>Type</th>
<th>Capacity</th>
<th>Price</th>
<th>Ensuite</th>
<th>Status</th>
<th>Actions</th>
</tr>

<?php while($row = mysqli_fetch_assoc($result)) { ?>

<tr>

<td><?php echo $row['room_id']; ?></td>
<td><?php echo $row['room_number']; ?></td>
<td><?php echo ucfirst($row['room_type']); ?></td>
<td><?php echo $row['capacity']; ?></td>
<td>$<?php echo $row['price_per_month']; ?></td>
<td><?php echo $row['ensuite_facility'] ? 'Yes' : 'No'; ?></td>

<td>
<?php
if($row['available_from'] <= date('Y-m-d')){
    echo "<span class='available'>Available</span>";
}else{
    echo "<span class='pending'>Coming Soon</span>";
}
?>
</td>

<td>
<a class="edit" href="edit-room.php?id=<?php echo $row['room_id']; ?>">Edit</a>
<a class="delete" href="delete-room.php?id=<?php echo $row['room_id']; ?>" onclick="return confirm('Delete this room?')">Delete</a>
</td>

</tr>

<?php } ?>

</table>

</div>

</div>

</body>
</html>