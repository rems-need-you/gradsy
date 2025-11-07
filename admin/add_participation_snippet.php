// ...existing insertion code...
// $student_id, $percent, $activity_title, $remarks are set earlier

// Ensure created_at is set and insert
$stmt = $conn->prepare("INSERT INTO participations (student_id, activity_title, percent, remarks, created_at) VALUES (?, ?, ?, ?, NOW())");
$stmt->bind_param("isds", $student_id, $activity_title, $percent, $remarks);
$stmt->execute();
$stmt->close();

// Fetch latest participation to confirm (used by UI that queries latest by created_at)
$ps = $conn->prepare("SELECT student_id, percent, activity_title, remarks, created_at FROM participations WHERE student_id = ? ORDER BY created_at DESC LIMIT 1");
$ps->bind_param("i", $student_id);
$ps->execute();
$res = $ps->get_result();
$latest = $res->fetch_assoc();
$ps->close();

// return JSON for AJAX
echo json_encode(["success"=>true, "latest"=>$latest]);
