<?php
// save_quarter.php
include('../partials-front/constantsss.php');
header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Invalid request method"]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);
if (!$input) {
    echo json_encode(["success" => false, "message" => "No input data received"]);
    exit;
}

// required fields
$teacher_id = (int)($input['teacher_id'] ?? 0);
$subject    = trim($input['subject'] ?? '');
$grade      = (string)($input['grade'] ?? '');
$section    = (string)($input['section'] ?? '');
$year       = (string)($input['year'] ?? '');
$quarter    = (int)($input['quarter'] ?? 0);
$data       = $input['data'] ?? [];

if (!$teacher_id || $subject === '' || $quarter < 1 || $quarter > 4 || !is_array($data) || count($data) === 0) {
    echo json_encode(["success" => false, "message" => "Missing or invalid required fields"]);
    exit;
}

// Start transaction
$conn->begin_transaction(MYSQLI_TRANS_START_READ_WRITE);

// --- NEW: dynamic column detection and INSERT build (replaces static $sql + prepare) ---
$dbname = null;
$resDb = $conn->query("SELECT DATABASE() AS dbname");
if ($resDb) {
    $rdb = $resDb->fetch_assoc();
    $dbname = $rdb['dbname'] ?? null;
    $resDb->free();
}

$existingCols = [];
$colsRes = $conn->query("SHOW COLUMNS FROM `grades3`");
if ($colsRes) {
    while ($c = $colsRes->fetch_assoc()) {
        $existingCols[] = $c['Field'];
    }
    $colsRes->free();
} else {
    // diagnostic and exit
    $err = $conn->error;
    @file_put_contents(__DIR__ . '/save_quarter_debug.log', "[".date('Y-m-d H:i:s')."] SHOW COLUMNS failed: {$err}\n", FILE_APPEND | LOCK_EX);
    echo json_encode(["success" => false, "message" => "Unable to inspect grades3 columns: " . $err]);
    exit;
}

// choose which "grade" column to use if present (handles schema variations)
$gradeColCandidates = ['grade', 'grade_level', 'level', 'grade_year'];
$gradeCol = null;
foreach ($gradeColCandidates as $c) {
    if (in_array($c, $existingCols, true)) { $gradeCol = $c; break; }
}

// desired columns and their types
$desiredCols = [
    'student_id'     => 'i',
    'teacher_id'     => 'i',
    'subject'        => 's',
    // 'grade' may not exist, we'll map to $gradeCol if found
    'section'        => 's',
    'year'           => 's',
    'quarter'        => 'i',
    'written_ps'     => 'd',
    'written_ws'     => 'd',
    'performance_ps' => 'd',
    'performance_ws' => 'd',
    'qa_ps'          => 'd',
    'qa_ws'          => 'd',
    'initial'        => 'd',
    'quarterly'      => 'i'
];

// assemble insert columns present in table (keep order)
$insertCols = [];
$bindTypeMap = [];
foreach ($desiredCols as $col => $type) {
    if ($col === 'grade') continue; // we handle via $gradeCol
    if ($col === 'subject' || in_array($col, $existingCols, true)) {
        $insertCols[] = $col;
        $bindTypeMap[] = $type;
    }
}
// if a candidate grade column exists, include it at appropriate position
if ($gradeCol !== null && in_array($gradeCol, $existingCols, true)) {
    // insert grade column after subject (or at position 3)
    $pos = array_search('subject', $insertCols);
    if ($pos === false) $insertCols[] = $gradeCol;
    else array_splice($insertCols, $pos+1, 0, [$gradeCol]);
    // assume grade column is string
    array_splice($bindTypeMap, ($pos === false ? count($bindTypeMap) : $pos+1), 0, ['s']);
}

// finalize placeholders and bind types
$placeholders = implode(',', array_fill(0, count($insertCols), '?'));
$colsList = implode(',', array_map(function($c){ return "`$c`"; }, $insertCols));
$bindTypes = implode('', $bindTypeMap);

// Build ON DUPLICATE UPDATE list (exclude key columns)
$keyCols = ['student_id', 'subject', 'quarter'];
$updatePairs = [];
foreach ($insertCols as $col) {
    if (in_array($col, $keyCols, true)) continue;
    $updatePairs[] = "`$col` = VALUES(`$col`)";
}
$updateSql = $updatePairs ? "ON DUPLICATE KEY UPDATE " . implode(', ', $updatePairs) : "";

// Compose SQL
$sql = "INSERT INTO `grades3` ($colsList) VALUES ($placeholders) $updateSql";

