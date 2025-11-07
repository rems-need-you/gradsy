<?php
include('../partials-front/constantsss.php');

// Check if parent is logged in
if (!isset($_SESSION['parent_id'])) {
    echo "<script>alert('Unauthorized access. Please log in.'); window.location.href='parent-login.php';</script>";
    exit();
}

// Get the logged-in parent info
$parentId = $_SESSION['parent_id'];

// Fetch parent info
$parentSql = "SELECT student_id, StudentName FROM parent_account WHERE id = $parentId LIMIT 1";
$parentResult = $conn->query($parentSql);

if ($parentResult && $parentResult->num_rows > 0) {
    $parentRow = $parentResult->fetch_assoc();
    $studentId = $parentRow['student_id'];
    $studentName = $conn->real_escape_string($parentRow['StudentName']); // sanitize for SQL
} else {
    echo "<script>alert('Parent account not found.'); window.location.href='parent-login.php';</script>";
    exit();
}

// --- FETCH FINAL AVERAGE AND SUBJECTS FROM ADMIN RECORD ---
// Fetch all subjects in the same order as admin
$coreSubjects = [
    "English", "Filipino", "Mathematics", "Science",
    "Araling Panlipunan (Social Studies)", "Edukasyon sa Pagpapakatao (EsP)",
    "Christian Living Education"
];
$mapeh_components = ["Music", "Arts", "Physical Education", "Health"];
$finalDisplaySubjects = array_merge(
    $coreSubjects,
    ['MAPEH'],
    $mapeh_components,
    ['Edukasyong Pantahanan at Pangkabuhayan (EPP)']
);

// --- FETCH FINAL AVERAGE FROM ADMIN RECORD ---
// Also fetch grade, section, year for display (same as admin)
$studentStmt = $conn->prepare("SELECT average, grade, section, year FROM student WHERE id = ?");
$studentStmt->bind_param("i", $studentId);
$studentStmt->execute();
$studentRes = $studentStmt->get_result();
$finalAverage = '-';
$studentGrade = $studentSection = $studentYear = '-';
if ($studentRes && $studentRes->num_rows > 0) {
    $row = $studentRes->fetch_assoc();
    $finalAverage = round(floatval($row['average']), 2);
    $studentGrade = htmlspecialchars($row['grade']);
    $studentSection = htmlspecialchars($row['section']);
    $studentYear = htmlspecialchars($row['year']);
}
$studentStmt->close();

// --- Fetch grades and calculate MAPEH like admin ---
$gstmt = $conn->prepare("SELECT subject, quarter, quarterly FROM grades3 WHERE student_id = ? ORDER BY subject, quarter");
$gstmt->bind_param("i", $studentId);
$gstmt->execute();
$gres = $gstmt->get_result();

$gradesMap = [];
$rawMapehGrades = [];
while ($r = $gres->fetch_assoc()) {
    $subject = $r['subject'];
    $quarter = (int)$r['quarter'];
    $grade = $r['quarterly'];
    $gradesMap[$subject][$quarter] = $grade;
    if (in_array($subject, $mapeh_components)) {
        $rawMapehGrades[$subject][$quarter] = $grade;
    }
}
$gstmt->close();

// Calculate MAPEH per quarter
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

