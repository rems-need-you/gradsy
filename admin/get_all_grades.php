<?php
include('partials/constants.php');
if (session_status() === PHP_SESSION_NONE) session_start();

// Check admin authorization
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admins') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

header('Content-Type: application/json');

if (!isset($_GET['student_id'])) {
    echo json_encode(['error' => 'Student ID required']);
    exit();
}

$student_id = (int)$_GET['student_id'];

$sql = "SELECT subject, quarter, quarterly 
        FROM grades3 
        WHERE student_id = ?
        ORDER BY subject, quarter";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $student_id);
$stmt->execute();
$result = $stmt->get_result();

$grades = [];
while ($row = $result->fetch_assoc()) {
    if (!isset($grades[$row['subject']])) {
        $grades[$row['subject']] = [
            'q1' => null,
            'q2' => null,
            'q3' => null,
            'q4' => null,
            'final' => null
        ];
    }
    
    $grades[$row['subject']]['q' . $row['quarter']] = $row['quarterly'];
    
    // Calculate final grade if all quarters are present
    if (isset($grades[$row['subject']]['q1']) &&
        isset($grades[$row['subject']]['q2']) &&
        isset($grades[$row['subject']]['q3']) &&
        isset($grades[$row['subject']]['q4'])) {
        
        $sum = $grades[$row['subject']]['q1'] +
               $grades[$row['subject']]['q2'] +
               $grades[$row['subject']]['q3'] +
               $grades[$row['subject']]['q4'];
        
        $grades[$row['subject']]['final'] = round($sum / 4);
    }
}

echo json_encode($grades);
