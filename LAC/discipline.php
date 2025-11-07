<?php
include('../partials-front/constantsss.php');

$sql = "SELECT * FROM discipline_records ORDER BY date_reported DESC";
$result = mysqli_query($conn, $sql);

if (!$result) {
    die("SQL Error: " . mysqli_error($conn));
}

if (mysqli_num_rows($result) > 0) {
    echo "<table border='1' cellspacing='0' cellpadding='8' width='100%'>";
    echo "<tr style='background:#b60303;color:#fff;'>
            <th>ID</th>
            <th>Student ID</th>
            <th>Student Name</th>
            <th>Reported By</th>
            <th>Role</th>
            <th>Offense Type</th>
            <th>Description</th>
            <th>Action Taken</th>
            <th>Severity</th>
            <th>Status</th>
            <th>Date Reported</th>
          </tr>";

    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>
                <td>{$row['id']}</td>
                <td>{$row['student_id']}</td>
                <td>{$row['student_name']}</td>
                <td>{$row['reported_by']}</td>
                <td>{$row['role']}</td>
                <td>{$row['offense_type']}</td>
                <td>{$row['description']}</td>
                <td>{$row['action_taken']}</td>
                <td>{$row['severity']}</td>
                <td>{$row['status']}</td>
                <td>{$row['date_reported']}</td>
              </tr>";
    }

    echo "</table>";
} else {
    echo "<p>No disciplinary records found.</p>";
}
?>
