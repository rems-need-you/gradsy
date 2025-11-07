<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../partials-front/constantsss.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Invalid method']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'get_form_data': 
        get_form_data($conn); 
        break;

    case 'record_participation': 
        record_participation($conn); 
        break;

    case 'get_dashboard': 
        get_dashboard($conn); 
        break;

    case 'add_activity': 
        add_activity($conn); 
        break;

    default:
        echo json_encode(['ok' => false, 'error' => 'Unknown action']);
        break;
}

// ✅ Get students and activities for dropdowns
function get_form_data($conn) {
    $students = [];
    $res = $conn->query("SELECT id, CONCAT(surname, ', ', name, ' ', middle_name) AS full_name, year FROM student ORDER BY surname, name");
    if ($res) $students = $res->fetch_all(MYSQLI_ASSOC);

    $activities = [];
    $res = $conn->query("SELECT id, title, category, subject FROM activities ORDER BY title");
    if ($res) $activities = $res->fetch_all(MYSQLI_ASSOC);

    echo json_encode(['ok' => true, 'students' => $students, 'activities' => $activities]);
}

// ✅ Record participation + auto update grade
function record_participation($conn) {
    $student_id = intval($_POST['student_id'] ?? 0);
    $activity_id = intval($_POST['activity_id'] ?? 0);
    $level = trim($_POST['level'] ?? '');
    $rank = trim($_POST['rank_position'] ?? '');
    $date_participated = $_POST['date_participated'] ?? date('Y-m-d');
    $remarks = trim($_POST['remarks'] ?? '');

    if ($student_id <= 0 || $activity_id <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Select student and activity']);
        return;
    }

    // Get student's school year
    $stmtS = $conn->prepare("SELECT year FROM student WHERE id = ?");
    $stmtS->bind_param('i', $student_id);
    $stmtS->execute();
    $res = $stmtS->get_result();
    if (!$res->num_rows) {
        echo json_encode(['ok' => false, 'error' => 'Student not found']);
        return;
    }
    $student = $res->fetch_assoc();
    $school_year = $student['year'];
    $stmtS->close();

    // Parse school year (e.g., "2021-2022")
    $years = explode('-', $school_year);
    if (count($years) !== 2) {
        echo json_encode(['ok' => false, 'error' => 'Invalid school year format']);
        return;
    }

    $start_year = intval($years[0]);
    $end_year = intval($years[1]);
    
    // Create valid date range (June of start year to March of end year)
    $valid_start_date = $start_year . '-06-01';
    $valid_end_date = $end_year . '-03-31';

    // Check if participation date is within school year
    if ($date_participated < $valid_start_date || $date_participated > $valid_end_date) {
        echo json_encode([
            'ok' => false, 
            'error' => "Date must be between June {$start_year} and March {$end_year} (School Year: {$school_year})"
        ]);
        return;
    }

    // ✅ Get category & subject of the activity
    $stmtA = $conn->prepare("SELECT category, subject FROM activities WHERE id = ?");
    $stmtA->bind_param('i', $activity_id);
    $stmtA->execute();
    $res = $stmtA->get_result();
    if (!$res->num_rows) {
        echo json_encode(['ok' => false, 'error' => 'Activity not found']);
        return;
    }
    $activity = $res->fetch_assoc();
    $category = $activity['category'];
    $subject = $activity['subject'] ?? null;
    $stmtA->close();

    // ✅ Compute percent points
    $percent = computePercent($category, $level, $rank);

    // ✅ Save participation record
    $stmt = $conn->prepare("INSERT INTO participations 
        (student_id, activity_id, level, rank_position, percent, date_participated, remarks)
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('iissdss', $student_id, $activity_id, $level, $rank, $percent, $date_participated, $remarks);
    if (!$stmt->execute()) {
        echo json_encode(['ok' => false, 'error' => $stmt->error]);
        return;
    }
    $stmt->close();

    // ✅ Determine quarter based on month
    $month = intval(date('n', strtotime($date_participated)));
    if ($month >= 6 && $month <= 8) $quarter = 1;
    elseif ($month >= 9 && $month <= 11) $quarter = 2;
    elseif ($month >= 12 || $month <= 2) $quarter = 3;
    else $quarter = 4;

    // ✅ Add points to grade table if subject exists
    if (!empty($subject)) {
        $qField = "q" . $quarter;

        // Check if grade record exists for student + subject
        $stmt3 = $conn->prepare("SELECT $qField FROM grade WHERE student_id = ? AND subject = ?");
        $stmt3->bind_param("is", $student_id, $subject);
        $stmt3->execute();
        $res = $stmt3->get_result();

        if ($res->num_rows > 0) {
            $curr = floatval($res->fetch_assoc()[$qField]);
            $newGrade = $curr + ($percent * 10); // 0.45 × 10 = +4.5
            if ($newGrade > 100) $newGrade = 100;

            $stmt4 = $conn->prepare("UPDATE grade SET $qField = ? WHERE student_id = ? AND subject = ?");
            $stmt4->bind_param("dis", $newGrade, $student_id, $subject);
            $stmt4->execute();
            $stmt4->close();
        } else {
            $newGrade = $percent * 10;
            $stmt5 = $conn->prepare("INSERT INTO grade (student_id, subject, $qField) VALUES (?, ?, ?)");
            $stmt5->bind_param("isd", $student_id, $subject, $newGrade);
            $stmt5->execute();
            $stmt5->close();
        }
        $stmt3->close();
    }

    echo json_encode([
        'ok' => true,
        'percent' => $percent,
        'message' => "Participation recorded (+".($percent*10)." pts) applied to {$subject} (Q{$quarter})"
    ]);
}

// ✅ Dashboard summary
function get_dashboard($conn) {
    $recent = [];
    $sql = "
        SELECT p.*, CONCAT(s.surname, ', ', s.name) AS student_name,
               a.title AS activity_title, a.category
        FROM participations p
        JOIN student s ON s.id = p.student_id
        JOIN activities a ON a.id = p.activity_id
        ORDER BY p.date_participated DESC, p.created_at DESC
        LIMIT 50
    ";
    $res = $conn->query($sql);
    if ($res) $recent = $res->fetch_all(MYSQLI_ASSOC);

    $leaderboard = [];
    $sql2 = "
        SELECT s.id, CONCAT(s.surname, ', ', s.name) AS name,
               s.grade, s.section, COALESCE(SUM(p.percent),0) AS total_points
        FROM student s
        LEFT JOIN participations p ON p.student_id = s.id
        GROUP BY s.id
        ORDER BY total_points DESC, s.surname ASC
    ";
    $res = $conn->query($sql2);
    if ($res) $leaderboard = $res->fetch_all(MYSQLI_ASSOC);

    echo json_encode(['ok' => true, 'data' => [
        'recent' => $recent,
        'leaderboard' => $leaderboard
    ]]);
}

// ✅ Add new activity
function add_activity($conn) {
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $subject = trim($_POST['subject'] ?? '');

    if ($title === '' || $category === '') {
        echo json_encode(['ok' => false, 'error' => 'All fields required']);
        return;
    }

    $stmt = $conn->prepare("INSERT INTO activities (title, category, subject) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $title, $category, $subject);

    if ($stmt->execute()) {
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Database error: ' . $stmt->error]);
    }
    $stmt->close();
}

// ✅ Percentage computation
function computePercent($category, $level, $rank) {
    $points = [
        'general' => [
            'International' => ['1st'=>0.50,'2nd'=>0.49,'3rd'=>0.48,'Participation'=>0.47],
            'National'      => ['1st'=>0.46,'2nd'=>0.45,'3rd'=>0.44,'Participation'=>0.43],
            'Regional'      => ['1st'=>0.42,'2nd'=>0.41,'3rd'=>0.40,'Participation'=>0.39],
            'Sectoral'      => ['1st'=>0.38,'2nd'=>0.37,'3rd'=>0.36,'Participation'=>0.35],
            'Division'      => ['1st'=>0.34,'2nd'=>0.33,'3rd'=>0.32,'Participation'=>0.31],
            'In-School'     => ['1st'=>0.30,'2nd'=>0.29,'3rd'=>0.28,'Participation'=>0.27],
        ],
        'mapeh' => [
            'International' => ['1st'=>0.25,'2nd'=>0.24,'3rd'=>0.23,'Participation'=>0.22],
            'National'      => ['1st'=>0.21,'2nd'=>0.20,'3rd'=>0.19,'Participation'=>0.18],
            'Regional'      => ['1st'=>0.17,'2nd'=>0.16,'3rd'=>0.15,'Participation'=>0.14],
            'Sectoral'      => ['1st'=>0.13,'2nd'=>0.12,'3rd'=>0.11,'Participation'=>0.10],
            'Division'      => ['1st'=>0.09,'2nd'=>0.08,'3rd'=>0.07,'Participation'=>0.06],
            'In-School'     => ['1st'=>0.05,'2nd'=>0.04,'3rd'=>0.03,'Participation'=>0.02],
        ]
    ];
    $key = strtolower($category) === 'mapeh' ? 'mapeh' : 'general';
    return $points[$key][$level][$rank] ?? 0;
}
?>
