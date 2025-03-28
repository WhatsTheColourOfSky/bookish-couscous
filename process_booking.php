<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: form_login.php");
    exit();
}

include 'db_connection.php';

$conn = get_database_connection();

// --- Functions ---

/**
 * Validates the user's input from the booking form.
 
 */
function validateBookingInput(array $quantities, string $booking_date): void
{
    // Validate that at least one ticket has a quantity greater than 0
    $total_quantity = array_sum($quantities);
    if ($total_quantity <= 0) {
        throw new Exception("Please select at least one ticket with a quantity of 1 or more.");
    }

    // Validate that the selected date is within the next 31 days
    //Now expecting date in UK Date format

    $today = new DateTime();
    $selected_date = DateTime::createFromFormat('l, d F Y', $booking_date);

    //Check If Parsing Failed
    if(!$selected_date){
        throw new Exception("Invalid date format");
    }
    $interval = $today->diff($selected_date);

    if ($interval->days > 31) {
        throw new Exception("Please select a date within the next 31 days.");
    }
}

/**
 * Calculates the total price of the booking based on the quantities of each ticket type.
 
 */
function calculateTotalPrice(mysqli $conn, array $quantities): float
{
    $total_price = 0;
    foreach ($quantities as $ticket_id => $quantity) {
        // Fetch the ticket price from the database
        $sql_ticket = "SELECT price FROM book_ticket_type WHERE ticket_id = ?";
        $stmt_ticket = $conn->prepare($sql_ticket);
        $stmt_ticket->bind_param("i", $ticket_id);
        $stmt_ticket->execute();
        $result_ticket = $stmt_ticket->get_result();

        if ($result_ticket->num_rows == 1) {
            $ticket = $result_ticket->fetch_assoc();
            $total_price += $ticket['price'] * $quantity;
        } else {
            // Handle error: ticket not found
            throw new Exception("Error: Ticket with ID $ticket_id not found.");
        }

        $stmt_ticket->close();
    }

    return $total_price;
}

/**
 * Finds an available slot for the given day with enough capacity.
 *

 */
function findAvailableSlot(mysqli $conn, string $booking_date, int $total_tickets_to_book): array
{
    //  expecting date in 'l, d F Y' format (e.g., "Monday, 17 July 2024")
    $dateObject = DateTime::createFromFormat('l, d F Y', $booking_date);
    $dayOfWeek = $dateObject->format('l');

    // Find the day_id corresponding to the day of the week
    $sql_day = "SELECT days_id FROM book_ticket_days WHERE `day` = ?";  // Correct table query
    $stmt_day = $conn->prepare($sql_day);
    $stmt_day->bind_param("s", $dayOfWeek);  // Correct table query
    $stmt_day->execute();
    $result_day = $stmt_day->get_result();

    if ($result_day && $result_day->num_rows == 1) {
        $day = $result_day->fetch_assoc();
        $day_id = $day['days_id'];

        // Find a slot with enough capacity for that day
        $sql_slot = "SELECT slot_id, end_times, capacity FROM booking_slots
                     WHERE day_id = ?
                     LIMIT 1"; //This will select one with the least amount of spots left and removed spots as its in bookings to validate against SQL as its wrong

        $stmt_slot = $conn->prepare($sql_slot);
        $stmt_slot->bind_param("i", $day_id);
        $stmt_slot->execute();
        $result_slot = $stmt_slot->get_result();

        if ($result_slot->num_rows == 1) {
            $slot = $result_slot->fetch_assoc();
            $slot_id = (int)$slot['slot_id'];
            $capacity = (int)$slot['capacity'];

            // Count existing bookings for that particular slot
            $sql_bookings_count = "SELECT SUM(quantity) AS total_booked FROM booked_tickets bt
                JOIN bookings b ON bt.user_booking_id = b.booking_id
                WHERE b.slot_id = ?";
            $stmt_bookings_count = $conn->prepare($sql_bookings_count);
            $stmt_bookings_count->bind_param("i", $slot_id);
            $stmt_bookings_count->execute();
            $result_bookings_count = $stmt_bookings_count->get_result();
            $bookings_count = 0;
            if ($result_bookings_count->num_rows > 0) {
                $row = $result_bookings_count->fetch_assoc();
                $bookings_count = (int)$row['total_booked'];
            }
            $stmt_bookings_count->close();

            // Check availability
            $available_spots = $capacity - $bookings_count;

            if ($available_spots < $total_tickets_to_book) {
                throw new Exception("Sorry, there are only " . $available_spots . " tickets left for $booking_date. Please reduce the number of tickets.");
            }

            return [
                'slot_id' => $slot_id,
                'end_times' => $slot['end_times'],
                'capacity' =>  $capacity,

            ];

        } else {
            throw new Exception("Sorry, there are no available time slots with enough capacity for the selected date.");
        }

    } else {
        throw new Exception("Error: No available day found for the selected date.");
    }
}

/**
 * Validates if the booking time is at least one hour before the slot's end time.
 *
 */
