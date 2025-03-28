<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['booking_confirmation'])) {
    header("Location: form_login.php"); // Or back to the booking form
    exit();
}

$confirmation = $_SESSION['booking_confirmation'];

// Retrieve the ticket types from the database
include 'db_connection.php';
$conn = get_database_connection();

$sql_tickets = "SELECT ticket_id, ticket_name FROM book_ticket_type";
$result_tickets = $conn->query($sql_tickets);
$ticket_types = [];
if ($result_tickets->num_rows > 0) {
    while ($row = $result_tickets->fetch_assoc()) {
        $ticket_types[$row['ticket_id']] = $row['ticket_name'];
    }
}

//Retrieve the slot time

$slot_id = $confirmation['slot_id'];

$sql_slot = "SELECT start_times, end_times FROM booking_slots WHERE slot_id = ?";
$stmt_slot = $conn->prepare($sql_slot);
$stmt_slot->bind_param("i", $slot_id);
$stmt_slot->execute();
$result_slot = $stmt_slot->get_result();

if ($result_slot->num_rows == 1) {
    $slot = $result_slot->fetch_assoc();
    $start_time = date('H:i', strtotime($slot['start_times']));  // Format start time
    $end_time = date('H:i', strtotime($slot['end_times']));    // Format end time
    $slot_time = $start_time . ' - ' .  $end_time; //display time
} else {
    $slot_time = "Unknown Slot";
}
$conn->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmation</title>
    <link rel="stylesheet" href="style_booking.css">
</head>
<body>
    <nav>
        <!-- Your navigation bar -->
    </nav>

    <div class="confirmation-container">
        <h1>Booking Confirmation</h1>
        <p>Thank you for your booking!</p>

        <div class="confirmation-details">
            <p><strong>Confirmation Number:</strong> <?php echo htmlspecialchars($confirmation['confirmation_number']); ?></p>
            <p><strong>Booking Date:</strong> <?php echo date('d/m/Y', strtotime($confirmation['booking_date'])); ?></p>
            <p><strong>Slot Time:</strong> <?php echo htmlspecialchars($slot_time); ?></p>
            <p><strong>Total Price:</strong> £<?php echo htmlspecialchars(number_format($confirmation['total_price'], 2)); ?></p>

            <h2>Ticket Details:</h2>
            <ul>
                <?php foreach ($confirmation['quantities'] as $ticket_id => $quantity): ?>
                    <?php if ($quantity > 0): ?>
                        <li><?php echo htmlspecialchars($ticket_types[$ticket_id]); ?>: <?php echo htmlspecialchars($quantity); ?></li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</body>
</html>
<?php
    // Unset the confirmation information so it's not accidentally shown again
    unset($_SESSION['booking_confirmation']);
?>