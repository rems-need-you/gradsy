<?php
include('partials/constants.php');
header('Content-Type: application/json');

if (!isset($_GET['student_id']) || !isset($_GET['subject'])) {
    echo json_encode(["success" => false, "message" => "Missing required parameters"]);
    exit;
}

$student_id = (int)$_GET['student_id'];
$subject = trim($_GET['subject']);

$stmt = $conn->prepare("SELECT quarter, quarterly FROM grades3 WHERE student_id = ? AND subject = ?");
$stmt->bind_param("is", $student_id, $subject);
$stmt->execute();
$result = $stmt->get_result();

$grades = [];
while ($row = $result->fetch_assoc()) {
    $grades[$row['quarter']] = $row['quarterly'];
}

$stmt->close();

echo json_encode([
    "success" => true,
    "grades" => $grades
]);
?>
