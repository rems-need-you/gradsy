<?php
// Include your existing DB connection
include('../partials-front/constantsss.php');

header('Content-Type: application/json');

// Query to fetch Top 50 students (grade 4–6, section 7)
$sql = "SELECT name, grade, section, year, average 
        FROM student 
        WHERE grade BETWEEN 4 AND 6 AND section = 7 
        ORDER BY average DESC 
        LIMIT 50";

$result = mysqli_query($conn, $sql);

$student = [];

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $student[] = $row;
    }
}

echo json_encode($student);
