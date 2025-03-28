<?php
include 'db_connection.php';

$conn = get_database_connection();

$selected_date = $_GET['date'];

$sql = "SELECT slot_id, start_times, end_times, capacity, availability_spots
        FROM booking_slots
        WHERE day_id = (SELECT days_id FROM book_ticket_days WHERE $selected_date = 'available')";

$result = $conn->query($sql);

$slots = array();
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $slots[] = $row;
    }
}

$conn->close();

header('Content-Type: application/json');
echo json_encode($slots);
?>