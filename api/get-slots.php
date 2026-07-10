<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

// Only logged in users (or specific roles) can fetch slots, but let's just guard that they are logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM donation_slots WHERE status = 'available'");
    $stmt->execute();
    $slots = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch user's existing bookings
    $stmt2 = $pdo->prepare("SELECT slot_id FROM donation_bookings WHERE donor_id = :donor_id AND status = 'scheduled'");
    $stmt2->execute(['donor_id' => $_SESSION['user_id']]);
    $myBookings = $stmt2->fetchAll(PDO::FETCH_COLUMN);

    $events = [];

    foreach ($slots as $slot) {
        $start = $slot['slot_date'] . 'T' . $slot['start_time'];
        $end = $slot['slot_date'] . 'T' . $slot['end_time'];
        
        $isMyBooking = in_array($slot['id'], $myBookings);

        if ($isMyBooking) {
            $events[] = [
                'id' => $slot['id'],
                'title' => 'My Booking',
                'start' => $start,
                'end' => $end,
                'backgroundColor' => '#15803d', // Green for their booking
                'borderColor' => '#15803d',
                'extendedProps' => [
                    'isBooked' => true,
                    'capacity' => $slot['capacity'],
                    'booked_count' => $slot['booked_count']
                ]
            ];
        } else if ($slot['booked_count'] < $slot['capacity']) {
            $events[] = [
                'id' => $slot['id'],
                'title' => ($slot['capacity'] - $slot['booked_count']) . ' slots left',
                'start' => $start,
                'end' => $end,
                'backgroundColor' => '#dc2626', // Red for available blood donation slots
                'borderColor' => '#dc2626',
                'extendedProps' => [
                    'isBooked' => false,
                    'capacity' => $slot['capacity'],
                    'booked_count' => $slot['booked_count']
                ]
            ];
        } else {
            $events[] = [
                'id' => $slot['id'],
                'title' => 'Full',
                'start' => $start,
                'end' => $end,
                'backgroundColor' => '#94a3b8', // Gray for full
                'borderColor' => '#94a3b8',
                'extendedProps' => [
                    'isBooked' => false,
                    'isFull' => true
                ]
            ];
        }
    }

    echo json_encode($events);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
