<?php
include ('partials/constants.php');
if (session_status() === PHP_SESSION_NONE) session_start();

// ✅ Check if logged in as admin

// ✅ Fetch ALL school years (no filter)
$years = $conn->query("
    SELECT DISTINCT year 
    FROM assign_teacher 
    ORDER BY year ASC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>All Assigned Sections</title>
<link rel="stylesheet" href="../css/recordg.css">
<style>
body {
    font-family: "Poppins", sans-serif;
    background: #f5f5fa;
    color: #222;
    margin: 0;
    padding: 20px;
}
h1 {
    text-align: center;
    color: #333;
    margin-bottom: 20px;
}
.search-container {
    text-align: center;
    margin-bottom: 25px;
}
.search-container input {
    padding: 10px;
    width: 300px;
    border-radius: 8px;
    border: 1px solid #ccc;
}
.year-block {
    margin-bottom: 30px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 3px 8px rgba(0,0,0,0.1);
    padding: 15px;
}
.year-title {
    background: #020000ff;
    color: #fff;
    padding: 10px 15px;
    border-radius: 8px;
    font-size: 18px;
    margin-bottom: 10px;
}
.grade-row {
    display: flex;
    gap: 20px;
    justify-content: space-around;
    flex-wrap: wrap;
}
.grade-column {
    flex: 1;
    background: #f8f9ff;
    padding: 10px;
    border-radius: 10px;
    min-width: 250px;
}
.grade-column h3 {
    background: #ecefff;
    border-radius: 8px;
    padding: 8px;
    text-align: center;
    color: #333;
}
.section {
    background: #fff;
    margin: 5px 0;
    padding: 8px;
    border-radius: 6px;
    cursor: pointer;
    transition: background 0.2s;
}
.section:hover {
    background: #fff0f0ff;
}
.student-list {
    display: none;
    margin-top: 10px;
}
table {
    width: 100%;    
    border-collapse: collapse;
    font-size: 14px;
}
th, td {
    border: 1px solid #ccc;
    padding: 6px 8px;
    text-align: center;
}
th {
    background: #f2f2f2;
}
a {
    text-decoration: none;
    color: #1e961eff;
}
a:hover {
    text-decoration: underline;
}
.teacher-list {
    font-size: 13px;
    background: #f2f2f2;
    border-radius: 6px;
    padding: 5px;
    margin-top: 5px;
}
</style>
</head>

<body>

<h1>📚 STUDENT RECORD WITH TEACHER</h1>

<div class="search-container">
    <input type="text" id="searchYear" placeholder="Search school year (e.g. 2022-2023)">
</div>

<div id="yearContainer">
<?php while ($y = $years->fetch_assoc()): ?>
    <div class="year-block" data-year="<?= strtolower($y['year']) ?>">
        <div class="year-title">School Year <?= htmlspecialchars($y['year']) ?></div>

        <div class="grade-row">
        <?php
        // 🔹 Get all grades for this year
        $grades = $conn->query("
            SELECT DISTINCT grade 
            FROM assign_teacher 
            WHERE year = '{$y['year']}'
            ORDER BY grade ASC
        ");

        while ($g_row = $grades->fetch_assoc()):
            $g = $g_row['grade'];

            // 🔹 Get all sections in this grade and year
            $sections = $conn->query("
                SELECT DISTINCT section 
                FROM assign_teacher 
                WHERE grade = '$g' 
                  AND year = '{$y['year']}'
                ORDER BY section ASC
            ");
        ?>
            <div class="grade-column">
                <h3>Grade <?= $g ?></h3>

                <?php if ($sections->num_rows > 0): ?>
                    <?php while ($s = $sections->fetch_assoc()):
                        $section = htmlspecialchars($s['section']);
                        $id = "y" . preg_replace('/[^a-zA-Z0-9]/', '', $y['year']) . "_g{$g}_s" . preg_replace('/[^a-zA-Z0-9]/', '', $section);
                    ?>
                        <div class="section" data-target="<?= $id ?>">📂 Section <?= $section ?></div>

                        <div class="student-list" id="<?= $id ?>">

                            <?php
                            // ✅ Show all teachers assigned to this grade-section-year
                            $teacher_sql = "
                                SELECT DISTINCT t.id AS teacher_id, t.name AS teacher_name, a.subject
                                FROM assign_teacher a
                                JOIN teacher_account t ON t.id = a.teacher_id
                                WHERE a.grade = '$g'
                                  AND a.section = '$section'
                                  AND a.year = '{$y['year']}'
                                ORDER BY t.name ASC
                            ";
                            $teachers = $conn->query($teacher_sql);
                            ?>

                            <div class="teacher-list">
                                <strong>👩‍🏫 Teachers assigned:</strong><br>
                                <?php
                                if ($teachers && $teachers->num_rows > 0) {
                                    while ($t = $teachers->fetch_assoc()) {
                                        echo htmlspecialchars($t['teacher_name']) . " — <em>" . htmlspecialchars($t['subject']) . "</em><br>";
                                    }
                                } else {
                                    echo "<em>No teachers assigned yet.</em>";
                                }
                                ?>
                            </div>

                            <?php
                            // 🔹 List students in this grade-section-year
                            $students = $conn->query("
                                SELECT id, CONCAT(surname, ', ', name, ' ', middle_name, '.') AS full_name, average 
                                FROM student 
                                WHERE grade = '$g' 
                                  AND section = '$section' 
                                  AND year = '{$y['year']}'
                                ORDER BY surname ASC
                            ");
                            ?>

                            <?php if ($students && $students->num_rows > 0): ?>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>View Grade</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php while ($stu = $students->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($stu['full_name']) ?></td>
                                            <td>
                                                <a href="Admin-viewgrade.php?id=<?= $stu['id'] ?>&year=<?= urlencode($y['year']) ?>">
                                                    View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p style="font-size:13px; color:#666;">No students yet.</p>
                            <?php endif; ?>

                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="font-size:13px; color:#666;">No sections found.</p>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
        </div>
    </div>
<?php endwhile; ?>
</div>

<script>
document.querySelectorAll(".section").forEach(sec => {
    sec.addEventListener("click", () => {
        const target = document.getElementById(sec.dataset.target);
        target.style.display = (target.style.display === "block") ? "none" : "block";
    });
});

document.getElementById("searchYear").addEventListener("keyup", function() {
    let input = this.value.toLowerCase();
    let years = document.querySelectorAll(".year-block");
    years.forEach(block => {
        let yearText = block.dataset.year;
        block.style.display = yearText.includes(input) ? "block" : "none";
    });
});
</script>

</body>
</html>
