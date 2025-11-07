<?php
include('../partials-front/constantsss.php');
if (session_status() === PHP_SESSION_NONE) session_start();

// ✅ Check if teacher is logged in
if (!isset($_SESSION['teacher_id'])) {
    echo "<script>alert('Please login first.'); window.location.href='login.php';</script>";
    exit();
}

$teacher_id = $_SESSION['teacher_id'];

// ✅ Check if assign_teacher table exists
$table_exists = $conn->query("SHOW TABLES LIKE 'assign_teacher'");
if (!$table_exists || $table_exists->num_rows === 0) {
    echo "<h2 style='text-align:center; color:#777;'>⚠️ No assign_teacher table found.</h2>";
    exit();
}

// ✅ Get teacher's assigned grade-section-year ONLY
$sql = "
    SELECT DISTINCT 
        year, grade, section
    FROM assign_teacher
    WHERE teacher_id = ?
    ORDER BY year DESC, grade ASC, section ASC
";

$assigned = $conn->prepare($sql);
if (!$assigned) {
    die('Prepare failed: ' . $conn->error);
}
$assigned->bind_param('i', $teacher_id);
$assigned->execute();
$result = $assigned->get_result();

$teacher_years = [];
while ($row = $result->fetch_assoc()) {
    $year = $row['year'];
    $grade = $row['grade'];
    $section = $row['section'];

    if (!isset($teacher_years[$year])) $teacher_years[$year] = [];
    if (!isset($teacher_years[$year][$grade])) $teacher_years[$year][$grade] = [];
    $teacher_years[$year][$grade][] = $section;
}
$assigned->close();

// ✅ If no assigned classes, show message
if (empty($teacher_years)) {
    echo "<h2 style='text-align:center; color:#777;'>You have no assigned classes yet.</h2>";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Organized Grades by School Year</title>
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
    background: #000000ff;
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
}
.grade-column {
    flex: 1;
    background: #f8f9ff;
    padding: 10px;
    border-radius: 10px;
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
a, button.btn-view-grade {
    text-decoration: none;
    color: #fff;
    background: #05b823ff;
    border: none;
    border-radius: 6px;
    padding: 5px 10px;
    cursor: pointer;
}
a:hover, button.btn-view-grade:hover {
    background: #1eff00ff;
}

/* ✅ Modal Styles */
#gradeModal { position: fixed; inset: 0; z-index: 9999; display: none; }
#gradeModalBackdrop {
    position:absolute; inset:0; background:rgba(0,0,0,0.5);
}
#gradeModalContent {
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    width: 90%;
    max-width: 1000px;
    max-height: 85vh;
    overflow: auto;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
    padding: 16px;
}
#gradeModalClose {
    position: absolute;
    right: 10px;
    top: 8px;
    background: transparent;
    border: none;
    font-size: 18px;
    cursor: pointer;
}
</style>
</head>

<body>

<h1>📚 STUDENT RECORDS</h1>

<div class="search-container">
    <input type="text" id="searchYear" placeholder="Search school year (e.g. 2022-2023)">
</div>

<div id="yearContainer">
<?php foreach ($teacher_years as $year => $grades): ?>
    <div class="year-block" data-year="<?= strtolower($year) ?>">
        <div class="year-title">School Year <?= htmlspecialchars($year) ?></div>

        <div class="grade-row">
        <?php foreach ($grades as $grade => $sections): ?>
            <div class="grade-column">
                <h3>Grade <?= $grade ?></h3>

                <?php foreach ($sections as $section): 
                    $id = "y" . preg_replace('/[^a-zA-Z0-9]/', '', $year) . 
                          "_g{$grade}_s" . preg_replace('/[^a-zA-Z0-9]/', '', $section);
                ?>
                    <div class="section" data-target="<?= $id ?>">📄 Section <?= htmlspecialchars($section) ?></div>

                    <div class="student-list" id="<?= $id ?>">
                        <?php
                        // Original students query
                        $students = $conn->query("
                            SELECT id, CONCAT(surname, ', ', name, ' ', middle_name, '.') AS full_name, average 
                            FROM student 
                            WHERE grade = '$grade' 
                              AND section = '" . $conn->real_escape_string($section) . "' 
                              AND year = '" . $conn->real_escape_string($year) . "' 
                            ORDER BY surname ASC
                        ");
                        if ($students->num_rows > 0):
                        ?>
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
                                                <button class="btn-view-grade"
                                                    data-student-id="<?= htmlspecialchars($stu['id']) ?>"
                                                    data-year="<?= htmlspecialchars($year) ?>">
                                                    View
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p style="font-size:13px; color:#666;">No students yet.</p>
                        <?php endif; 
                        ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>
</div>

<!-- ✅ Modal HTML -->
<div id="gradeModal">
    <div id="gradeModalBackdrop"></div>
    <div id="gradeModalContent">
        <button id="gradeModalClose" aria-label="Close">✕</button>
        <div id="gradeModalInner">Loading...</div>
    </div>
</div>

<script>
// 🔽 Collapse / expand section logic
document.querySelectorAll(".section").forEach(sec => {
    sec.addEventListener("click", () => {
        const target = document.getElementById(sec.dataset.target);
        target.style.display = (target.style.display === "block") ? "none" : "block";
    });
});

// 🔍 Search function by school year
document.getElementById("searchYear").addEventListener("keyup", function() {
    let input = this.value.toLowerCase();
    let years = document.querySelectorAll(".year-block");
    years.forEach(block => {
        let yearText = block.dataset.year;
        block.style.display = yearText.includes(input) ? "block" : "none";
    });
});

// ✅ Modal script
function showModal(html) {
    const modal = document.getElementById('gradeModal');
    const inner = document.getElementById('gradeModalInner');
    inner.innerHTML = html;
    modal.style.display = 'block';
    document.getElementById('gradeModalClose').focus();
}
function hideModal() {
    document.getElementById('gradeModal').style.display = 'none';
    document.getElementById('gradeModalInner').innerHTML = '';
}
document.getElementById('gradeModalClose').addEventListener('click', hideModal);
document.getElementById('gradeModalBackdrop').addEventListener('click', hideModal);

document.querySelectorAll('.btn-view-grade').forEach(btn => {
    btn.addEventListener('click', async function() {
        const studentId = this.dataset.studentId;
        const year = this.dataset.year;
        showModal('<p style="padding:20px;text-align:center;">Loading grades…</p>');

        try {
            const resp = await fetch(`teacherviewg.php?id=${encodeURIComponent(studentId)}&year=${encodeURIComponent(year)}`, { credentials: 'same-origin' });
            if (!resp.ok) throw new Error('Failed to load');
            let html = await resp.text();

            // Try to extract only the grade content
            let extracted = html;
            try {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const content = doc.querySelector('#viewGrade, .view-grade, #content, .content, main');
                extracted = content ? content.outerHTML : doc.body.innerHTML;
            } catch(e){}

            showModal(extracted);
        } catch (err) {
            showModal(`<p style="padding:20px;text-align:center;color:red;">Error: ${err.message}</p>`);
        }
    });
});
</script>

</body>
</html>