// validate prepared statement
$stmt = $conn->prepare($sql);
if (!$stmt) {
    $err = $conn->error;
    $errno = $conn->errno;
    $server = $conn->server_info ?? null;
    $host = $conn->host_info ?? null;
    $testPrepare = $conn->prepare("SELECT 1");
    $testErr = $testPrepare ? '' : $conn->error;
    $log = "[".date('Y-m-d H:i:s')."] Dynamic prepare failed in save_quarter.php\n"
         . "errno: {$errno}\nerror: {$err}\nserver: {$server}\nhost: {$host}\n"
         . "sql_preview: " . substr($sql,0,1000) . "\n\n";
    @file_put_contents(__DIR__ . '/save_quarter_debug.log', $log, FILE_APPEND | LOCK_EX);
    echo json_encode([
        "success" => false,
        "message" => "Prepare failed: " . ($err ?: 'Unknown error'),
        "errno" => $errno,
        "test_prepare_error" => $testErr,
        "log_file" => "LAC/save_quarter_debug.log"
    ]);
    exit;
}
// --- END NEW: dynamic column detection and INSERT build ---

$processed_student_ids = [];

foreach ($data as $row) {
    $student_id = (int)($row['student_id'] ?? 0);
    if ($student_id <= 0) continue;
    
    // Add debug logging
    @file_put_contents(__DIR__ . '/save_quarter_debug.log', 
        "[".date('Y-m-d H:i:s')."] Saving grade for student_id: $student_id, subject: $subject\n", 
        FILE_APPEND);
    
    $processed_student_ids[] = $student_id;

    $written_ps = floatval($row['written_ps'] ?? 0);
    $performance_ps = floatval($row['performance_ps'] ?? 0);
    $qa_ps = floatval($row['qa_ps'] ?? 0);

    $written_ws = ($written_ps / 100) * 20;
    $performance_ws = ($performance_ps / 100) * 60;
    $qa_ws = ($qa_ps / 100) * 20;
    $initial = $written_ws + $performance_ws + $qa_ws;
    $quarterly = (int) round($initial);

    // build values array in same order as $insertCols
    $values = [];
    foreach ($insertCols as $col) {
        switch ($col) {
            case 'student_id': $values[] = $student_id; break;
            case 'teacher_id': $values[] = $teacher_id; break;
            case 'subject': $values[] = $subject; break;
            case $gradeCol: $values[] = $grade; break;
            case 'section': $values[] = $section; break;
            case 'year': $values[] = $year; break;
            case 'quarter': $values[] = $quarter; break;
            case 'written_ps': $values[] = $written_ps; break;
            case 'written_ws': $values[] = $written_ws; break;
            case 'performance_ps': $values[] = $performance_ps; break;
            case 'performance_ws': $values[] = $performance_ws; break;
            case 'qa_ps': $values[] = $qa_ps; break;
            case 'qa_ws': $values[] = $qa_ws; break;
            case 'initial': $values[] = $initial; break;
            case 'quarterly': $values[] = $quarterly; break;
            default:
                // fallback: null for unexpected column
                $values[] = null;
        }
    }

    // dynamic bind_param via refs
    $bindParams = array_merge([$bindTypes], $values);
    $ok = call_user_func_array([$stmt, 'bind_param'], ref_values($bindParams));
    if (!$ok) {
        $stmt->close();
        $conn->rollback();
        echo json_encode(["success" => false, "message" => "Bind param failed: " . $stmt->error]);
        exit;
    }

    if (!$stmt->execute()) {
        $stmt->close();
        $conn->rollback();
        echo json_encode(["success" => false, "message" => "Execute failed: " . $stmt->error]);
        exit;
    }
}

$stmt->close();

// Commit the inserts/updates
$conn->commit();

// --- NEW CODE: Final Grade Calculation and Student Table Update (ONLY WHEN COMPLETE) ---
$processed_student_ids = array_unique($processed_student_ids);

// Define all subjects that contribute to the overall average
$all_subjects = [
    "English", "Filipino", "Mathematics", "Science", 
    "Araling Panlipunan (Social Studies)", "Edukasyon sa Pagpapakatao (EsP)", 
    "Christian Living Education", "Music", "Arts", "Physical Education", 
    "Health", "Edukasyong Pantahanan at Pangkabuhayan (EPP)"
];
$mapeh_subjects = ["Music", "Arts", "Physical Education", "Health"];
$non_mapeh_subjects = array_values(array_diff($all_subjects, $mapeh_subjects));

