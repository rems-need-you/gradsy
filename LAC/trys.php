<?php
include('../partials-front/constantsss.php');
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['teacher_id'])) {
    header("Location: ../login.php");
    exit;
}

$teacher_id = $_SESSION['teacher_id'];
if (!isset($_GET['assign_id'])) {
    echo "<h2 style='color:red;text-align:center;'>❌ No class selected.</h2>";
    exit;
}

$assign_id = (int) $_GET['assign_id'];

// Fetch assignment info
$assign_sql = "
    SELECT a.subject, a.grade, a.section, a.year, t.name AS teacher_name
    FROM assign_teacher a
    INNER JOIN teacher_account t ON a.teacher_id = t.id
    WHERE a.id = ? AND a.teacher_id = ?
";
$stmt = $conn->prepare($assign_sql);
$stmt->bind_param("ii", $assign_id, $teacher_id);
$stmt->execute();
$assign = $stmt->get_result()->fetch_assoc();

if (!$assign) {
    echo "<h2 style='color:red;text-align:center;'>❌ Invalid or unauthorized class access.</h2>";
    exit;
}

$subject = $assign['subject'];
$grade = (string)$assign['grade'];
$section = (string)$assign['section'];
$year = (string)$assign['year'];
$teacher_name = $assign['teacher_name'];

// If the subject is MAPEH, show all sub-subjects
$subjects_list = [];
if (strtoupper($subject) === 'MAPEH' || in_array($subject, ['Music', 'Arts', 'Physical Education', 'Health'])) {
    $subjects_list = ['Music', 'Arts', 'Physical Education', 'Health'];
} else {
    $subjects_list = [$subject];
}

// Fetch students in that section
$students_sql = "
    SELECT id AS student_id,
           CONCAT(surname, ', ', name, ' ', middle_name, '.') AS student_name
    FROM student
    WHERE grade = ? AND section = ? AND year = ?
    ORDER BY surname ASC, name ASC
";
$stmt = $conn->prepare($students_sql);
$stmt->bind_param("sss", $grade, $section, $year);
$stmt->execute();
$students_result = $stmt->get_result();

// Collect all student IDs and reset the pointer
$students_data = [];
$student_ids = [];
while ($row = $students_result->fetch_assoc()) {
    $students_data[] = $row;
    $student_ids[] = $row['student_id'];
}
$students_result->data_seek(0); // Reset pointer for the display loop

// Helper function for dynamic bind_param
function ref_values($arr){
    $refs = array();
    foreach($arr as $key => $value)
        $refs[$key] = &$arr[$key];
    return $refs;
}

