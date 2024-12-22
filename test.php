<?php

session_start();
// CSV handling at top
function readCSV() {
    $attendees = [];
    if (($handle = fopen("data.csv", "r")) !== FALSE) {
        fgetcsv($handle);
        while (($data = fgetcsv($handle)) !== FALSE) {
            $attendees[] = [
                'project_name' => $data[0],
                'name' => $data[1],
                'id' => $data[2],
                'present' => $data[3] === "1",
                'late' => $data[4] === "1"
            ];
        }
        fclose($handle);
    }
    return $attendees;
}

if (isset($_GET['sse'])) {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');

    $lastUpdate = isset($_SERVER['HTTP_LAST_EVENT_ID']) ? intval($_SERVER['HTTP_LAST_EVENT_ID']) : 0;

    while (true) {
        clearstatcache();
        $currentModTime = filemtime('data.csv');
        
        // Check if the file has been modified
        if ($currentModTime > $lastUpdate) {
            $attendees = readCSV();
            echo "id: {$currentModTime}\n";
            echo "data: " . json_encode($attendees) . "\n\n";
            flush();
            break;
        }
        
        // Avoid CPU exhaustion
        sleep(1);
    }
    exit();
}

// Modified AJAX endpoint
// Replace POST endpoint section
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $attendee = $data['attendee'];
    
    // Read current CSV
    $attendees = readCSV();
    
    // Update specific attendee
    foreach ($attendees as &$existing) {
        if ($existing['id'] === $attendee['id']) {
            $existing['present'] = $attendee['present'];
            $existing['late'] = $attendee['late'];
            break;
        }
    }
    
    // Write back to CSV
    $fp = fopen('data.csv', 'w');
    fputcsv($fp, ['project_name', 'name', 'ID', 'present', 'late']);
    foreach ($attendees as $row) {
        fputcsv($fp, [
            $row['project_name'],
            $row['name'],
            $row['id'],
            $row['present'] ? "1" : "0",
            $row['late'] ? "1" : "0"
        ]);
    }
    fclose($fp);
    
    touch('updates.txt');
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'attendee' => $attendee]);
    exit;
}

function checkForNewData() {
    static $lastCheck = 0;
    $file = 'updates.txt';
    
    if (file_exists($file)) {
        $currentMod = filemtime($file);
        if ($currentMod > $lastCheck) {
            $lastCheck = $currentMod;
            return json_decode(explode(':', file_get_contents($file), 2)[1], true);
        }
    }
    return false;
}

$attendees = readCSV();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Update</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <h1>Update Attendance</h1>
    </header>

    <div class="container">
        <input type="text" id="search-bar" class="search-bar" placeholder="Search by Student ID or Name" onkeyup="searchAttendance()">

        <h2>Update Attendance</h2>
        <form id="attendance-form">
            <div class="form-group">
                <input type="text" id="id" placeholder="Student ID" required>
                <input type="text" id="name" placeholder="Student Name" required>
                <select id="year" required>
                    <option value="" disabled selected>Select Year</option>
                    <option value="Year 1">Year 1</option>
                    <option value="Year 2">Year 2</option>
                </select>
                <button type="submit">Update Attendance</button>
            </div>
        </form>

        <div class="divider"></div>

        <h2>Attendance List</h2>
        <table>
            <thead>
                <tr>
                    <th>Project Name</th>
                    <th>Name</th>
                    <th>Student ID</th>
                    <th>Talk 1</th>
                    <th>Talk 2</th>
                </tr>
            </thead>
            <tbody id="attendees-list">
                <?php foreach ($attendees as $attendee): ?>
                <tr class="attendance-row">
                    <td><?= htmlspecialchars($attendee['project_name']) ?></td>
                    <td><?= htmlspecialchars($attendee['name']) ?></td>
                    <td><?= htmlspecialchars($attendee['id']) ?></td>
                    <td class="checkbox-cell">
                        <input type="checkbox" class="present-checkbox" data-id="<?= htmlspecialchars($attendee['id']) ?>" <?= $attendee['present'] ? 'checked' : '' ?>>
                    </td>
                    <td class="checkbox-cell">
                        <input type="checkbox" class="late-checkbox" data-id="<?= htmlspecialchars($attendee['id']) ?>" <?= $attendee['late'] ? 'checked' : '' ?>>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
        function searchAttendance() {
            const searchInput = document.getElementById('search-bar').value.toLowerCase();
            const rows = document.querySelectorAll('.attendance-row');

            rows.forEach(row => {
                const name = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                const studentId = row.querySelector('td:nth-child(3)').textContent.toLowerCase();

                if (name.includes(searchInput) || studentId.includes(searchInput)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        document.getElementById('attendees-list').addEventListener('change', function(event) {
    if (event.target.classList.contains('present-checkbox') || 
        event.target.classList.contains('late-checkbox')) {
        
        const row = event.target.closest('tr');
        const cells = row.querySelectorAll('td');
        
        const attendee = {
            project_name: cells[0].innerText,
            name: cells[1].innerText,
            id: cells[2].innerText,
            present: cells[3].querySelector('input').checked,
            late: cells[4].querySelector('input').checked
        };

        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ attendee })
        })
        .then(response => response.json())
        .then(data => console.log('Updated:', data.attendee.id))
        .catch(error => console.error('Error:', error));
    }
});
// Initialize SSE connection
const eventSource = new EventSource(`${window.location.href}?sse=1`);

eventSource.onmessage = function (event) {
    const attendees = JSON.parse(event.data);

    attendees.forEach(attendee => {
        const row = [...document.querySelectorAll('.attendance-row')].find(
            row => row.querySelector('td:nth-child(3)').textContent.trim() === attendee.id
        );

        if (row) {
            row.querySelector('td:nth-child(4) input').checked = attendee.present;
            row.querySelector('td:nth-child(5) input').checked = attendee.late;
        }
    });
};

eventSource.onerror = function () {
    console.error('Error connecting to the SSE server.');
};
    </script>
</body>
</html>