// --- FETCH SUM OF EXTRACURRICULAR PERCENT FOR FINAL GRADE ---
$extraPercent = 0.0;
$extraStmt = $conn->prepare("SELECT SUM(percent) AS total_percent FROM participations WHERE student_id = ?");
$extraStmt->bind_param("i", $studentId);
$extraStmt->execute();
$extraRes = $extraStmt->get_result();
if ($extraRes && $extraRes->num_rows > 0) {
    $extraPercent = floatval($extraRes->fetch_assoc()['total_percent']);
}
$extraStmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Student Grades - <?= htmlspecialchars($studentName) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../css/graph.css">
<style>
/* CSS for the Modal */
.modal {
    display: none; 
    position: fixed; 
    z-index: 1000; 
    left: 0;
    top: 0;
    width: 100%; 
    height: 100%; 
    overflow: auto; 
    background-color: rgba(0,0,0,0.4); 
}
.modal-content {
    background-color: #fefefe;
    margin: 5% auto; /* Adjusted margin to be higher */
    padding: 20px;
    border: 1px solid #888;
    width: 90%; 
    max-width: 950px; /* Increased max width to fit 7 columns */
    border-radius: 8px;
    position: relative;
}
.close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
}
.close:hover,
.close:focus {
    color: black;
    text-decoration: none;
    cursor: pointer;
}
.clickable-name {
    cursor: pointer;
    color: #007bff; /* Highlight as link */
    text-decoration: underline;
}
table { width:100%; border-collapse:collapse; margin-top:18px; }
th, td { border:1px solid #ddd; padding:8px; text-align:center; }
th { background: #030000ff;; color:#fff; }
.passed { color:green; font-weight:600; }
.failed { color:red; font-weight:600; }
.controls { margin-top:10px; }
#disciplinaryTable th, #extracurricularTable th {
    text-align: left;
    padding: 8px;
    border-bottom: 1px solid #ddd;
}
#disciplinaryTable td, #extracurricularTable td {
    padding: 8px;
    border-bottom: 1px solid #eee;
}
</style>
</head>
<body>
<header>
    <h1>Student Grades</h1>
</header>
<div class="container">
    <!-- Student info (same format as admin) -->
    <div style="margin-bottom:12px;">
        <strong>Student:</strong> <?= htmlspecialchars($studentName) ?> |
        <strong>ID:</strong> <?= htmlspecialchars($studentId) ?> |
        <strong>Grade:</strong> <?= $studentGrade ?> |
        <strong>Section:</strong> <?= $studentSection ?> |
        <strong>Year:</strong> <?= $studentYear ?>
        <span style="float:right;">
            <strong>+ Extracurricular:</strong> <?= $extraPercent ?>
        </span>
    </div>
    <!-- Grades table (same as admin format) -->
    <table aria-label="Student Grades">
        <thead>
            <tr>
                <th>Subject</th><th>Q1</th><th>Q2</th><th>Q3</th><th>Q4</th><th>Average</th><th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $totalSum = 0; $totalSubjects = 0;
            $baseOverall = 0;
            $mapehAvg = '-';
            foreach ($finalDisplaySubjects as $subject) {
                $q = [];
                for ($i = 1; $i <= 4; $i++) {
                    $q[$i] = $gradesMap[$subject][$i] ?? '-';
                }
                if (is_numeric($q[1]) && is_numeric($q[2]) && is_numeric($q[3]) && is_numeric($q[4])) {
                    $avg = round(($q[1]+$q[2]+$q[3]+$q[4])/4,2);
                    $remarks = $avg < 75 ? "Failed" : "Passed";
                    if ($subject === 'MAPEH') {
                        $mapehAvg = $avg;
                    }
                    if (!in_array($subject, $mapeh_components)) {
                        $totalSum += $avg;
                        $totalSubjects++;
                    }
                } else {
                    $avg = '-';
                    $remarks = '-';
                }
                $rowStyle = '';
                if ($subject === 'MAPEH') {
                    $rowStyle = "style='font-weight: bold; background-color: #f0f8ff;'";
                } elseif (in_array($subject, $mapeh_components)) {
                    $rowStyle = "style='font-style: italic; color: #555;'";
                }
                echo "<tr $rowStyle>
                    <td>$subject</td>
                    <td>{$q[1]}</td><td>{$q[2]}</td><td>{$q[3]}</td><td>{$q[4]}</td>
                    <td>$avg</td><td>$remarks</td>
                </tr>";
            }
            $baseOverall = $totalSubjects > 0 ? round($totalSum/$totalSubjects, 2) : 0;
            $finalOverall = round($baseOverall + $extraPercent, 2);
            ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5" style="text-align:right;"><strong>Base Overall Average:</strong></td>
                <td colspan="2"><?= $baseOverall ?></td>
            </tr>
            <tr>
                <td colspan="5" style="text-align:right;"><strong>Final Grade with Extracurricular:</strong></td>
                <td colspan="2"><?= $finalOverall ?></td>
            </tr>
        </tfoot>
    </table>
    <div class="controls">
        <button id="viewRecordsBtn">View Extracurricular & Disciplinary</button>
    </div>