// --- Fetch existing grades for all students in the class ---
$existing_grades = [];
if (!empty($student_ids)) {
    $id_placeholders = implode(',', array_fill(0, count($student_ids), '?'));
    $subject_placeholders = implode(',', array_fill(0, count($subjects_list), '?'));
    
    // Prepare types for student IDs (i) and subjects (s)
    $bind_types = str_repeat('i', count($student_ids)) . str_repeat('s', count($subjects_list));

    $grade_fetch_sql = "
        SELECT student_id, subject, quarter, written_ps, performance_ps, qa_ps, written_ws, performance_ws, qa_ws, initial, quarterly
        FROM grades3
        WHERE student_id IN ($id_placeholders) AND subject IN ($subject_placeholders)
    ";
    $gstmt = $conn->prepare($grade_fetch_sql);

    // Bind student IDs followed by subjects
    $bind_params = array_merge($student_ids, $subjects_list);
    
    // Use call_user_func_array for dynamic binding
    $gstmt_params = array_merge([$bind_types], $bind_params);
    call_user_func_array([$gstmt, 'bind_param'], ref_values($gstmt_params));
    
    $gstmt->execute();
    $gresult = $gstmt->get_result();

    // Structure: $existing_grades[subject][student_id][quarter] = grade_data
    while ($grade_row = $gresult->fetch_assoc()) {
        $existing_grades
            [$grade_row['subject']]
            [$grade_row['student_id']]
            [(int)$grade_row['quarter']] = $grade_row;
    }
}
// --- END NEW CODE ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Grade Sheet - <?= htmlspecialchars($subject) ?></title>
<link rel="stylesheet" href="../css/gs.css">
</head>
<body>
<div class="container">
    <a href="LACeditgrades.php?assign_id=<?= $assign_id ?>" class="back-btn">← Back</a>
    <h1>Grade Sheet <?= (count($subjects_list) > 1 ? "" : "") ?></h1>
    <h3>
        Teacher: <?= htmlspecialchars($teacher_name) ?> |
        Grade: <?= htmlspecialchars($grade) ?> |
        Section: <?= htmlspecialchars($section) ?> |
        Year: <?= htmlspecialchars($year) ?>
    </h3>

    <?php if (!empty($students_data)): ?>
        <?php foreach ($subjects_list as $sub): ?>
            <div class="quarter-block" data-subject="<?= htmlspecialchars($sub) ?>">
                <h2><?= htmlspecialchars($sub) ?></h2>
                <div class="controls">
                    <label for="quarter-select-<?= $sub ?>">Select Quarter:</label>
                    <select class="quarter-select" id="quarter-select-<?= $sub ?>">
                        <option value="1">Q1</option>
                        <option value="2">Q2</option>
                        <option value="3">Q3</option>
                        <option value="4">Q4</option>
                    </select>
                    <button class="saveQuarterBtn">💾 Save Quarter</button>
                    <span class="save-status" style="margin-left:12px;color:green;display:none;">Saved ✓</span>
                </div>

                <table class="gradeTable">
                    <thead>
                        <tr>
                            <th rowspan="2">Student Name</th>
                            <th colspan="2">Written Works (20%)</th>
                            <th colspan="2">Performance Tasks (60%)</th>
                            <th colspan="2">Quarterly Assessment (20%)</th>
                            <th rowspan="2">Initial Grade</th>
                            <th rowspan="2">Quarterly Grade</th>
                        </tr>
                        <tr>
                            <th>PS</th><th>WS</th>
                            <th>PS</th><th>WS</th>
                            <th>PS</th><th>WS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students_data as $row): 
                            $student_id = $row['student_id'];
                            $student_subject_grades = $existing_grades[$sub][$student_id] ?? [];
                        ?>
                            <tr data-student="<?= $student_id ?>" data-grades='<?= htmlspecialchars(json_encode($student_subject_grades)) ?>'>
                                <td><?= htmlspecialchars($row['student_name']) ?></td>
                                <!-- allow typing 0-100; on blur convert <=60 to 0 -->
                                <td><input type="number" class="ps written" min="0" max="100" oninput="validateGrade(this)" onblur="enforceMin(this)"></td>
                                <td class="ws written_ws">-</td>
                                <td><input type="number" class="ps performance" min="0" max="100" oninput="validateGrade(this)" onblur="enforceMin(this)"></td>
                                <td class="ws performance_ws">-</td>
                                <td><input type="number" class="ps qa" min="0" max="100" oninput="validateGrade(this)" onblur="enforceMin(this)"></td>
                                <td class="ws qa_ws">-</td>
                                <td class="initial">-</td>
                                <td class="quarterly">-</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p style="text-align:center;">No students found for this class.</p>
    <?php endif; ?>
</div>

<script>
function validateGrade(input) {
    // Allow typing; remove non-numeric chars
    let value = input.value.replace(/[^0-9]/g, '');
    if (value === '') {
        input.value = '';
        updateRow(input.closest('tr'));
        return;
    }
    let num = parseInt(value, 10);
    if (isNaN(num)) {
        input.value = '';
        updateRow(input.closest('tr'));
        return;
    }
    // Cap at 100 immediately while typing
    if (num > 100) num = 100;
    input.value = num;
    updateRow(input.closest('tr'));
}

// On blur: convert any entered value <= 60 to 0; keep empty as empty
function enforceMin(input) {
    const v = input.value.replace(/[^0-9]/g, '');
    if (v === '') {
        input.value = '';
        updateRow(input.closest('tr'));
        return;
    }
    let num = parseInt(v, 10);
    if (isNaN(num)) {
        input.value = '';
        updateRow(input.closest('tr'));
        return;
    }
    if (num <= 60) {
        // below-or-equal 60 becomes 0 (displayed)
        num = 0;
    } else if (num > 100) {
        num = 100;
    }
    input.value = num;
    updateRow(input.closest('tr'));
}

function updateRow(row) {
    const wPS = parseFloat(row.querySelector('.written')?.value);
    const pPS = parseFloat(row.querySelector('.performance')?.value);
    const qPS = parseFloat(row.querySelector('.qa')?.value);

    // treat empty as 0 for calculation
    const vW = isNaN(wPS) ? 0 : wPS;
    const vP = isNaN(pPS) ? 0 : pPS;
    const vQ = isNaN(qPS) ? 0 : qPS;

    // Any value <= 60 becomes 0% for calculations, otherwise use the entered value
    const toPercent = (g) => (g <= 60 ? 0 : g);

    const wWS = (toPercent(vW) / 100) * 20;
    const pWS = (toPercent(vP) / 100) * 60;
    const qWS = (toPercent(vQ) / 100) * 20;
    const initial = wWS + pWS + qWS;

    row.querySelector('.written_ws').textContent = isFinite(wWS) ? wWS.toFixed(2) : '';
    row.querySelector('.performance_ws').textContent = isFinite(pWS) ? pWS.toFixed(2) : '';
    row.querySelector('.qa_ws').textContent = isFinite(qWS) ? qWS.toFixed(2) : '';
    row.querySelector('.initial').textContent = isFinite(initial) ? initial.toFixed(2) : '';
    row.querySelector('.quarterly').textContent = Math.round(initial) || 0;
}

