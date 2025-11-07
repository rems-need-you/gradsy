<?php
include('../partials-front/constantsss.php');
if (session_status() === PHP_SESSION_NONE) session_start();

// ======= SAVE ASSIGNMENT =======
// ======= SAVE ASSIGNMENT (CLEANED UP FOR MAPEH SPLITTING) =======
if (isset($_POST['save'])) {
    $teacher_id = $_POST['teacher_id'];
    $subject = $_POST['subject']; // auto-filled from department
    $grade = $_POST['grade'];
    $sections = $_POST['section'] ?? [];
    $year = $_POST['year'];

    // 1. Check for required fields
    if (empty($teacher_id) || empty($subject) || empty($grade) || empty($sections) || empty($year)) {
        $_SESSION['msg'] = "<div class='error'>Please complete all selections.</div>";
    } else {
        
        // 2. Determine the list of subjects to save
        $subjects_to_save = [];
        if ($subject === 'MAPEH') {
            $subjects_to_save = ['Music', 'Art', 'P.E.', 'Health'];
        } else {
            $subjects_to_save = [$subject];
        }
        
        $success_count = 0;
        $total_attempts = count($subjects_to_save) * count($sections);
        
        // Prepare the statements once outside the loop for efficiency
        $check_stmt = $conn->prepare("SELECT id FROM assign_teacher WHERE teacher_id=? AND subject=? AND grade=? AND section=? AND year=?");
        $insert_stmt = $conn->prepare("INSERT INTO assign_teacher (teacher_id, subject, grade, section, year) VALUES (?, ?, ?, ?, ?)");
        
        // 3. Loop through each subject and each selected section
        foreach ($subjects_to_save as $current_subject) {
            foreach ($sections as $section) {
                
                // Check if assignment already exists
                $check_stmt->bind_param("isiss", $teacher_id, $current_subject, $grade, $section, $year);
                $check_stmt->execute();
                $result = $check_stmt->get_result();

                if ($result->num_rows == 0) {
                    // Insert new assignment
                    $insert_stmt->bind_param("isiss", $teacher_id, $current_subject, $grade, $section, $year);
                    if ($insert_stmt->execute()) {
                        $success_count++;
                    }
                }
            }
        }
        
        // Close prepared statements after the loop is complete
        $check_stmt->close();
        $insert_stmt->close();
        
        // 4. Set session message based on results
        if ($success_count > 0) {
            $_SESSION['msg'] = "<div class='success'>Teacher assigned successfully! Total new assignments: {$success_count}.</div>";
        } elseif ($total_attempts > 0) {
            $_SESSION['msg'] = "<div class='error'>Assignment already exists for the selected teacher/subjects/sections.</div>";
        } else {
            $_SESSION['msg'] = "<div class='error'>Failed to save assignment. Database error or no new assignments to save.</div>";
        }
    }

    // Redirect regardless of success/failure
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// ======= FETCH TEACHERS =======
$teachers = mysqli_query($conn, "SELECT * FROM teacher_account ORDER BY name ASC");

// ======= FETCH GRADES =======
$grades = mysqli_query($conn, "SELECT DISTINCT grade FROM student ORDER BY grade ASC");

// ======= FETCH SECTIONS =======
$sections = mysqli_query($conn, "SELECT DISTINCT section FROM student ORDER BY section ASC");

// ======= PAGINATION + FETCH CURRENT ASSIGNMENTS =======
$limit = 10; // rows per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// count total
$total_result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM assign_teacher");
$total_row = mysqli_fetch_assoc($total_result);
$total_records = $total_row['total'];
$total_pages = ceil($total_records / $limit);

$current_assignments = mysqli_query($conn, "
    SELECT t.name, a.subject, a.grade, a.section, a.year 
    FROM assign_teacher a
    INNER JOIN teacher_account t ON a.teacher_id = t.id
    ORDER BY a.year DESC, t.name ASC, a.grade ASC, a.section ASC
    LIMIT $limit OFFSET $offset
");

// ======= AJAX: Get department when teacher selected =======
if (isset($_POST['get_department']) && isset($_POST['teacher_id'])) {
    $teacher_id = intval($_POST['teacher_id']);
    $query = $conn->prepare("SELECT department FROM teacher_account WHERE id=?");
    $query->bind_param("i", $teacher_id);
    $query->execute();
    $result = $query->get_result()->fetch_assoc();
    echo $result['department'] ?? '';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Assign Teacher</title>
<style>
body {
    font-family: 'Poppins', sans-serif;
    background-color: #faf8f8;
    margin: 0;
    padding: 0;
}
.container {
    width: 95%;
    max-width: 1300px;
    margin: 40px auto;
    display: flex;
    gap: 40px;
    align-items: flex-start;
}
.box {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    padding: 25px 35px;
}
.left { flex: 1; }
.right { flex: 1.2; max-height: 80vh; overflow-y: auto; }

h1, h2 {
    color: #a40000;
    text-align: center;
    margin-bottom: 20px;
}
form label {
    font-weight: 600;
    display: block;
    margin: 15px 0 5px;
    color: #333;
}
select, input[type="text"], input[type="submit"] {
    width: 100%;
    padding: 10px;
    border-radius: 6px;
    border: 1px solid #ccc;
    font-size: 15px;
    transition: 0.3s;
}
select:focus, input[type="text"]:focus {
    border-color: #a40000;
    outline: none;
    box-shadow: 0 0 5px rgba(164, 0, 0, 0.3);
}
select[multiple] { height: 100px; }
input[type="submit"] {
    background: #a40000;
    color: #fff;
    font-weight: bold;
    margin-top: 20px;
    cursor: pointer;
    border: none;
    transition: 0.3s;
}
input[type="submit"]:hover { background: #c50000; }
.success, .error {
    padding: 10px;
    border-radius: 5px;
    text-align: center;
    margin-bottom: 15px;
    font-weight: 500;
}
.success { background: #e8f5e9; color: #2e7d32; border: 1px solid #81c784; }
.error { background: #ffebee; color: #c62828; border: 1px solid #ef9a9a; }
table {
    width: 100%;
    border-collapse: collapse;
    font-size: 15px;
}
th, td {
    padding: 10px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}
th { background-color: #a40000; color: #fff; position: sticky; top: 0; }
tr:hover { background-color: #fff5f5; }

/* Pagination */
.pagination {
    text-align: center;
    margin-top: 15px;
}
.pagination a {
    color: #a40000;
    border: 1px solid #a40000;
    padding: 5px 10px;
    border-radius: 5px;
    margin: 0 3px;
    text-decoration: none;
    transition: 0.3s;
}
.pagination a.active {
    background-color: #a40000;
    color: #fff;
}
.pagination a:hover {
    background-color: #c50000;
    color: #fff;
}
</style>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function(){
    $('#teacher').change(function(){
        var teacherId = $(this).val();
        if(teacherId){
            $.ajax({
                url: '',
                type: 'POST',
                data: { get_department: 1, teacher_id: teacherId },
                success: function(dep){
                    $('#subject').val(dep.trim());
                }
            });
        } else {
            $('#subject').val('');
        }
    });
});
</script>
</head>
<body>
<div class="container">
    <!-- LEFT: ASSIGN FORM -->
    <div class="box left">
        <h1>Assign Teacher</h1>
        <?php 
            if (isset($_SESSION['msg'])) {
                echo $_SESSION['msg'];
                unset($_SESSION['msg']);
            }
        ?>
        <form method="POST">
            <label>Teacher:</label>
            <select name="teacher_id" id="teacher" required>
                <option value="">-- Select Teacher --</option>
                <?php while ($t = mysqli_fetch_assoc($teachers)) {
                    echo "<option value='{$t['id']}'>{$t['name']} ({$t['email']})</option>";
                } ?>
            </select>

            <label>Subject (Auto from Department):</label>
            <input type="text" name="subject" id="subject" readonly placeholder="Select a teacher to load subject">

            <label>Grade:</label>
            <select name="grade" id="grade" required>
                <option value="">-- Select Grade --</option>
                <?php while ($g = mysqli_fetch_assoc($grades)) {
                    echo "<option value='{$g['grade']}'>Grade {$g['grade']}</option>";
                } ?>
            </select>

            <label>Sections (hold Ctrl to select multiple):</label>
            <select name="section[]" id="section" multiple required>
                <?php while ($s = mysqli_fetch_assoc($sections)) {
                    echo "<option value='{$s['section']}'>Section {$s['section']}</option>";
                } ?>
            </select>

            <label>School Year:</label>
            <select name="year" required>
                <option value="">-- Select Year --</option>
                <?php 
                    $existing_years = mysqli_query($conn, "SELECT DISTINCT year FROM student ORDER BY year DESC");
                    $years = [];
                    while ($y = mysqli_fetch_assoc($existing_years)) {
                        $years[] = $y['year'];
                        echo "<option value='{$y['year']}'>{$y['year']}</option>";
                    }
                    $current = date("Y");
                    $curr_range = $current . "-" . ($current + 1);
                    if (!in_array($curr_range, $years)) {
                        echo "<option value='{$curr_range}'>{$curr_range}</option>";
                    }
                ?>
            </select>

            <input type="submit" name="save" value="Assign Teacher">
        </form>
    </div>

    <!-- RIGHT: ASSIGNMENT LIST -->
    <div class="box right">
        <h2>Teacher Assignment List</h2>
        <table>
            <tr>
                <th>Teacher</th>
                <th>Subject</th>
                <th>Grade</th>
                <th>Section</th>
                <th>Year</th>
            </tr>
            <?php
            if (mysqli_num_rows($current_assignments) > 0) {
                while ($row = mysqli_fetch_assoc($current_assignments)) {
                    echo "<tr>
                            <td>{$row['name']}</td>
                            <td>{$row['subject']}</td>
                            <td>Grade {$row['grade']}</td>
                            <td>{$row['section']}</td>
                            <td>{$row['year']}</td>
                        </tr>";
                }
            } else {
                echo "<tr><td colspan='5' style='text-align:center;color:#777;'>No assignments yet.</td></tr>";
            }
            ?>
        </table>

        <!-- PAGINATION LINKS -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page-1; ?>">&laquo; Prev</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>" class="<?php echo ($i == $page ? 'active' : ''); ?>"><?php echo $i; ?></a>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page+1; ?>">Next &raquo;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
