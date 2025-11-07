<?php
include('../partials-front/constantsss.php');
if (session_status() === PHP_SESSION_NONE) session_start();

// Check authentication
$isAdmin = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
$teacherId = $_SESSION['teacher_id'] ?? null;

if (!$isAdmin && !$teacherId) {
    echo "<h3 style='color:red; text-align:center;'>⚠️ Unauthorized access.</h3>";
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    echo "<h3 style='color:red; text-align:center;'>⚠️ Invalid or missing student ID in the URL.</h3>";
    exit;
}

// Fetch student info
$studentStmt = $conn->prepare("
    SELECT id, CONCAT(surname, ', ', name, ' ', middle_name, '.') AS full_name, grade, section, year
    FROM student
    WHERE id = ?
");
$studentStmt->bind_param("i", $id);
$studentStmt->execute();
$studentRes = $studentStmt->get_result();
if ($studentRes->num_rows === 0) {
    echo "<h3 style='color:red; text-align:center;'>❌ Student not found.</h3>";
    exit;
}
$student = $studentRes->fetch_assoc();

// subjects list
$subjects = [
    "English",
    "Filipino",
    "Mathematics",
    "Science",
    "Araling Panlipunan (Social Studies)",
    "Edukasyon sa Pagpapakatao (EsP)",
    "Christian Living Education",
    "MAPEH",
    "Music",
    "Arts",
    "Physical Education",
    "Health",
    "Edukasyong Pantahanan at Pangkabuhayan (EPP)"
];
$mapeh_subjects = ["Music", "Arts", "Physical Education", "Health"];

// Fetch saved quarterly grades from grades3: subject, quarter, quarterly
$gstmt = $conn->prepare("SELECT subject, quarter, quarterly FROM grades3 WHERE student_id = ?");
$gstmt->bind_param("i", $id);
$gstmt->execute();
$gres = $gstmt->get_result();

// Build structure: gradesMap[subject][quarter] = quarterly
$gradesMap = [];
while ($r = $gres->fetch_assoc()) {
    $subj = $r['subject'];
    $q = (int)$r['quarter'];
    $val = is_null($r['quarterly']) ? '-' : $r['quarterly'];
    if (!isset($gradesMap[$subj])) $gradesMap[$subj] = [];
    $gradesMap[$subj][$q] = $val;
}

// Get teacher's assigned subjects if not admin
$assignedSubjects = [];
if (!$isAdmin && $teacherId) {
    $assignSql = "
        SELECT DISTINCT subject 
        FROM assign_teacher 
        WHERE teacher_id = ? AND grade = ? AND section = ? AND year = ?
    ";
    $astmt = $conn->prepare($assignSql);
    $astmt->bind_param("isss", $teacherId, $student['grade'], $student['section'], $student['year']);
    $astmt->execute();
    $ares = $astmt->get_result();
    while ($row = $ares->fetch_assoc()) {
        $subject = $row['subject'];
        $assignedSubjects[] = $subject;
        // If assigned MAPEH, add all MAPEH subjects
        if (strtoupper($subject) === 'MAPEH') {
            $assignedSubjects = array_merge($assignedSubjects, $mapeh_subjects);
        }
    }
    $astmt->close();
}

// Filter subjects based on assignments
$displaySubjects = $isAdmin ? $subjects : array_intersect($subjects, $assignedSubjects);
?>
<!DOCTYPE html>
<html>
<head>
<title>View Grades - <?= htmlspecialchars($student['full_name']) ?></title>
<link rel="stylesheet" href="../css/viewg.css">
<style>
    table { width:100%; border-collapse:collapse; margin-top:20px; }
    th, td { border:1px solid #444; padding:8px; text-align:center; }
    th { background:#f30000ff; color:white; }
    .passed { color:green; font-weight:bold; }
    .failed { color:red; font-weight:bold; }
    .overall-row td { font-weight:bold; background:#f9f9f9; }
    .mapeh-row { background:#f0f0f0; font-weight:bold; }
</style>
</head>
<body>
<div class="container">
    <button onclick="window.print()">🖨 Print Grades</button>
    <h2><?= htmlspecialchars($student['full_name']) ?> - Grades</h2>
    <p><strong>Grade:</strong> <?= htmlspecialchars($student['grade']) ?> |
       <strong>Section:</strong> <?= htmlspecialchars($student['section']) ?> |
       <strong>Year:</strong> <?= htmlspecialchars($student['year']) ?></p>

    <table>
        <tr>
            <th>Subject</th>
            <th>Q1</th>
            <th>Q2</th>
            <th>Q3</th>
            <th>Q4</th>
            <th>Average</th>
            <th>Remarks</th>
        </tr>

        <?php
        $totalSum = 0;
        $totalSubjects = 0;

        foreach ($displaySubjects as $subj) {
            // Only continue if admin or subject is assigned
            if (!$isAdmin && !in_array($subj, $assignedSubjects)) {
                continue;
            }
            
            if ($subj === "MAPEH") {
                // Compute MAPEH from subsubjects (quarterly)
                $m_q = [1=>0,2=>0,3=>0,4=>0];
                $count = 0;
                foreach ($mapeh_subjects as $msub) {
                    if (isset($gradesMap[$msub])) {
                        $count++;
                        for ($q=1;$q<=4;$q++) {
                            $val = $gradesMap[$msub][$q] ?? null;
                            if (is_numeric($val)) $m_q[$q] += $val;
                        }
                    }
                }
                if ($count > 0) {
                    for ($q=1;$q<=4;$q++) $m_q[$q] = $m_q[$q] / $count;
                    $m_avg = round(($m_q[1] + $m_q[2] + $m_q[3] + $m_q[4]) / 4, 2);
                    $m_remarks = ($m_avg < 75) ? "Failed" : "Passed";
                } else {
                    // ensure keys 1..4 so subsequent accesses like $m_q[4] do not trigger undefined key warnings
                    $m_q = [1 => '-', 2 => '-', 3 => '-', 4 => '-'];
                    $m_avg = '-';
                    $m_remarks = '-';
                }
                echo "<tr class='mapeh-row'>
                        <td><strong>MAPEH</strong></td>
                        <td>" . (is_numeric($m_q[1]) ? round($m_q[1],2) : '-') . "</td>
                        <td>" . (is_numeric($m_q[2]) ? round($m_q[2],2) : '-') . "</td>
                        <td>" . (is_numeric($m_q[3]) ? round($m_q[3],2) : '-') . "</td>
                        <td>" . (is_numeric($m_q[4]) ? round($m_q[4],2) : '-') . "</td>
                        <td>$m_avg</td>
                        <td class='" . strtolower($m_remarks) . "'>$m_remarks</td>
                      </tr>";
                if (is_numeric($m_avg)) { $totalSum += $m_avg; $totalSubjects++; }
                continue;
            }

            // normal subjects
            $q1 = $gradesMap[$subj][1] ?? '-';
            $q2 = $gradesMap[$subj][2] ?? '-';
            $q3 = $gradesMap[$subj][3] ?? '-';
            $q4 = $gradesMap[$subj][4] ?? '-';

            $avg = '-';
            $remarks = '-';
            if (is_numeric($q1) && is_numeric($q2) && is_numeric($q3) && is_numeric($q4)) {
                $avg = round(($q1 + $q2 + $q3 + $q4) / 4, 2);
                $remarks = ($avg < 75) ? "Failed" : "Passed";
                
                // Only add to total if not a MAPEH sub-subject
                if (!in_array($subj, $mapeh_subjects)) {
                    $totalSum += $avg;
                    $totalSubjects++;
                }
            }

            echo "<tr>
                    <td>" . htmlspecialchars($subj) . "</td>
                    <td>" . (is_numeric($q1) ? $q1 : '-') . "</td>
                    <td>" . (is_numeric($q2) ? $q2 : '-') . "</td>
                    <td>" . (is_numeric($q3) ? $q3 : '-') . "</td>
                    <td>" . (is_numeric($q4) ? $q4 : '-') . "</td>
                    <td>" . ($avg !== '-' ? $avg : '-') . "</td>
                    <td class='" . strtolower($remarks) . "'>" . ($remarks !== '-' ? $remarks : '-') . "</td>
                  </tr>";
        }

        // Only show overall if admin or has MAPEH assignment
        $showOverall = $isAdmin || (in_array('MAPEH', $assignedSubjects));
        if ($showOverall) {
            $overall = $totalSubjects > 0 ? round($totalSum / $totalSubjects, 2) : 0;
            ?>
            <tr class="overall-row">
                <td colspan="5" style="text-align:right;"><strong>Overall Average:</strong></td>
                <td colspan="2"><strong><?= $overall ?></strong></td>
            </tr>
            <?php
        }
        ?>
    </table>
    
    <?php if (!$isAdmin): ?>
    <p style="text-align:center;color:#666;margin-top:20px;">

    </p>
    <?php endif; ?>
</div>
</body>
</html>
