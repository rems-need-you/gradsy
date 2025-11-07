<?php
// fetch_students.php

// Ensure session and database connection
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Adjust path to constantsss.php if fetch_students.php is in a different directory
include('partials-front/constantsss.php'); // Assuming constantsss.php is in partials-front/ directly inside your project root

header('Content-Type: application/json'); // Set header to indicate JSON response

if (!isset($conn) || $conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed.']);
    exit();
}

$grade_filter = $_GET['grade'] ?? '';
$section_filter = $_GET['section'] ?? '';

$sql = "SELECT id, name, grade_level, section FROM students WHERE 1=1";
$params = [];
$types = "";

if (!empty($grade_filter)) {
    $sql .= " AND grade_level = ?";
    $params[] = $grade_filter;
    $types .= "s";
}
if (!empty($section_filter)) {
    $sql .= " AND section = ?";
    $params[] = $section_filter;
    $types .= "s";
}

$sql .= " ORDER BY name ASC";

$stmt = $conn->prepare($sql);

if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    echo json_encode($students);
    $stmt->close();
} else {
    echo json_encode(['error' => 'Failed to prepare statement: ' . $conn->error]);
}

$conn->close(); // Close the connection for this script
?>