</div>
<!-- Reuse modal for extracurricular / disciplinary (unchanged) -->
<div id="studentModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2 id="modalStudentName"></h2>
        
        <hr>
        
        <h3>Extracurricular Records 🏆</h3>
        <table id="extracurricularTable" style="width:100%;border-collapse:collapse;">
            <thead><tr><th>Activity</th><th>Category</th><th>Level</th><th>Rank</th><th>Percent</th><th>Date</th><th>Remarks</th></tr></thead>
            <tbody></tbody>
        </table>
        <p id="noExtracurricular" style="display:none;color:gray;">No extracurricular records found.</p>

        <hr>
        
        <h3>Disciplinary Records ⚠️</h3>
        <table id="disciplinaryTable" style="width:100%;border-collapse:collapse;">
            <thead><tr><th>Offense Type</th><th>Action Taken</th><th>Severity</th><th>Date Reported</th></tr></thead>
            <tbody></tbody>
        </table>
        <p id="noDisciplinary" style="display:none;color:gray;">No disciplinary records found.</p>
    </div>
</div>

<script>
// Minimal JS: hook the button to open modal and load details via existing endpoint
const studentModal = document.getElementById('studentModal');
const closeBtn = studentModal.querySelector('.close');

document.getElementById('viewRecordsBtn').addEventListener('click', () => {
    showStudentDetails(<?= (int)$studentId ?>, <?= json_encode($studentName) ?>);
});

closeBtn.addEventListener('click', () => { studentModal.style.display = 'none'; });
window.addEventListener('click', (e) => { if (e.target === studentModal) studentModal.style.display = 'none'; });

// Reuse existing showStudentDetails function (adapted from original file) 
async function showStudentDetails(studentId, studentName) {
    document.getElementById('modalStudentName').textContent = 'Details for ' + studentName;
    const extraBody = document.getElementById('extracurricularTable').getElementsByTagName('tbody')[0];
    const disciplineBody = document.getElementById('disciplinaryTable').getElementsByTagName('tbody')[0];

    extraBody.innerHTML = '<tr><td colspan="7">Loading...</td></tr>';
    disciplineBody.innerHTML = '<tr><td colspan="4">Loading...</td></tr>';
    document.getElementById('noExtracurricular').style.display = 'none';
    document.getElementById('noDisciplinary').style.display = 'none';

    studentModal.style.display = 'block';

    try {
        const response = await fetch(`get_student_details.php?id=${studentId}`);
        const data = await response.json();

        // extracurricular
        extraBody.innerHTML = '';
        if (data.extracurricular && data.extracurricular.length) {
            data.extracurricular.forEach(rec => {
                const remarks = rec.remarks ?? '';
                extraBody.innerHTML += `<tr>
                    <td>${rec.activity_title}</td>
                    <td>${rec.category}</td>
                    <td>${rec.level}</td>
                    <td>${rec.rank_position}</td>
                    <td>${rec.percent}</td>
                    <td>${rec.date_participated}</td>
                    <td>${remarks}</td>
                </tr>`;
            });
        } else {
            document.getElementById('noExtracurricular').style.display = 'block';
            extraBody.innerHTML = '';
        }

        // disciplinary
        disciplineBody.innerHTML = '';
        if (data.disciplinary && data.disciplinary.length) {
            data.disciplinary.forEach(rec => {
                disciplineBody.innerHTML += `<tr>
                    <td>${rec.offense_type}</td>
                    <td>${rec.action_taken}</td>
                    <td>${rec.severity}</td>
                    <td>${rec.date_reported}</td>
                </tr>`;
            });
        } else {
            document.getElementById('noDisciplinary').style.display = 'block';
            disciplineBody.innerHTML = '';
        }

    } catch (err) {
        console.error(err);
        extraBody.innerHTML = '<tr><td colspan="7" style="color:red;">Error loading data.</td></tr>';
        disciplineBody.innerHTML = '<tr><td colspan="4" style="color:red;">Error loading data.</td></tr>';
    }
}
</script>

</body>
</html>