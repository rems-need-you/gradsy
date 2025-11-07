<?php
include('../admin/partials/constants.php');
if (session_status() === PHP_SESSION_NONE) session_start();

$isAdmin = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
$teacherId = $_SESSION['teacher_id'] ?? null;

if (!$isAdmin && !$teacherId) {
    echo "<h3 style='color:red;text-align:center;'>⚠️ Unauthorized access.</h3>";
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    echo "<h3 style='color:red;text-align:center;'>⚠️ Invalid or missing student ID.</h3>";
    exit;
}

// Fetch student info
$studentStmt = $conn->prepare("
    SELECT id, CONCAT(surname, ', ', name, ' ', middle_name, '.') AS full_name, grade, section, year
    FROM student WHERE id = ?
");
$studentStmt->bind_param("i", $id);
$studentStmt->execute();
$studentRes = $studentStmt->get_result();
if ($studentRes->num_rows === 0) {
    echo "<h3 style='color:red;text-align:center;'>❌ Student not found.</h3>";
    exit;
}
$student = $studentRes->fetch_assoc();

// Subjects list
$subjects = [
    "English","Filipino","Mathematics","Science",
    "Araling Panlipunan (Social Studies)","Edukasyon sa Pagpapakatao (EsP)",
    "Christian Living Education","MAPEH","Music","Arts","Physical Education","Health",
    "Edukasyong Pantahanan at Pangkabuhayan (EPP)"
];
$mapeh_subjects = ["Music","Arts","Physical Education","Health"];

// Grades
$gstmt = $conn->prepare("SELECT subject, quarter, quarterly FROM grades3 WHERE student_id = ?");
$gstmt->bind_param("i", $id);
$gstmt->execute();
$gres = $gstmt->get_result();
$gradesMap = [];
while ($r = $gres->fetch_assoc()) {
    $gradesMap[$r['subject']][(int)$r['quarter']] = $r['quarterly'];
}

// Assigned subjects for teachers
$assignedSubjects = [];
if (!$isAdmin && $teacherId) {
    $astmt = $conn->prepare("
        SELECT DISTINCT subject FROM assign_teacher 
        WHERE teacher_id = ? AND grade = ? AND section = ? AND year = ?
    ");
    $astmt->bind_param("isss", $teacherId, $student['grade'], $student['section'], $student['year']);
    $astmt->execute();
    $ares = $astmt->get_result();
    while ($row = $ares->fetch_assoc()) {
        $assignedSubjects[] = $row['subject'];
        if (strtoupper($row['subject']) === 'MAPEH') {
            $assignedSubjects = array_merge($assignedSubjects, $mapeh_subjects);
        }
    }
}

$displaySubjects = $isAdmin ? $subjects : array_intersect($subjects, $assignedSubjects);
?>
<!DOCTYPE html>
<html>
<head>
<title>View Grades - <?= htmlspecialchars($student['full_name']) ?></title>
<link rel="stylesheet" href="../css/viewg.css">
</head>
<body>
<div class="container">
    <button onclick="window.print()">🖨 Print Grades</button>
    <h2><?= htmlspecialchars($student['full_name']) ?></h2>
    <p><strong>Grade:</strong> <?= htmlspecialchars($student['grade']) ?> |
       <strong>Section:</strong> <?= htmlspecialchars($student['section']) ?> |
       <strong>Year:</strong> <?= htmlspecialchars($student['year']) ?></p>

    <table border="1" width="100%">
        <tr>
            <th>Subject</th><th>Q1</th><th>Q2</th><th>Q3</th><th>Q4</th><th>Average</th><th>Remarks</th>
        </tr>
        <?php
        $totalSum = 0; $totalSubjects = 0;
        foreach ($displaySubjects as $subj) {
            $q = [];
            for ($i=1;$i<=4;$i++) $q[$i] = $gradesMap[$subj][$i] ?? '-';
            if (is_numeric($q[1]) && is_numeric($q[2]) && is_numeric($q[3]) && is_numeric($q[4])) {
                $avg = round(($q[1]+$q[2]+$q[3]+$q[4])/4,2);
                $remarks = $avg < 75 ? "Failed" : "Passed";
                $totalSum += $avg; $totalSubjects++;
            } else {
                $avg = '-'; $remarks = '-';
            }
            echo "<tr>
                <td>$subj</td>
                <td>{$q[1]}</td><td>{$q[2]}</td><td>{$q[3]}</td><td>{$q[4]}</td>
                <td>$avg</td><td>$remarks</td>
            </tr>";
        }
        if ($isAdmin) {
            $overall = $totalSubjects > 0 ? round($totalSum/$totalSubjects,2) : '-';
            echo "<tr><td colspan='5' align='right'><strong>Overall:</strong></td><td colspan='2'>$overall</td></tr>";
        }
        ?>
    </table>
</div>
</body>
</html>