function loadQuarter(subjectBlock, quarter) {
    const rows = subjectBlock.querySelectorAll('tbody tr');
    rows.forEach(row => {
        // The data-grades attribute is a JSON string of all quarters' grades for this student/subject
        const gradesData = JSON.parse(row.dataset.grades);
        const grade = gradesData[quarter] || {};
        
        // Use existing PS values or default to empty string for input field
        row.querySelector('.written').value = grade.written_ps ?? '';
        row.querySelector('.performance').value = grade.performance_ps ?? '';
        row.querySelector('.qa').value = grade.qa_ps ?? '';

        // Recalculate and display the WS, Initial, and Quarterly grades
        updateRow(row); 
    });
    
    // Hide 'Saved' indicator when switching quarters
    subjectBlock.querySelector('.save-status').style.display = 'none';
}

document.addEventListener('DOMContentLoaded', () => {
    // 1. Setup Input Event Listeners for live calculation
    document.querySelectorAll('.ps').forEach(input => {
        input.addEventListener('input', () => updateRow(input.closest('tr')));
    });

    // 2. Setup Quarter Selection and Initial Load
    document.querySelectorAll('.quarter-block').forEach(subjectBlock => {
        const select = subjectBlock.querySelector('.quarter-select');
        
        // Initial load for the selected quarter (default Q1)
        loadQuarter(subjectBlock, select.value);

        // Add change listener to load grades on quarter change
        select.addEventListener('change', (e) => {
            loadQuarter(subjectBlock, parseInt(e.target.value));
        });
    });

    // 3. Setup Save Button Listener
    document.querySelectorAll('.saveQuarterBtn').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const subjectBlock = btn.closest('.quarter-block');
            // Use the selected quarter from the dropdown
            const quarter = parseInt(subjectBlock.querySelector('.quarter-select').value); 
            const subject = subjectBlock.dataset.subject;
            const teacherId = <?= json_encode($teacher_id) ?>;
            const assignId = <?= json_encode($assign_id) ?>;
            const grade = <?= json_encode($grade) ?>;
            const section = <?= json_encode($section) ?>;
            const year = <?= json_encode($year) ?>;

            if (isNaN(quarter) || quarter < 1 || quarter > 4) {
                alert("❌ Invalid quarter selected.");
                return;
            }

            // collect rows
            const rows = subjectBlock.querySelectorAll('tbody tr');
            const data = [];
            rows.forEach(row => {
                data.push({
                    student_id: parseInt(row.dataset.student),
                    // Use ?? 0 to ensure the values sent are numbers, even if input is empty
                    written_ps: parseFloat(row.querySelector('.written')?.value || 0),
                    performance_ps: parseFloat(row.querySelector('.performance')?.value || 0),
                    qa_ps: parseFloat(row.querySelector('.qa')?.value || 0)
                });
            });

            // basic client-side validation
            if (data.length === 0) {
                alert("No students to save.");
                return;
            }

            btn.disabled = true;
            btn.textContent = 'Saving...';

            try {
                const res = await fetch("save_quarter.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({
                        teacher_id: teacherId,
                        subject: subject,
                        grade: grade,
                        section: section,
                        year: year,
                        quarter: quarter,
                        data: data
                    })
                });

                const result = await res.json();
                if (result.success) {
                    alert(result.message);
                    // Update data-grades attribute and reload the current quarter to reflect new WS/Initial/Quarterly if needed
                    // (The server calculates the final grades, but the client recalculates for consistency)
                    // For simplicity and to reflect persistence, we will just show the saved indicator.
                    subjectBlock.querySelector('.save-status').style.display = 'inline';
                } else {
                    alert("❌ " + result.message);
                }
            } catch (err) {
                alert("⚠️ Error saving: " + err.message);
            } finally {
                btn.disabled = false;
                btn.textContent = '💾 Save Quarter';
                
                // Automatically hide the status after a few seconds
                setTimeout(() => {
                    subjectBlock.querySelector('.save-status').style.display = 'none';
                }, 3000);
            }
        });
    });
});
</script>

</body>
</html>