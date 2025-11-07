<?php
include('../admin/partials/constants.php');

// ✅ Validate student ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    echo "<h3 style='color:red;text-align:center;'>⚠️ Invalid or missing student ID.</h3>";
    exit;
}

// ✅ Fetch student info
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

// --- START: MODIFIED SUBJECT ORDER AND GRADES HANDLING ---

// Listahan ng mga component na dapat i-average at i-display
$mapeh_components = ["Music", "Arts", "Physical Education", "Health"];

// Base list of core subjects (excluding EPP and MAPEH components)
$coreSubjects = [
    "English", "Filipino", "Mathematics", "Science",
    "Araling Panlipunan (Social Studies)", "Edukasyon sa Pagpapakatao (EsP)",
    "Christian Living Education"
];


// ✅ Fetch student grades
$gstmt = $conn->prepare("SELECT subject, quarter, quarterly FROM grades3 WHERE student_id = ?");
$gstmt->bind_param("i", $id);
$gstmt->execute();
$gres = $gstmt->get_result();

$gradesMap = [];
$rawMapehGrades = []; 

while ($r = $gres->fetch_assoc()) {
    $subject = $r['subject'];
    $quarter = (int)$r['quarter'];
    $grade = $r['quarterly'];

    // 1. Store grades for ALL subjects in the main map for display
    $gradesMap[$subject][$quarter] = $grade;

    // 2. Store raw grades for MAPEH component calculation
    if (in_array($subject, $mapeh_components)) {
        $rawMapehGrades[$subject][$quarter] = $grade;
    }
}


// --- CALCULATION AND INSERTION FOR MAPEH ---

for ($q = 1; $q <= 4; $q++) {
    $componentGrades = [];
    foreach ($mapeh_components as $m_subj) {
        $grade = $rawMapehGrades[$m_subj][$q] ?? 0;
        if (is_numeric($grade)) {
            $componentGrades[] = $grade;
        }
    }
    
    $componentCount = count($componentGrades);

    if ($componentCount > 0) {
        $q_sum = array_sum($componentGrades);
        $gradesMap['MAPEH'][$q] = round($q_sum / $componentCount, 2);
    } else {
        $gradesMap['MAPEH'][$q] = '-';
    }
}


// --- FINAL SUBJECT ORDER ---

$finalDisplaySubjects = array_merge(
    $coreSubjects,                                                // 1. Core subjects (English hanggang CLE)
    ['MAPEH'],                                                    // 2. Calculated MAPEH Average
    $mapeh_components,                                            // 3. The 4 components
    ['Edukasyong Pantahanan at Pangkabuhayan (EPP)']              // 4. EPP at the very bottom
);

// --- FETCH EXTRACURRICULAR PERCENT (SUM ALL PARTICIPATIONS) ---
$extraPercent = 0.0;
$extraStmt = $conn->prepare("SELECT SUM(percent) AS total_percent FROM participations WHERE student_id = ?");
$extraStmt->bind_param("i", $id);
$extraStmt->execute();
$extraRes = $extraStmt->get_result();
if ($extraRes->num_rows > 0) {
    $r = $extraRes->fetch_assoc();
    // Sum of all percent values (e.g., 0.50 + 0.46 = 0.96)
    $extraPercent = floatval($r['total_percent']);
}


// --- END: MODIFIED SUBJECT ORDER AND GRADES HANDLING ---
?>
<!DOCTYPE html>
<html>
<head>
<title>View Grades - <?= htmlspecialchars($student['full_name']) ?></title>
<link rel="stylesheet" href="../css/adminviewg.css">
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
        
        // Loop through the $finalDisplaySubjects list to calculate BASE Overall Average
        foreach ($finalDisplaySubjects as $subj) {
            $q = [];
            
            // Fetch all 4 quarter grades
            for ($i=1;$i<=4;$i++) {
                 $q[$i] = $gradesMap[$subj][$i] ?? '-';
            }
            
            // Calculate the subject's final average and remarks for display
            if (is_numeric($q[1]) && is_numeric($q[2]) && is_numeric($q[3]) && is_numeric($q[4])) {
                $avg = round(($q[1]+$q[2]+$q[3]+$q[4])/4,2);
                $remarks = $avg < 75 ? "Failed" : "Passed";
                
                // --- CRITICAL: BASE AVERAGE CALCULATION ---
                if (!in_array($subj, $mapeh_components)) {
                    $totalSum += $avg; 
                    $totalSubjects++;
                }
                
            } else {
                $avg = '-'; 
                $remarks = '-';
            }
            
            // Set style for the rows
            $rowStyle = '';
            if ($subj === 'MAPEH') {
                 $rowStyle = "style='font-weight: bold; background-color: #f0f8ff;'"; 
            } elseif (in_array($subj, $mapeh_components)) {
                 $rowStyle = "style='font-style: italic; color: #555;'";
            }
            
            echo "<tr $rowStyle>
                <td>$subj</td>
                <td>{$q[1]}</td><td>{$q[2]}</td><td>{$q[3]}</td><td>{$q[4]}</td>
                <td>$avg</td><td>$remarks</td>
            </tr>";
        }
        
        // Final Overall Average Calculation (BASE)
        $baseOverall = $totalSubjects > 0 ? round($totalSum/$totalSubjects, 2) : 0;
        
        // --- ADJUSTED OVERALL AVERAGE CALCULATION (DIRECT ADDITION) ---
        $finalOverall = round($baseOverall + $extraPercent, 2);
        
        $remarksOverall = $finalOverall < 75 ? "Failed" : "Passed";

        // ================================================================
        // ✅ NEW LOGIC: UPDATE 'average' column in 'student' table
        // ================================================================

        $updateStmt = $conn->prepare("UPDATE student SET average = ? WHERE id = ?");
        $updateStmt->bind_param("di", $baseOverall, $id);
        $updateStmt->execute();
        
        if ($updateStmt->error) {
             // Optional: Log error if the update failed
             // echo "<p style='color:red;'>Database Update Error: " . $updateStmt->error . "</p>";
        }

        // ================================================================

        // Display the Base Overall Average (for transparency)
        echo "<tr><td colspan='5' align='right'><strong>Base Overall Average:</strong></td><td colspan='2'>$baseOverall</td></tr>";

        // Display the Adjusted Overall Average (Final Grade)
        echo "<tr><td colspan='5' align='right'><strong>Final Grade with Extracurricular:</strong></td><td colspan='2'>$finalOverall</td></tr>";
        ?>
    </table>
    
    <?php if ($extraPercent != 0): ?>
    <div style="margin-top: 15px; font-size: 0.9em;">
        * Note: The extracurricular bonus/deduction (**<?= number_format($extraPercent, 2) ?>**) has been <strong>added</strong> to the student's final grade and saved to the database.
    </div>
    <?php endif; ?>

</div>
</body>
</html>