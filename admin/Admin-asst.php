<?php
include ('partials/constants.php');
if (session_status() === PHP_SESSION_NONE) session_start();

// ======= DELETE ASSIGNMENT (via GET) =======
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    $del_stmt = $conn->prepare("DELETE FROM assign_teacher WHERE id = ?");
    $del_stmt->bind_param("i", $del_id);
    if ($del_stmt->execute()) {
        $_SESSION['msg'] = "<div class='success'>Assignment deleted successfully.</div>";
    } else {
        $_SESSION['msg'] = "<div class='error'>Failed to delete assignment.</div>";
    }
    $del_stmt->close();
    header("Location: " . strtok($_SERVER['REQUEST_URI'],'?'));
    exit;
}

// ======= LOAD ASSIGNMENT FOR EDITING (via GET) =======
$edit_mode = false;
$edit_data = [];
if (isset($_GET['edit_id'])) {
    $eid = intval($_GET['edit_id']);
    $q = $conn->prepare("SELECT id, teacher_id, subject, grade, section, year FROM assign_teacher WHERE id = ? LIMIT 1");
    $q->bind_param("i", $eid);
    $q->execute();
    $res = $q->get_result();
    if ($res && $res->num_rows > 0) {
        $edit_mode = true;
        $edit_data = $res->fetch_assoc();
    }
    $q->close();
}