foreach ($processed_student_ids as $student_id) {
    // 1. Fetch all quarterly grades for all relevant subjects
    $subj_placeholders = implode(',', array_fill(0, count($all_subjects), '?'));
    $bind_types = 'i' . str_repeat('s', count($all_subjects));
    $bind_params = array_merge([$student_id], $all_subjects);

    $all_grades_sql = "SELECT subject, quarter, quarterly FROM grades3 WHERE student_id = ? AND subject IN ($subj_placeholders)";
    $agstmt = $conn->prepare($all_grades_sql);
    if (!$agstmt) {
        @file_put_contents(__DIR__ . '/save_quarter_debug.log', "[".date('Y-m-d H:i:s')."] prepare failed for all_grades_sql: " . $conn->error . "\n", FILE_APPEND);
        continue;
    }

    // Dynamic binding
    $agstmt_params = array_merge([$bind_types], $bind_params);
    call_user_func_array([$agstmt, 'bind_param'], ref_values($agstmt_params));
    $agstmt->execute();
    $agresult = $agstmt->get_result();

    $student_grades_map = []; // [subject][quarter] = grade
    while ($row = $agresult->fetch_assoc()) {
        $student_grades_map[$row['subject']][(int)$row['quarter']] = (int)$row['quarterly'];
    }
    $agstmt->close();

    // 2. CHECK COMPLETENESS: ensure every non-MAPEH subject has 4 quarters and every MAPEH sub-subject has 4 quarters
    $incomplete = false;

    // non-MAPEH subjects
    foreach ($non_mapeh_subjects as $subj) {
        $q_grades = $student_grades_map[$subj] ?? [];
        $count_q = 0;
        for ($q = 1; $q <= 4; $q++) if (isset($q_grades[$q])) $count_q++;
        if ($count_q !== 4) { $incomplete = true; break; }
    }

    // MAPEH sub-subjects
    if (!$incomplete) {
        foreach ($mapeh_subjects as $ms) {
            $q_grades = $student_grades_map[$ms] ?? [];
            $count_q = 0;
            for ($q = 1; $q <= 4; $q++) if (isset($q_grades[$q])) $count_q++;
            if ($count_q !== 4) { $incomplete = true; break; }
        }
    }

    if ($incomplete) {
        @file_put_contents(__DIR__ . '/save_quarter_debug.log', "[".date('Y-m-d H:i:s')."] Student {$student_id} incomplete - skipping overall average update\n", FILE_APPEND);
        continue; // skip update for this student until all subjects are complete
    }

    // 3. All required data present => compute per-subject averages and overall average (MAPEH aggregated)
    $total_sum = 0;
    $subject_count = 0;

    // non-MAPEH subjects: average of 4 quarters
    foreach ($non_mapeh_subjects as $subj) {
        $q_grades = $student_grades_map[$subj];
        $sum_q = 0;
        for ($q = 1; $q <= 4; $q++) $sum_q += $q_grades[$q];
        $subject_avg = round($sum_q / 4);
        $total_sum += $subject_avg;
        $subject_count++;
    }

    // MAPEH: for each quarter average the 4 MAPEH sub-subjects, then average the 4 quarter-averages
    $mapeh_q_avgs = [];
    for ($q = 1; $q <= 4; $q++) {
        $qsum = 0;
        foreach ($mapeh_subjects as $ms) {
            $qsum += $student_grades_map[$ms][$q];
        }
        $mapeh_q_avgs[$q] = $qsum / count($mapeh_subjects);
    }
    $mapeh_avg = round(array_sum($mapeh_q_avgs) / 4);
    $total_sum += $mapeh_avg;
    $subject_count++; // MAPEH counts as one subject

    // 4. Calculate Overall Average and update student table
    $overall_avg = 0;
    if ($subject_count > 0) {
        $overall_avg = round($total_sum / $subject_count, 2);
    }

    $update_sql = "UPDATE student SET average = ? WHERE id = ?";
    $ustmt = $conn->prepare($update_sql);
    if ($ustmt) {
        $ustmt->bind_param("di", $overall_avg, $student_id);
        $ustmt->execute();
        $ustmt->close();
        @file_put_contents(__DIR__ . '/save_quarter_debug.log', "[".date('Y-m-d H:i:s')."] Student {$student_id} overall updated to {$overall_avg}\n", FILE_APPEND);
    } else {
        @file_put_contents(__DIR__ . '/save_quarter_debug.log', "[".date('Y-m-d H:i:s')."] prepare failed for update student {$student_id}: " . $conn->error . "\n", FILE_APPEND);
    }
}

echo json_encode(["success" => true, "message" => "✅ Grades saved successfully. Overall Average updated for students with complete subjects only."]);

// Helper function for dynamic bind_param (copied from LACgrade_sheet.php)
function ref_values($arr){
    $refs = array();
    foreach($arr as $key => $value)
        $refs[$key] = &$arr[$key];
    return $refs;
}
exit;
?>