<?php
/**
 * Donor Schedule Page
 */

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/db.php';

// Guard role
checkRole(['donor']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Donation — BDMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- FullCalendar CSS and JS -->
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>

    <style>
        :root {
            --bg-color: #f1f5f9;
            --nav-bg: #ffffff;
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --primary: #dc2626; /* Crimson red */
            --primary-hover: #b91c1c;
            --border: #e2e8f0;
            --radius: 16px;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
        }
        * { box-sizing: border-box; }
        body {
            background-color: var(--bg-color);
            color: var(--text-main);
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Navbar */
        .navbar {
            background-color: var(--nav-bg);
            border-bottom: 1px solid var(--border);
            padding: 0 32px;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .nav-brand {
            font-weight: 700;
            font-size: 20px;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .nav-brand svg { width: 24px; height: 24px; fill: currentColor; }
        .nav-links { display: flex; gap: 24px; align-items: center; }
        .nav-links a { text-decoration: none; color: var(--text-muted); font-weight: 500; font-size: 15px; transition: color 0.2s; }
        .nav-links a:hover, .nav-links a.active { color: var(--text-main); }
        .nav-links a.btn-logout { background-color: #f1f5f9; color: var(--text-main); padding: 8px 16px; border-radius: 8px; font-weight: 600; }
        .nav-links a.btn-logout:hover { background-color: #e2e8f0; }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 40px 24px;
            width: 100%;
            flex: 1;
        }

        .page-header { margin-bottom: 24px; }
        .page-header h1 { font-size: 28px; font-weight: 700; margin: 0 0 8px 0; }
        .page-header p { color: var(--text-muted); margin: 0; font-size: 16px; }

        .card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 24px;
            box-shadow: var(--shadow-sm);
        }
        
        /* Calendar Container Styles */
        #calendar {
            margin-top: 20px;
            min-height: 500px;
        }

        .fc .fc-toolbar-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-main);
        }
        .fc .fc-button-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        .fc .fc-button-primary:not(:disabled):active,
        .fc .fc-button-primary:not(:disabled).fc-button-active {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
        }
        .fc-event {
            cursor: pointer;
            padding: 2px 4px;
            font-size: 12px;
            border-radius: 4px;
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 100;
        }
        .modal-overlay.active { display: flex; }
        .modal {
            background: #fff;
            padding: 24px;
            border-radius: 12px;
            width: 100%;
            max-width: 400px;
            box-shadow: var(--shadow-md);
            text-align: center;
        }
        .modal h2 { margin: 0 0 16px 0; font-size: 20px; }
        .modal p { color: var(--text-muted); margin-bottom: 24px; line-height: 1.5; }
        .modal-actions {
            display: flex;
            justify-content: center;
            gap: 12px;
        }
        .btn {
            background: var(--primary); color: #fff;
            padding: 10px 20px; border-radius: 8px;
            font-weight: 600; font-size: 14px;
            border: none; cursor: pointer;
        }
        .btn:hover { background: var(--primary-hover); }
        .btn-outline {
            background: #fff; color: var(--text-main);
            border: 1px solid var(--border);
        }
        .btn-outline:hover { background: #f8fafc; }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="nav-brand">
        <svg viewBox="0 0 32 32"><path d="M16 4C16 4 6 14.5 6 20.5C6 26.3 10.5 29 16 29C21.5 29 26 26.3 26 20.5C26 14.5 16 4 16 4Z"/></svg>
        BDMS
    </div>
    <div class="nav-links">
        <a href="donor-dashboard.php">Dashboard</a>
        <a href="donor-schedule.php" class="active">Schedule Donation</a>
        <a href="donor-edit-profile.php">Edit Profile</a>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>
</nav>

<main class="container">
    <div class="page-header">
        <h1>Schedule a Donation</h1>
        <p>Select an available slot on the calendar below to book your next blood donation appointment.</p>
    </div>

    <div class="card">
        <div id="calendar"></div>
    </div>
</main>

<!-- Booking Modal -->
<div class="modal-overlay" id="bookingModal">
    <div class="modal">
        <h2 id="modalTitle">Confirm Booking</h2>
        <p id="modalDesc">Are you sure you want to book this donation slot?</p>
        <div class="modal-actions">
            <button class="btn btn-outline" id="btnCancel">Cancel</button>
            <button class="btn" id="btnConfirm">Book Slot</button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const calendarEl = document.getElementById('calendar');
        const modal = document.getElementById('bookingModal');
        const btnCancel = document.getElementById('btnCancel');
        const btnConfirm = document.getElementById('btnConfirm');
        const modalDesc = document.getElementById('modalDesc');
        const modalTitle = document.getElementById('modalTitle');
        
        let selectedSlotId = null;
        let currentAction = 'book'; // 'book' or 'cancel'

        const calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            events: 'api/get-slots.php',
            eventClick: function(info) {
                const props = info.event.extendedProps;
                
                const dateStr = info.event.start.toLocaleString();

                if (props.isBooked) {
                    currentAction = 'cancel';
                    selectedSlotId = info.event.id;
                    modalTitle.innerText = 'Cancel Appointment';
                    modalDesc.innerHTML = `Are you sure you want to cancel your donation appointment on <strong>${dateStr}</strong>?<br><br><small>If you need to reschedule, simply cancel this appointment and select a new available slot.</small>`;
                    btnConfirm.innerText = 'Cancel Appointment';
                    btnConfirm.style.backgroundColor = '#64748b'; // Gray-ish for cancel
                    modal.classList.add('active');
                    return;
                }
                if (props.isFull) {
                    alert('This slot is full.');
                    return;
                }

                // Prepare modal for booking
                currentAction = 'book';
                selectedSlotId = info.event.id;
                modalTitle.innerText = 'Confirm Booking';
                modalDesc.innerHTML = `Are you sure you want to book a donation slot on <strong>${dateStr}</strong>?`;
                btnConfirm.innerText = 'Book Slot';
                btnConfirm.style.backgroundColor = 'var(--primary)';
                modal.classList.add('active');
            }
        });

        calendar.render();

        btnCancel.addEventListener('click', () => {
            modal.classList.remove('active');
            selectedSlotId = null;
        });

        btnConfirm.addEventListener('click', () => {
            if (!selectedSlotId) return;

            btnConfirm.disabled = true;
            btnConfirm.innerText = 'Processing...';

            const endpoint = currentAction === 'book' ? 'api/book-slot.php' : 'api/cancel-slot.php';

            fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ slot_id: selectedSlotId })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    calendar.refetchEvents();
                } else {
                    alert(data.message || 'An error occurred.');
                }
            })
            .catch(err => {
                console.error(err);
                alert('A network error occurred.');
            })
            .finally(() => {
                btnConfirm.disabled = false;
                modal.classList.remove('active');
                selectedSlotId = null;
            });
        });
    });
</script>

</body>
</html>
