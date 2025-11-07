<?php
include('../partials-front/constantsss.php');
if (session_status() === PHP_SESSION_NONE) session_start();

// Roles: admin vs lac (teacher)
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$isLac   = isset($_SESSION['role']) && $_SESSION['role'] === 'lac';

// Determine account id for department lookup:
// lac users typically have $_SESSION['id'] (lac_account.id); teacher accounts may use 'teacher_id'.
$acctId = $_SESSION['id'] ?? $_SESSION['teacher_id'] ?? null;

// Must be either admin or lac (teacher)
if (!$isAdmin && !$isLac) {
    echo "<h3 style='color:red; text-align:center;'>⚠️ Unauthorized access.</h3>";
    exit;
}
if (!$isAdmin && $isLac && !$acctId) {
    echo "<h3 style='color:red; text-align:center;'>⚠️ Unauthorized access (no account id).</h3>";
    exit;
}

// ✅ Get student ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    echo "<h3 style='color:red; text-align:center;'>⚠️ Invalid or missing student ID in the URL.</h3>";
    exit;
}

// ✅ Fetch student info
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
$studentStmt->close();

// ✅ Subjects list
$subjects = [
    "English", "Filipino", "Mathematics", "Science",
    "Araling Panlipunan (Social Studies)", "Edukasyon sa Pagpapakatao (EsP)",
    "Christian Living Education", "MAPEH", "Music", "Arts",
    "Physical Education", "Health", "Edukasyong Pantahanan at Pangkabuhayan (EPP)"
];
$mapeh_subjects = ["Music", "Arts", "Physical Education", "Health"];

// ✅ Fetch saved quarterly grades
$gstmt = $conn->prepare("SELECT subject, quarter, quarterly FROM grades3 WHERE student_id = ?");
$gstmt->bind_param("i", $id);
$gstmt->execute();
$gres = $gstmt->get_result();

// Build structure
$gradesMap = [];
while ($r = $gres->fetch_assoc()) {
    $subj = $r['subject'];
    $q = (int)$r['quarter'];
    $val = is_null($r['quarterly']) ? '-' : $r['quarterly'];
    if (!isset($gradesMap[$subj])) $gradesMap[$subj] = [];
    $gradesMap[$subj][$q] = $val;
}
$gstmt->close();

// --- ACCESS CONTROL: determine which subjects the LAC (or admin) can view ---
$assignedSubjects = [];

if ($isAdmin) {
    // admin sees all subjects
    $displaySubjects = $subjects;
} else {
    // LAC: determine department from lac_account (use acctId)
    $teacherDept = '';
    $deptStmt = $conn->prepare("SELECT department FROM lac_account WHERE id = ? LIMIT 1");
    if ($deptStmt) {
        $deptStmt->bind_param("i", $acctId);
        $deptStmt->execute();
        $deptRes = $deptStmt->get_result();
        if ($deptRes && $deptRes->num_rows > 0) {
            $drow = $deptRes->fetch_assoc();
            $teacherDept = trim($drow['department'] ?? '');
        }
        $deptStmt->close();
    }

    if (empty($teacherDept)) {
        echo "<h3 style='color:red; text-align:center;'>❌ Access Denied. Your department information is missing.</h3>";
        exit;
    }

    if (strcasecmp($teacherDept, 'MAPEH') === 0) {
        // MAPEH teachers see combined MAPEH and its components
        $assignedSubjects = array_merge(['MAPEH'], $mapeh_subjects);
    } else {
        // Non-MAPEH: match department string to subject label (case-insensitive)
        // Find best matching subject from subjects list
        foreach ($subjects as $s) {
            if (strcasecmp($s, $teacherDept) === 0) {
                $assignedSubjects[] = $s;
                break;
            }
        }
        // If no exact match found, try simpler containment (e.g., "Mathematics Dept" vs "Mathematics")
        if (empty($assignedSubjects)) {
            foreach ($subjects as $s) {
                if (stripos($teacherDept, $s) !== false || stripos($s, $teacherDept) !== false) {
                    $assignedSubjects[] = $s;
                    break;
                }
            }
        }
    }

    if (empty($assignedSubjects)) {
        echo "<h3 style='color:red; text-align:center;'>❌ Access Denied. Your department maps to no available subject.</h3>";
        exit;
    }

    // Final display list is intersection preserving order
    $displaySubjects = array_values(array_intersect($subjects, $assignedSubjects));
}