function validateBookingTime(string $booking_date, string $slot_end_time): void
{
    // Get the current time
    $now = new DateTime();

    // Create a DateTime object from the booking date
    $dateObject = DateTime::createFromFormat('l, d F Y', $booking_date);
    $bookingDateString = $dateObject->format('Y-m-d');

    // Create a DateTime object for the end time, combining the booking date and end time
    $slotEndTime = new DateTime($bookingDateString . ' ' . $slot_end_time);

    // Get the current date and time
    $currentDateTime = new DateTime();

    // Calculate the time difference in hours
    $timeDiff = ($slotEndTime->getTimestamp() - $currentDateTime->getTimestamp()) / 3600;

    // Check if the time difference is less than 1 hour
    if ($timeDiff < 1) {
        throw new Exception("Sorry, you cannot book tickets within 1 hour of the slot end time. Please select another day.");
    }


}

/**
 * Creates a new booking in the database.

 */

function createBooking(mysqli $conn, int $user_id, int $slot_id, string $booking_date, float $total_price, array $quantities, string $confirmation_number): int
{

      // Convert the booking_date to the correct format for the database
      $dateObject = DateTime::createFromFormat('l, d F Y', $booking_date); // parse input
      $booking_datetime = $dateObject->format('Y-m-d H:i:s'); // format for database

    // Insert the booking into the bookings table
    $sql_booking = "INSERT INTO bookings (total_price, payment_status, confirmation_number, user_id, slot_id, booking_date) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt_booking = $conn->prepare($sql_booking);
    $payment_status = 'pending'; // Default status

   
    $stmt_booking->bind_param("dsssss", $total_price, $payment_status, $confirmation_number, $user_id, $slot_id, $booking_datetime);
    $stmt_booking->execute();

    if ($stmt_booking->error) {
        throw new Exception("Error inserting booking: " . $stmt_booking->error);
    }

    $booking_id = $conn->insert_id;  // Get the newly inserted booking ID
    $stmt_booking->close();

    // Insert the booked tickets into the booked_tickets table
    $sql_booked_tickets = "INSERT INTO booked_tickets (user_booking_id, ticket_id, quantity, price) VALUES (?, ?, ?, ?)";
    $stmt_booked_tickets = $conn->prepare($sql_booked_tickets);

    foreach ($quantities as $ticket_id => $quantity) {
        if ($quantity > 0) {
            // Fetch the ticket price from the database again (for consistency)
            $sql_ticket = "SELECT price FROM book_ticket_type WHERE ticket_id = ?";
            $stmt_ticket = $conn->prepare($sql_ticket);
            $stmt_ticket->bind_param("i", $ticket_id);
            $stmt_ticket->execute();
            $result_ticket = $stmt_ticket->get_result();
            $ticket = $result_ticket->fetch_assoc();
            $ticket_price = $ticket['price'];
            $stmt_ticket->close();

            $stmt_booked_tickets->bind_param("iiid", $booking_id, $ticket_id, $quantity, $ticket_price);
            $stmt_booked_tickets->execute();

            if ($stmt_booked_tickets->error) {
                throw new Exception("Error inserting booked ticket: " . $stmt_booked_tickets->error);
            }
        }
    }
    $stmt_booked_tickets->close();

    return $booking_id;
}

/**
 * Updates the availability of spots in the selected time slot.
 *
 
 */
function updateAvailability(mysqli $conn, int $slot_id, int $total_tickets_to_book): void
{
     //Nothing Need
}

// --- Main Execution ---

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];
    $booking_date = $_POST['date'];
    $quantities = $_POST['quantity'];

    try {
        // 1. Validate the input
        validateBookingInput($quantities, $booking_date);

        // 2. Calculate the total price
        $total_price = calculateTotalPrice($conn, $quantities);

        // 3. Generate a unique confirmation number
        $confirmation_number = uniqid('ZOO-', true);
        var_dump($confirmation_number);  // Add this line
        // 4. Determine the total tickets to book
        $total_tickets_to_book = array_sum($quantities);

        // 5. Start a transaction
        $conn->begin_transaction();

        try {
            // 6. Find an available slot
            $slot_info = findAvailableSlot($conn, $booking_date, $total_tickets_to_book); //slot_id and end_times from the function

            // 7. Validate booking time
            validateBookingTime($booking_date, $slot_info['end_times']);

            //Get slot ID
            $slot_id = $slot_info['slot_id'];

            // 8. Create the booking
            $booking_id = createBooking($conn, $user_id, $slot_id, $booking_date, $total_price, $quantities, $confirmation_number);

            // 9. Update availability No Need.

            // 10. Commit the transaction
            $conn->commit();

            // 11. Store booking information in the session
            $_SESSION['booking_confirmation'] = [
                'confirmation_number' => $confirmation_number,
                'booking_date' => $booking_date,
                'total_price' => $total_price,
                'quantities' => $quantities,
                'slot_id' => $slot_id // STORE SLOT ID
            ];

            // 12. Redirect to the confirmation page
            header("Location: confirmation.php");
            exit();

        } catch (Exception $e) {
            // Rollback the transaction on error
            $conn->rollback();
            throw $e;  // Re-throw the exception to be caught by the outer catch block
        }

    } catch (Exception $e) {
        $_SESSION['booking_error_message'] = "Booking failed: " . $e->getMessage();
        header("Location: form_booking_zoo.php");
        exit();
    }

} else {
    // If not a POST request
    header("Location: form_booking_zoo.php");
    exit();
}

$conn->close();
?>