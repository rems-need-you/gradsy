<?php
include('../partials-front/constantsss.php');

// Handle Add Student
if (isset($_POST['add_student'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $middle_name = $conn->real_escape_string($_POST['middle_name']);
    $surname = $conn->real_escape_string($_POST['surname']);
    $grade = $conn->real_escape_string($_POST['grade']);
    $section = $conn->real_escape_string($_POST['section']);

    // Year formatted as 2021-2022
    $start_year = $conn->real_escape_string($_POST['start_year']);
    $end_year = $conn->real_escape_string($_POST['end_year']);
    $year = $start_year . "-" . $end_year;

    $conn->query("INSERT INTO student (name, middle_name, surname, grade, section, year) 
                  VALUES ('$name', '$middle_name', '$surname', '$grade', '$section', '$year')");
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle Update Student
if (isset($_POST['update_student'])) {
    $id = intval($_POST['id']);
    $name = $conn->real_escape_string($_POST['name']);
    $middle_name = $conn->real_escape_string($_POST['middle_name']);
    $surname = $conn->real_escape_string($_POST['surname']);
    $grade = $conn->real_escape_string($_POST['grade']);
    $section = $conn->real_escape_string($_POST['section']);

    $start_year = $conn->real_escape_string($_POST['start_year']);
    $end_year = $conn->real_escape_string($_POST['end_year']);
    $year = $start_year . "-" . $end_year;

    $conn->query("UPDATE student 
                  SET name='$name', middle_name='$middle_name', surname='$surname', grade='$grade', section='$section', year='$year' 
                  WHERE id=$id");
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle Delete Student
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM student WHERE id=$id");
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Search functionality
$keyword = $_GET['search'] ?? '';
$keyword = $conn->real_escape_string($keyword);

$sql = "SELECT * FROM student 
        WHERE name LIKE '%$keyword%' 
        OR middle_name LIKE '%$keyword%' 
        OR surname LIKE '%$keyword%' 
        OR grade LIKE '%$keyword%' 
        OR section LIKE '%$keyword%' 
        OR year LIKE '%$keyword%' 
        ORDER BY name ASC";
$students = $conn->query($sql);

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

<div class="container">

    <h2>📋 Manage Students</h2>

    <!-- Search -->
    <form method="get" class="search-box" id="searchForm">
        <input type="text" name="search" id="searchInput"
               placeholder="Search by name, middle name, surname, grade, section, or year..."
               value="<?= htmlspecialchars($keyword) ?>">
        <a href="<?= $_SERVER['PHP_SELF'] ?>"><button type="button" style="background:#7f8c8d;">Reset</button></a>
    </form>

    <!-- Add/Edit Form -->
    <!-- Add/Edit Form -->
<h3><?= $editStudent ? "✏️ Edit Student" : "➕ Add Student" ?></h3>
<form method="post">
    <?php if ($editStudent): ?>
        <input type="hidden" name="id" value="<?= $editStudent['id'] ?>">
    <?php endif; ?>
    <input type="text" name="name" placeholder="First Name" value="<?= $editStudent['name'] ?? '' ?>" required>
    <input type="text" name="middle_name" placeholder="Middle Name" value="<?= $editStudent['middle_name'] ?? '' ?>" required>
    <input type="text" name="surname" placeholder="Surname" value="<?= $editStudent['surname'] ?? '' ?>" required>
    
    <!-- Grade Dropdown -->
    <label>Grade:</label>
    <select name="grade" required>
        <?php for ($g = 4; $g <= 6; $g++): ?>
            <option value="<?= $g ?>" <?= ($editStudent && $editStudent['grade'] == $g) ? 'selected' : '' ?>>
                Grade <?= $g ?>
            </option>
        <?php endfor; ?>
    </select>

    <!-- Section Dropdown -->
    <label>Section:</label>
    <select name="section" required>
        <?php for ($s = 1; $s <= 7; $s++): ?>
            <option value="<?= $s ?>" <?= ($editStudent && $editStudent['section'] == $s) ? 'selected' : '' ?>>
                Section <?= $s ?>
            </option>
        <?php endfor; ?>
    </select>
    
    <!-- School Year Dropdown -->
    <?php 
    $startYear = '';
    $endYear = '';
    if ($editStudent && strpos($editStudent['year'], '-') !== false) {
        list($startYear, $endYear) = explode('-', $editStudent['year']);
    }
    ?>
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

    <button type="submit" name="<?= $editStudent ? 'update_student' : 'add_student' ?>">
        <?= $editStudent ? 'Update Student' : 'Add Student' ?>
    </button>
    <?php if ($editStudent): ?>
        <a href="<?= $_SERVER['PHP_SELF'] ?>"><button type="button" style="background:#7f8c8d;">Cancel</button></a>
    <?php endif; ?>
</form>


    <!-- Students Table -->
    <table>
        <tr>
            <th>First Name</th>
            <th>Middle Name</th>
            <th>Surname</th>
            <th>Grade</th>
            <th>Section</th>
            <th>Year</th>
            <th>Actions</th>
        </tr>
        <?php if ($students->num_rows > 0): ?>
            <?php while($row = $students->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= htmlspecialchars($row['middle_name']) ?></td>
                    <td><?= htmlspecialchars($row['surname']) ?></td>
                    <td><?= htmlspecialchars($row['grade']) ?></td>
                    <td><?= htmlspecialchars($row['section']) ?></td>
                    <td><?= htmlspecialchars($row['year']) ?></td>
                    <td>
                        <a href="?edit=<?= $row['id'] ?>"><button type="button" class="view-btn">Edit</button></a>
                        <a href="?delete=<?= $row['id'] ?>" onclick="return confirm('Are you sure you want to delete this student?');">
                            <button type="button" class="delete-btn">Delete</button>
                        </a>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="7">No students found.</td></tr>
        <?php endif; ?>
    </table>
</div>

<script>
    const searchInput = document.getElementById('searchInput');
    const form = document.getElementById('searchForm');

    let typingTimer;
    searchInput.addEventListener('keyup', function () {
        clearTimeout(typingTimer);
        typingTimer = setTimeout(() => {
            form.submit(); // auto-submit after typing stops
        }, 1000);
    });
</script>

</body>
</html>
