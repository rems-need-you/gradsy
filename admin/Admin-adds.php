<?php
include ('partials/constants.php');

// Start session for flash messages if not started
if (session_status() === PHP_SESSION_NONE) session_start();

// Handle Add Student
if (isset($_POST['add_student'])) {
    $name = $conn->real_escape_string(trim($_POST['name']));
    $middle_name = $conn->real_escape_string(trim($_POST['middle_name']));
    $surname = $conn->real_escape_string(trim($_POST['surname']));
    $grade = $conn->real_escape_string($_POST['grade']);
    $section = $conn->real_escape_string($_POST['section']);

    // --- VALIDATION: names ---
    // First name & surname: letters, spaces, hyphen, apostrophe only
    $name_ok = preg_match('/^[\p{L} \'-]+$/u', $name);
    $surname_ok = preg_match('/^[\p{L} \'-]+$/u', $surname);
    // Middle name: single letter followed by dot, e.g. "I."
    $middle_ok = preg_match('/^[A-Za-z]\.$/', $middle_name);

    if (!$name_ok) {
        $_SESSION['msg'] = "<div style='padding:10px;background:#ffebee;color:#c62828;border:1px solid #ef9a9a;border-radius:5px;margin:10px 0;'>First name may only contain letters, spaces, hyphens or apostrophes.</div>";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
    if (!$middle_ok) {
        $_SESSION['msg'] = "<div style='padding:10px;background:#ffebee;color:#c62828;border:1px solid #ef9a9a;border-radius:5px;margin:10px 0;'>Middle name must be a single letter followed by a dot (e.g. I.).</div>";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
    if (!$surname_ok) {
        $_SESSION['msg'] = "<div style='padding:10px;background:#ffebee;color:#c62828;border:1px solid #ef9a9a;border-radius:5px;margin:10px 0;'>Surname may only contain letters, spaces, hyphens or apostrophes.</div>";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    $start_year = $conn->real_escape_string($_POST['start_year']);
    $end_year = $conn->real_escape_string($_POST['end_year']);

    // --- validate school year is exactly 1 year span ---
    $syStart = intval($start_year);
    $syEnd = intval($end_year);
    if ($syEnd !== $syStart + 1) {
        $_SESSION['msg'] = "<div style='padding:10px;background:#ffebee;color:#c62828;border:1px solid #ef9a9a;border-radius:5px;margin:10px 0;'>School year must span exactly one year (e.g. 2021-2022).</div>";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
    $year = $syStart . "-" . $syEnd;

    $conn->query("INSERT INTO student (name, middle_name, surname, grade, section, year) 
                  VALUES ('$name', '$middle_name', '$surname', '$grade', '$section', '$year')");

    $_SESSION['msg'] = "<div style='padding:10px;background:#e8f5e9;color:#2e7d32;border:1px solid #81c784;border-radius:5px;margin:10px 0;'>Student added successfully.</div>";
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle Update Student
if (isset($_POST['update_student'])) {
    $id = intval($_POST['id']);
    $name = $conn->real_escape_string(trim($_POST['name']));
    $middle_name = $conn->real_escape_string(trim($_POST['middle_name']));
    $surname = $conn->real_escape_string(trim($_POST['surname']));
    $grade = $conn->real_escape_string($_POST['grade']);
    $section = $conn->real_escape_string($_POST['section']);

    // --- VALIDATION: names (same rules as add) ---
    $name_ok = preg_match('/^[\p{L} \'-]+$/u', $name);
    $surname_ok = preg_match('/^[\p{L} \'-]+$/u', $surname);
    $middle_ok = preg_match('/^[A-Za-z]\.$/', $middle_name);

    if (!$name_ok) {
        $_SESSION['msg'] = "<div style='padding:10px;background:#ffebee;color:#c62828;border:1px solid #ef9a9a;border-radius:5px;margin:10px 0;'>First name may only contain letters, spaces, hyphens or apostrophes.</div>";
        header("Location: " . $_SERVER['PHP_SELF'] . "?edit=" . $id);
        exit;
    }
    if (!$middle_ok) {
        $_SESSION['msg'] = "<div style='padding:10px;background:#ffebee;color:#c62828;border:1px solid #ef9a9a;border-radius:5px;margin:10px 0;'>Middle name must be a single letter followed by a dot (e.g. I.).</div>";
        header("Location: " . $_SERVER['PHP_SELF'] . "?edit=" . $id);
        exit;
    }
    if (!$surname_ok) {
        $_SESSION['msg'] = "<div style='padding:10px;background:#ffebee;color:#c62828;border:1px solid #ef9a9a;border-radius:5px;margin:10px 0;'>Surname may only contain letters, spaces, hyphens or apostrophes.</div>";
        header("Location: " . $_SERVER['PHP_SELF'] . "?edit=" . $id);
        exit;
    }

    $start_year = $conn->real_escape_string($_POST['start_year']);
    $end_year = $conn->real_escape_string($_POST['end_year']);

    // --- validate school year is exactly 1 year span ---
    $syStart = intval($start_year);
    $syEnd = intval($end_year);
    if ($syEnd !== $syStart + 1) {
        // redirect back to edit with message
        $_SESSION['msg'] = "<div style='padding:10px;background:#ffebee;color:#c62828;border:1px solid #ef9a9a;border-radius:5px;margin:10px 0;'>School year must span exactly one year (e.g. 2021-2022).</div>";
        header("Location: " . $_SERVER['PHP_SELF'] . "?edit=" . $id);
        exit;
    }
    $year = $syStart . "-" . $syEnd;

    $conn->query("UPDATE student 
                  SET name='$name', middle_name='$middle_name', surname='$surname', grade='$grade', section='$section', year='$year' 
                  WHERE id=$id");

    $_SESSION['msg'] = "<div style='padding:10px;background:#e8f5e9;color:#2e7d32;border:1px solid #81c784;border-radius:5px;margin:10px 0;'>Student updated successfully.</div>";
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle Delete Student
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM student WHERE id=$id");
    $_SESSION['msg'] = "<div style='padding:10px;background:#fff3e0;color:#ef6c00;border:1px solid #ffcc80;border-radius:5px;margin:10px 0;'>Student deleted successfully.</div>";
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Search functionality
$keyword = $_GET['search'] ?? '';
$keyword = $conn->real_escape_string($keyword);

// Pagination setup
$limit = 10; // number of students per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Count total students for pagination
$countSql = "SELECT COUNT(*) as total FROM student 
             WHERE name LIKE '%$keyword%' 
             OR middle_name LIKE '%$keyword%' 
             OR surname LIKE '%$keyword%' 
             OR grade LIKE '%$keyword%' 
             OR section LIKE '%$keyword%' 
             OR year LIKE '%$keyword%'";
$totalResult = $conn->query($countSql)->fetch_assoc();
$totalStudents = $totalResult['total'];
$totalPages = ceil($totalStudents / $limit);

// Fetch paginated & grouped data
$sql = "SELECT * FROM student 
        WHERE name LIKE '%$keyword%' 
        OR middle_name LIKE '%$keyword%' 
        OR surname LIKE '%$keyword%' 
        OR grade LIKE '%$keyword%' 
        OR section LIKE '%$keyword%' 
        OR year LIKE '%$keyword%' 
        ORDER BY grade ASC, section ASC, year ASC, surname ASC 
        LIMIT $limit OFFSET $offset";
$students = $conn->query($sql);

// Group students by grade, section, and year
$grouped = [];
while ($row = $students->fetch_assoc()) {
    $grade = $row['grade'];
    $section = $row['section'];
    $year = $row['year'];
    $grouped[$grade][$section][$year][] = $row;
}

// Get student for editing
$editStudent = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $editStudent = $conn->query("SELECT * FROM student WHERE id=$id")->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Students</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../css/adds.css">
</head>
<body>
<?php
// display flash message if any
if (isset($_SESSION['msg'])) {
    echo $_SESSION['msg'];
    unset($_SESSION['msg']);
}
?>
<div class="main-wrapper">
<div class="form-container">
    <!-- ===== FORM CARD (LEFT) ===== -->
        <h3><?= $editStudent ? "✏️ Edit Student" : "➕ Add Student" ?></h3>

        <form method="post">
            <?php if ($editStudent): ?>
                <input type="hidden" name="id" value="<?= $editStudent['id'] ?>">
            <?php endif; ?>

            <div class="form-row">
                <input type="text" name="name" placeholder="First Name" value="<?= $editStudent['name'] ?? '' ?>" required>
                <input type="text" name="middle_name" placeholder="Middle Name (eg, I.)" value="<?= $editStudent['middle_name'] ?? '' ?>" required>
                <input type="text" name="surname" placeholder="Surname" value="<?= $editStudent['surname'] ?? '' ?>" required>
            </div>

            <div class="form-row">
                <label>Grade:</label>
                <select name="grade" required>
                    <?php for ($g = 4; $g <= 6; $g++): ?>
                        <option value="<?= $g ?>" <?= ($editStudent && $editStudent['grade'] == $g) ? 'selected' : '' ?>>Grade <?= $g ?></option>
                    <?php endfor; ?>
                </select>

                <label>Section:</label>
                <select name="section" required>
                    <?php for ($s = 1; $s <= 7; $s++): ?>
                        <option value="<?= $s ?>" <?= ($editStudent && $editStudent['section'] == $s) ? 'selected' : '' ?>>Section <?= $s ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <?php 
            $startYear = '';
            $endYear = '';
            if ($editStudent && strpos($editStudent['year'], '-') !== false) {
                list($startYear, $endYear) = explode('-', $editStudent['year']);
            }
            ?>
            <div class="form-row">
                <label>School Year:</label>
                <select name="start_year" required>
                    <?php for ($y = 2015; $y <= 2035; $y++): ?>
                        <option value="<?= $y ?>" <?= ($startYear == $y) ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
                -
                <select name="end_year" required>
                    <?php for ($y = 2016; $y <= 2036; $y++): ?>
                        <option value="<?= $y ?>" <?= ($endYear == $y) ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <button type="submit" name="<?= $editStudent ? 'update_student' : 'add_student' ?>">
                <?= $editStudent ? 'Update Student' : 'Add Student' ?>
            </button>

            <?php if ($editStudent): ?>
                <a href="<?= $_SERVER['PHP_SELF'] ?>"><button type="button" style="background:#7f8c8d;">Cancel</button></a>
            <?php endif; ?>
            </form>
</div>
<div class="table-container">
    <!-- ===== RIGHT SIDE: TABLE CARD ===== -->
        <h3>📚 Student List</h3>

        <!-- Search -->
        <form method="get" class="search-box" id="searchForm" style="margin-bottom:15px;">
            <input type="text" name="search" id="searchInput"
                   placeholder="Search by name, middle name, surname, grade, section, or year..."
                   value="<?= htmlspecialchars($keyword) ?>" style="width:70%;">
            <a href="<?= $_SERVER['PHP_SELF'] ?>"><button type="button" style="background:#7f8c8d;">Reset</button></a>
        </form>

        <!-- Table -->
        <div class="student-table" style="max-height:70vh; overflow-y:auto;">
            <?php if (!empty($grouped)): ?>
                <?php foreach ($grouped as $grade => $sections): ?>
                    <?php foreach ($sections as $section => $years): ?>
                        <?php foreach ($years as $year => $studentsList): ?>
                            <h4 style="margin-top:20px;">Grade <?= htmlspecialchars($grade) ?> – Section <?= htmlspecialchars($section) ?> – SY <?= htmlspecialchars($year) ?></h4>
                            <table>
                                <thead>
                                    <tr>
                                        <th>First Name</th>
                                        <th>Middle Name</th>
                                        <th>Surname</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ((array)$studentsList as $row): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['name']) ?></td>
                                            <td><?= htmlspecialchars($row['middle_name']) ?></td>
                                            <td><?= htmlspecialchars($row['surname']) ?></td>
                                            <td>
                                                <a href="?edit=<?= $row['id'] ?>"><button type="button" class="view-btn">Edit</button></a>
                                                <a href="?delete=<?= $row['id'] ?>" onclick="return confirm('Are you sure you want to delete this student?');"><button type="button" class="delete-btn">Delete</button></a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No students found.</p>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <div class="pagination">
            <?php if ($totalPages > 1): ?>
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($keyword) ?>">⬅ Prev</a>
                <?php endif; ?>
                <span>Page <?= $page ?> of <?= $totalPages ?></span>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($keyword) ?>">Next ➡</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
const searchInput = document.getElementById('searchInput');
const form = document.getElementById('searchForm');
let typingTimer;
searchInput.addEventListener('keyup', function () {
    clearTimeout(typingTimer);
    typingTimer = setTimeout(() => {
        form.submit();
    }, 1000);
});
</script>
</body>

</html>