// ======= UPDATE ASSIGNMENT =======
if (isset($_POST['update'])) {
    $edit_id = intval($_POST['edit_id']);
    $teacher_id = intval($_POST['teacher_id']);
    $subject = $_POST['subject'];
    $grade = $_POST['grade'];
    $sections = $_POST['section'] ?? [];
    $section = $sections[0] ?? ''; // when editing a single assignment row, use first selected
    $year = $_POST['year'];

    // basic validation
    if (empty($teacher_id) || empty($subject) || empty($grade) || empty($section) || empty($year)) {
        $_SESSION['msg'] = "<div class='error'>Please complete all selections for update.</div>";
    } else {
        // check if another assignment already exists for same subject/grade/section/year (excluding this id)
        $chk = $conn->prepare("SELECT id, teacher_id FROM assign_teacher WHERE subject=? AND grade=? AND section=? AND year=? AND id <> ?");
        $chk->bind_param("sissi", $subject, $grade, $section, $year, $edit_id);
        $chk->execute();
        $cres = $chk->get_result();

        if ($cres && $cres->num_rows > 0) {
            $_SESSION['msg'] = "<div class='error'>Cannot update: another assignment already exists for that subject/grade/section/year.</div>";
        } else {
            $up = $conn->prepare("UPDATE assign_teacher SET teacher_id = ?, subject = ?, grade = ?, section = ?, year = ? WHERE id = ?");
            $up->bind_param("isissi", $teacher_id, $subject, $grade, $section, $year, $edit_id);
            if ($up->execute()) {
                $_SESSION['msg'] = "<div class='success'>Assignment updated successfully.</div>";
            } else {
                $_SESSION['msg'] = "<div class='error'>Failed to update assignment.</div>";
            }
            $up->close();
        }
        $chk->close();
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// ======= SAVE ASSIGNMENT =======
if (isset($_POST['save'])) {
    $teacher_id = intval($_POST['teacher_id']);
    $subject = $_POST['subject']; 
    $grade = $_POST['grade'];
    $sections = $_POST['section'] ?? [];
    $year = $_POST['year'];

    if (empty($teacher_id) || empty($subject) || empty($grade) || empty($sections) || empty($year)) {
        $_SESSION['msg'] = "<div class='error'>Please complete all selections.</div>";
    } else {
        $subjects_to_save = ($subject === 'MAPEH')
            ? ['Music', 'Art', 'P.E.', 'Health']
            : [$subject];

        $success_count = 0;
        $conflicts = [];

        $check_stmt = $conn->prepare("SELECT id, teacher_id FROM assign_teacher WHERE subject=? AND grade=? AND section=? AND year=?");
        $insert_stmt = $conn->prepare("INSERT INTO assign_teacher (teacher_id, subject, grade, section, year) VALUES (?, ?, ?, ?, ?)");

        foreach ($subjects_to_save as $current_subject) {
            foreach ($sections as $section) {
                // check existing assignment for this subject/grade/section/year (regardless of teacher)
                $check_stmt->bind_param("siss", $current_subject, $grade, $section, $year);
                $check_stmt->execute();
                $res = $check_stmt->get_result();

                if ($res && $res->num_rows > 0) {
                    // if already assigned and same teacher -> skip; if different teacher -> record conflict
                    $row = $res->fetch_assoc();
                    if (intval($row['teacher_id']) !== $teacher_id) {
                        // fetch teacher name
                        $tname = "Unknown";
                        $tq = $conn->prepare("SELECT name FROM teacher_account WHERE id = ? LIMIT 1");
                        $tq->bind_param("i", $row['teacher_id']);
                        $tq->execute();
                        $tres = $tq->get_result();
                        if ($tres && $tres->num_rows > 0) $tname = $tres->fetch_assoc()['name'];
                        $tq->close();

                        $conflicts[] = [
                            'subject' => $current_subject,
                            'grade' => $grade,
                            'section' => $section,
                            'year' => $year,
                            'assigned_teacher' => $tname,
                            'assign_id' => $row['id']
                        ];
                        continue; // don't insert
                    }
                    // same teacher assigned -> skip insert (already exists)
                    continue;
                }

                // no existing assignment -> insert
                $insert_stmt->bind_param("isiss", $teacher_id, $current_subject, $grade, $section, $year);
                if ($insert_stmt->execute()) $success_count++;
            }
        }

        $check_stmt->close();
        $insert_stmt->close();

        // Build message
        $msg_parts = [];
        if ($success_count > 0) {
            $msg_parts[] = "<div class='success'>Teacher assigned successfully! New assignments added: {$success_count}.</div>";
        }
        if (!empty($conflicts)) {
            $html = "<div class='error'><strong>Conflicts detected:</strong><br>";
            foreach ($conflicts as $c) {
                $html .= "Subject: " . htmlspecialchars($c['subject']) . " | Grade: {$c['grade']} | Section: " . htmlspecialchars($c['section']) . " | Year: {$c['year']} — Assigned to: " . htmlspecialchars($c['assigned_teacher']);
                // links to edit/delete conflict
                $html .= " &nbsp; <a href='?edit_id=" . intval($c['assign_id']) . "' style='color:#fff;background:#a40000;padding:2px 6px;border-radius:4px;text-decoration:none;'>Edit</a>";
                $html .= " &nbsp; <a href='?delete_id=" . intval($c['assign_id']) . "' onclick=\"return confirm('Delete this conflicting assignment?');\" style='color:#a40000;text-decoration:underline;'>Delete</a>";
                $html .= "<br>";
            }
            $html .= "<br>Resolve the conflicts above (Edit/Delete) then try assigning again.</div>";
            $msg_parts[] = $html;
        }

        if (empty($msg_parts)) {
            $msg_parts[] = "<div class='error'>No new assignments added. They may already exist.</div>";
        }

        $_SESSION['msg'] = implode("", $msg_parts);
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// ======= FETCH DATA =======
$teachers = mysqli_query($conn, "SELECT * FROM teacher_account ORDER BY name ASC");
$grades = mysqli_query($conn, "SELECT DISTINCT grade FROM student ORDER BY grade ASC");
$sections = mysqli_query($conn, "SELECT DISTINCT section FROM student ORDER BY section ASC");

// ======= PAGINATION / ASSIGNMENTS LIST: include assign id and teacher_id for actions =======
$limit = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$total_result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM assign_teacher");
$total_records = mysqli_fetch_assoc($total_result)['total'];
$total_pages = ceil($total_records / $limit);

$current_assignments = mysqli_query($conn, "
    SELECT a.id AS assign_id, a.teacher_id, t.name, a.subject, a.grade, a.section, a.year 
    FROM assign_teacher a
    LEFT JOIN teacher_account t ON a.teacher_id = t.id
    ORDER BY a.year DESC, t.name ASC, a.grade ASC, a.section ASC
    LIMIT $limit OFFSET $offset
");

// ======= AJAX: Get department =======
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

<!-- ✅ Include Select2 (searchable dropdown) -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

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
h1, h2 { color: #000000ff; text-align: center; margin-bottom: 20px; }
form label { font-weight: 600; display: block; margin: 15px 0 5px; color: #333; }
select, input[type="text"], input[type="submit"] {
    width: 100%;
    padding: 10px;
    border-radius: 6px;
    border: 1px solid #ccc;
    font-size: 15px;
    transition: 0.3s;
}
select[multiple] { height: 100px; }
input[type="submit"] {
    background: #0287d4ff;
    color: #fff;
    font-weight: bold;
    margin-top: 20px;
    cursor: pointer;
    border: none;
}
input[type="submit"]:hover { background: #6abcffff; }
.success, .error { padding: 10px; border-radius: 5px; text-align: center; margin-bottom: 15px; font-weight: 500; }
.success { background: #e8f5e9; color: #2e7d32; border: 1px solid #81c784; }
.error { background: #ffebee; color: #c62828; border: 1px solid #ef9a9a; }
table { width: 100%; border-collapse: collapse; font-size: 15px; }
th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
th { background-color: #FFC300; color: #fff; position: sticky; top: 0; }
tr:hover { background-color: #fff5f5; }
.pagination { text-align: center; margin-top: 15px; }
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

/* ✅ Make Select2 fit design */
.select2-container--default .select2-selection--single {
    height: 42px;
    padding: 5px;
    border-radius: 6px;
    border: 1px solid #ccc;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 30px;
}
</style>

<script>
$(document).ready(function(){
    // ✅ Make teacher dropdown searchable
    $('#teacher').select2({
        placeholder: "-- Select Teacher --",
        allowClear: true,
        width: '100%'
    });

    // ✅ Auto-load department when teacher changes
    $('#teacher').on('change', function(){
        var teacherId = $(this).val();
        if(teacherId){
            $.post('', { get_department: 1, teacher_id: teacherId }, function(dep){
                $('#subject').val(dep.trim());
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
                <?php 
                // reset teachers result pointer if necessary
                mysqli_data_seek($teachers, 0);
                while ($t = mysqli_fetch_assoc($teachers)) {
                    $selected = ($edit_mode && intval($edit_data['teacher_id']) === intval($t['id'])) ? "selected" : "";
                    echo "<option value='{$t['id']}' {$selected}>" . htmlspecialchars($t['name']) . " ({$t['email']})</option>";
                } ?>
            </select>

            <label>Subject:</label>
            <input type="text" name="subject" id="subject" readonly placeholder="Select a teacher to load subject" value="<?= $edit_mode ? htmlspecialchars($edit_data['subject']) : '' ?>">

            <label>Grade:</label>
            <select name="grade" id="grade" required>
                <option value="">-- Select Grade --</option>
                <?php 
                mysqli_data_seek($grades, 0);
                while ($g = mysqli_fetch_assoc($grades)) {
                    $sel = ($edit_mode && $edit_data['grade'] == $g['grade']) ? "selected" : "";
                    echo "<option value='{$g['grade']}' {$sel}>Grade {$g['grade']}</option>";
                } ?>
            </select>

            <label>Sections (hold Ctrl to select multiple):</label>
            <select name="section[]" id="section" multiple required>
                <?php 
                mysqli_data_seek($sections, 0);
                while ($s = mysqli_fetch_assoc($sections)) {
                    $optval = $s['section'];
                    $sel = ($edit_mode && $edit_data['section'] == $optval) ? "selected" : "";
                    echo "<option value='{$s['section']}' {$sel}>Section {$s['section']}</option>";
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
                        $sel = ($edit_mode && $edit_data['year'] == $y['year']) ? "selected" : "";
                        echo "<option value='{$y['year']}' {$sel}>{$y['year']}</option>";
                    }
                    $current = date("Y");
                    $curr_range = $current . "-" . ($current + 1);
                    if (!in_array($curr_range, $years)) {
                        $sel = ($edit_mode && $edit_data['year'] == $curr_range) ? "selected" : "";
                        echo "<option value='{$curr_range}' {$sel}>{$curr_range}</option>";
                    }
                ?>
            </select>

            <?php if ($edit_mode): ?>
                <input type="hidden" name="edit_id" value="<?= intval($edit_data['id']) ?>">
                <input type="submit" name="update" value="Update Assignment">
                <a href="<?= $_SERVER['PHP_SELF'] ?>" style="display:inline-block;margin-left:10px;padding:8px 12px;background:#ccc;color:#000;border-radius:6px;text-decoration:none;">Cancel</a>
            <?php else: ?>
                <input type="submit" name="save" value="Assign Teacher">
            <?php endif; ?>
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
                <th>Actions</th>
            </tr>
            <?php
            if (mysqli_num_rows($current_assignments) > 0) {
                while ($row = mysqli_fetch_assoc($current_assignments)) {
                    $assign_id = intval($row['assign_id']);
                    echo "<tr>
                            <td>" . htmlspecialchars($row['name']) . "</td>
                            <td>" . htmlspecialchars($row['subject']) . "</td>
                            <td>Grade " . htmlspecialchars($row['grade']) . "</td>
                            <td>" . htmlspecialchars($row['section']) . "</td>
                            <td>" . htmlspecialchars($row['year']) . "</td>
                            <td>
                                <a href='?edit_id={$assign_id}' style='margin-right:8px;'>Edit</a>
                                <a href='?delete_id={$assign_id}' onclick=\"return confirm('Are you sure you want to delete this assignment?');\" style='color:#a40000;'>Delete</a>
                            </td>
                        </tr>";
                }
            } else {
                echo "<tr><td colspan='6' style='text-align:center;color:#777;'>No assignments yet.</td></tr>";
            }
            ?>
        </table>

        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?><a href="?page=<?= $page-1 ?>">&laquo; Prev</a><?php endif; ?>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?= $i ?>" class="<?= ($i == $page ? 'active' : '') ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?><a href="?page=<?= $page+1 ?>">Next &raquo;</a><?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
