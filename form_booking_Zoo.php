<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: form_login.php");
    exit();
}

include 'db_connection.php';
include 'date_generator.php';

$conn = get_database_connection();

// Generate the next 31 days
$dates = generateNextThirtyOneDays();

// Fetch ticket types
$sql_tickets = "SELECT ticket_id, ticket_name, description, price FROM book_ticket_type";
$result_tickets = $conn->query($sql_tickets);
$ticket_types = [];
if ($result_tickets->num_rows > 0) {
    while ($row = $result_tickets->fetch_assoc()) {
        $ticket_types[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Tickets</title>
    <link rel="stylesheet" href="style_booking.css">
    <script>
        function updateTotals() {
            let ticketPrices = <?php echo json_encode($ticket_types); ?>;
            let total = 0;

            ticketPrices.forEach(ticket => {
                let quantity = parseInt(document.getElementById('quantity_' + ticket.ticket_id).value) || 0;
                let subtotal = quantity * parseFloat(ticket.price);
                document.getElementById('subtotal_' + ticket.ticket_id).textContent = subtotal.toFixed(2);
                total += subtotal;
            });

            document.getElementById('overallTotal').textContent = total.toFixed(2);
        }
    </script>
</head>
<body>
    <nav>
        <div class="nav-left">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <path d="M12 2L2 12h3v8h5v-6h4v6h5v-8h3L12 2z"/>
            </svg>
            <span class="company-name">Roo Zoo</span>
        </div>
        <ul class="navbar">
            <li><a href="../index.html">Home</a></li>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="#">Classes</a></li>
            <li><a href="#">Contact</a></li>
        </ul>
        <div class="nav-right">
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
            <a href="logout.php">Logout</a>
        </div>
    </nav>

    <div class="booking_hero">
        <h1>Book Tickets</h1>
        <p>Select your Ticket Type, Date, and Time</p>
    </div>

    <div class="booking_container">
        <?php
        if (!empty($_SESSION['booking_error_message'])): ?>
            <p class="error-message"><?php echo $_SESSION['booking_error_message']; ?></p>
            <?php
            unset($_SESSION['booking_error_message']);
        endif;
        if(!empty($_SESSION['booking_success_message'])): ?>
            <p class="success-message"><?php echo $_SESSION['booking_success_message']; ?></p>
            <?php
            unset($_SESSION['booking_success_message']);
        endif;
        ?>

        <form id="bookingForm" action="process_booking.php" method="POST">

            <div class="form-group">
                <label for="date">Select Date:</label>
                <select id="date" name="date" required>
                    <?php foreach ($dates as $date): ?>
                        <option value="<?php echo htmlspecialchars($date['formattedDate']); ?>">
                            <?php echo htmlspecialchars($date['formattedDate']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <h2>Ticket Types</h2>
            <div class="ticket-container">
                <?php foreach ($ticket_types as $ticket): ?>
                    <div class="ticket-item">
                        <h3><?php echo htmlspecialchars($ticket['ticket_name']); ?></h3>
                        <p><?php echo htmlspecialchars($ticket['description']); ?></p>
                        <p>Price: £<?php echo htmlspecialchars(number_format($ticket['price'], 2)); ?></p>
                        <label for="quantity_<?php echo $ticket['ticket_id']; ?>">Quantity:</label>
                        <input type="number" id="quantity_<?php echo $ticket['ticket_id']; ?>" name="quantity[<?php echo $ticket['ticket_id']; ?>]" value="0" min="0" onchange="updateTotals()">
                        Subtotal: £<span id="subtotal_<?php echo $ticket['ticket_id']; ?>">0.00</span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="total">
                Overall Total: £<span id="overallTotal">0.00</span>
            </div>

            <button type="submit">Book Tickets</button>
        </form>
    </div>
</body>
</html>