// Ensure MAPEH ordering if included
if (in_array('MAPEH', $displaySubjects)) {
    $displaySubjects = array_unique(array_merge(['MAPEH'], $mapeh_subjects));
}
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
            if ($subj === "MAPEH") {
                // Calculate MAPEH overall row (average of sub-subjects)
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
                    for ($q=1;$q<=4;$q++) $m_q[$q] = ($m_q[$q] > 0) ? $m_q[$q] / $count : '-';
                    
                    // Calculate final MAPEH average only using numeric quarterly grades
                    $numeric_grades = array_filter($m_q, 'is_numeric');
                    $q_count = count($numeric_grades);
                    $q_sum = array_sum($numeric_grades);
                    
                    if ($q_count > 0) {
                        $m_avg = round($q_sum / $q_count, 2);
                        $m_remarks = ($m_avg < 75) ? "Failed" : "Passed";
                    } else {
                        $m_avg = '-';
                        $m_remarks = '-';
                    }
                    
                } else {
                    $m_q = [1=>'-',2=>'-',3=>'-',4=>'-'];
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
                
                // Add MAPEH final average to overall average calculation
                if (is_numeric($m_avg)) { $totalSum += $m_avg; $totalSubjects++; }
                continue;
            }

            // Skip individual MAPEH sub-subjects if "MAPEH" is already processed, 
            // unless they are explicitly in the display list (which they should be for the teacher)
            if (in_array($subj, $mapeh_subjects) && in_array("MAPEH", $displaySubjects)) {
                // The MAPEH row already handles the average. We only show sub-subjects for clarity 
                // but exclude them from the overall sum to avoid double counting if MAPEH is also listed.
                // NOTE: If you only want the combined MAPEH row, you would 'continue' here.
            }


            $q1 = $gradesMap[$subj][1] ?? '-';
            $q2 = $gradesMap[$subj][2] ?? '-';
            $q3 = $gradesMap[$subj][3] ?? '-';
            $q4 = $gradesMap[$subj][4] ?? '-';

            $avg = '-';
            $remarks = '-';
            
            $valid_quarterly_grades = array_filter([$q1, $q2, $q3, $q4], 'is_numeric');
            $count_valid = count($valid_quarterly_grades);

            if ($count_valid > 0) {
                $avg = round(array_sum($valid_quarterly_grades) / $count_valid, 2);
                $remarks = ($avg < 75) ? "Failed" : "Passed";
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

            // Add subject average to overall average calculation, exclude individual MAPEH components 
            // if the main 'MAPEH' entry is present in displaySubjects
            if ($avg !== '-' && !in_array($subj, $mapeh_subjects)) {
                $totalSum += $avg;
                $totalSubjects++;
            }
        }

        $overall = $totalSubjects > 0 ? round($totalSum / $totalSubjects, 2) : 0;
        ?>
        <tr class="overall-row">
            <td colspan="5" style="text-align:right;">Overall Average:</td>
            <td colspan="2"><?= $overall ?></td>
        </tr>
    </table>

    <?php if (!$isAdmin): ?>
    <p style="text-align:center;color:#666;margin-top:20px;">
    </p>
    <?php endif; ?>
</div>
</body>
</html>

<?php
// --- FETCH EXTRACURRICULAR PERCENT ---
$extraPercent = 0.0;
$extraStmt = $conn->prepare("
    SELECT percent 
    FROM participations 
    WHERE student_id = ? 
    ORDER BY COALESCE(date_participated, created_at) DESC 
    LIMIT 1
");
$extraStmt->bind_param("i", $id);
$extraStmt->execute();
$extraRes = $extraStmt->get_result();
if ($extraRes && $extraRes->num_rows > 0) {
    $r = $extraRes->fetch_assoc();
    $raw = $r['percent'];
    $extraPercent = is_null($raw) ? 0.0 : floatval($raw);
    if ($extraPercent > 1) $extraPercent = $extraPercent / 100.0;
}
if (isset($extraStmt)) $extraStmt->close();
?>