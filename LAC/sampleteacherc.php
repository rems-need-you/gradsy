<?php
include('../partials-front/constantsss.php');
if (session_status() === PHP_SESSION_NONE) session_start();

// ✅ Redirect if not a teacher
if (!isset($_SESSION['role']) || $_SESSION['role'] !== "teacher") {
    header('Location: ' . SITEURL . 'LAC/login.php');
    exit();
}

$teacher_id = $_SESSION['teacher_id'] ?? 0;
if ($teacher_id === 0) {
    header('Location: ' . SITEURL . 'LAC/login.php');
    exit();
}

// ✅ Fetch all assignments for this teacher
$sql = "
    SELECT a.id AS assign_id, a.subject, a.grade, a.section
    FROM assign_teacher a
    INNER JOIN teacher_account t ON a.teacher_id = t.id
    WHERE t.id = ?
    ORDER BY a.subject ASC, a.grade ASC, a.section ASC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang='en'>
<head>
<meta charset='UTF-8'>
<title>My Assigned Classes</title>
<style>
    body {
        font-family: 'Poppins', sans-serif;
        background: #faf8f8;
        margin: 0;
        padding: 0;
    }
    .container {
        width: 90%;
        max-width: 900px;
        margin: 40px auto;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        padding: 30px;
    }
    h1 {
        color: #a40000;
        text-align: center;
        margin-bottom: 20px;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }
    th, td {
        border: 1px solid #ddd;
        padding: 10px;
        text-align: left;
    }
    th {
        background-color: #a40000;
        color: #fff;
    }
    tr:nth-child(even) {
        background: #f9f9f9;
    }
    tr:hover {
        background: #fff2f2;
    }
    .btn {
        background: #a40000;
        color: white;
        padding: 8px 15px;
        border-radius: 6px;
        text-decoration: none;
        font-size: 14px;
    }
    .btn:hover {
        background: #c20000;
    }
    .no-data {
        text-align: center;
        color: #888;
        padding: 15px;
    }
</style>
</head>
<body>
<div class='container'>
    <h1>My Assigned Classes</h1>
    <table>
        <tr>
            <th>Subject</th>
            <th>Grade</th>
            <th>Section</th>
            <th>Action</th>
        </tr>
        <?php
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "
                <tr>
                    <td>{$row['subject']}</td>
                    <td>Grade {$row['grade']}</td>
                    <td>Section {$row['section']}</td>
                    <td>
                        <form action='set_session.php' method='POST' style='margin:0;'>
                            <input type='hidden' name='subject' value='{$row['subject']}'>
                            <input type='hidden' name='grade' value='{$row['grade']}'>
                            <input type='hidden' name='section' value='{$row['section']}'>
                            <button type='submit' class='btn'>Open Grade Sheet</button>
                        </form>
                    </td>
                </tr>";
            }
        } else {
            echo "<tr><td colspan='4' class='no-data'>No assignments found.</td></tr>";
        }
        ?>
    </table>
</div>
</body>
</html>
