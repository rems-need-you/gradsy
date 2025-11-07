<?php
include('../partials-front/constantsss.php');
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['teacher_id'])) {
    header("Location: ../login.php");
    exit;
}

$teacher_id = $_SESSION['teacher_id'];

// ✅ Fetch all assigned subjects/sections for this teacher (with year)
$sql = "
    SELECT id, subject, grade, section, year
    FROM assign_teacher
    WHERE teacher_id = ?
    ORDER BY year DESC, grade ASC, section ASC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("SQL prepare failed: " . $conn->error);
}

$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Grade Sheets</title>
<style>
body {
  font-family: Arial, sans-serif;
  background: #f6f6f6;
  padding: 20px;
}
h1 {
  text-align: center;
  margin-bottom: 20px;
}
.folder {
  background: #fff;
  padding: 15px;
  border-radius: 10px;
  margin: 10px 0;
  box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}
.folder a {
  text-decoration: none;
  color: #333;
  font-weight: bold;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.folder:hover {
  background: #f0f0f0;
}
.year-label {
  background: #b80505;
  color: #fff;
  padding: 3px 8px;
  border-radius: 5px;
  font-size: 13px;
}
</style>
</head>
<body>
<h1>📁 My Classes Grade Sheet</h1>

<?php if ($result->num_rows > 0): ?>
  <?php while ($row = $result->fetch_assoc()): ?>
    <div class="folder">
      <a href="trys.php?assign_id=<?= $row['id'] ?>">
        <span>
          <?= "Grade " . htmlspecialchars($row['grade']) . 
             " - Section " . htmlspecialchars($row['section']) . 
             " (" . htmlspecialchars($row['subject']) . ")" ?>
        </span>
        <span class="year-label"><?= htmlspecialchars($row['year']) ?></span>
      </a>
    </div>
  <?php endwhile; ?>
<?php else: ?>
  <p>No assigned subjects yet.</p>
<?php endif; ?>

</body>
</html>
