<?php
include('../partials-front/constantsss.php');
if (session_status() === PHP_SESSION_NONE) session_start();

$message = ""; // Variable to hold success/error messages

// --- START: FORM SUBMISSION (INSERTION LOGIC) ---
if (isset($_POST['submit'])) {
    // 1. Get data from the form
    $student_id     = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
    $student_name   = $_POST['student_name'] ?? ''; // Add this line
    $offense_type   = $_POST['offense_type'] ?? '';
    $description    = $_POST['description'] ?? '';
    $action_taken   = $_POST['action_taken'] ?? '';
    $severity       = $_POST['severity'] ?? '';
    $status         = $_POST['status'] ?? '';
    $date_reported  = $_POST['date_reported'] ?? '';
    $quarter        = $_POST['quarter'] ?? '';
    
    // NOTE: 'reported_by' and 'role' are not in the form. Using placeholders.
    $reported_by    = 'Guidance Counselor'; 
    $role           = 'Student affairs'; 

    // Basic validation
    if ($student_id <= 0 || empty($offense_type) || empty($action_taken) || empty($date_reported)) {
        $message = "<div class='error'>❌ Required fields are missing.</div>";
    } else {
        // Validate date against student's school year
        $stmtYear = $conn->prepare("SELECT year FROM student WHERE id = ?");
        $stmtYear->bind_param('i', $student_id);
        $stmtYear->execute();
        $yearResult = $stmtYear->get_result();
        $student = $yearResult->fetch_assoc();
        $stmtYear->close();

        if ($student) {
            $years = explode('-', $student['year']);
            $start_year = $years[0];
            $end_year = $years[1];
            $valid_start_date = $start_year . '-06-01';
            $valid_end_date = $end_year . '-03-31';

            if ($date_reported < $valid_start_date || $date_reported > $valid_end_date) {
                $message = "<div class='error'>❌ Date must be between June {$start_year} and March {$end_year} (School Year: {$student['year']})</div>";
                return;
            }
        }
        // 2. Update SQL query to include student_name
        $sql = "INSERT INTO discipline_records (
                    student_id, student_name, reported_by, role, offense_type, 
                    description, action_taken, severity, status, 
                    date_reported, quarter
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param(
                "issssssssss", 
                $student_id, $student_name, $reported_by, $role, $offense_type,
                $description, $action_taken, $severity, $status, 
                $date_reported, $quarter
            );

            // 3. Execute the query
            if ($stmt->execute()) {
                $message = "<div class='success'>✅ Disciplinary record added successfully!</div>";
            } else {
                $message = "<div class='error'>❌ Failed to add record: " . $stmt->error . "</div>";
            }
            $stmt->close();
        } else {
            $message = "<div class='error'>❌ Database error: Could not prepare statement.</div>";
        }
    }
}
// --- END: FORM SUBMISSION (INSERTION LOGIC) ---


// Fetch all students for dropdown (needed for initial load and post-submission)
$students = $conn->query("SELECT id, CONCAT(surname, ', ', name, ' ', middle_name) AS fullname, section, year FROM student ORDER BY surname ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Disciplinary Action</title>
    <link rel="stylesheet" href="../css/style.css">
    <!-- Add Tom Select for searchable dropdown -->
    <link href="https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select/dist/js/tom-select.complete.min.js"></script>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f9f9f9;
            margin: 0;
            padding: 20px;
        }

        .form-container {
            max-width: 700px;
            margin: 40px auto;
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        h2 {
            text-align: center;
            color: #000000ff;
        }

        label {
            font-weight: bold;
        }

        input, textarea, select {
            width: 100%;
            padding: 8px;
            margin: 6px 0 15px 0;
            border: 1px solid #ccc;
            border-radius: 6px;
        }

        button {
            background-color: #032de9ff;
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 6px;
            cursor: pointer;
        }

        button:hover {
            background-color: #0032d4ff;
        }

        /* Message Styles */
        .success {
            color: #155724; /* Dark green text */
            font-weight: bold;
            text-align: center;
            padding: 10px;
            border: 1px solid #c3e6cb;
            background-color: #d4edda;
            margin-bottom: 15px;
            border-radius: 6px;
        }
        .error {
            color: #721c24; /* Dark red text */
            font-weight: bold;
            text-align: center;
            padding: 10px;
            border: 1px solid #f5c6cb;
            background-color: #f8d7da;
            margin-bottom: 15px;
            border-radius: 6px;
        }
        /* -------- MODAL STYLES -------- */
        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background: rgba(0,0,0,0.5);
        }

        .modal-content {
            background: #fff;
            margin: 5% auto;
            padding: 20px;
            border-radius: 12px;
            width: 85%;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
            animation: fadeIn 0.3s ease;
        }

        .close {
            float: right;
            font-size: 24px;
            font-weight: bold;
            color: #333;
            cursor: pointer;
        }

        @keyframes fadeIn {
            from {opacity: 0;}
            to {opacity: 1;}
        }
    </style>
</head>
<body>

<div class="form-container">
    <h2>Add Disciplinary Action</h2>
    
    <?php
    // Display Message (Success/Error)
    echo $message;
    ?>

    <form action="" method="POST"> <label>Student Name</label>
        <select name="student_id" id="studentSelect" required>
            <option value="">-- Select Student --</option>
            <?php while ($row = $students->fetch_assoc()): ?>
                <option value="<?php echo $row['id']; ?>" 
                        data-section="<?php echo htmlspecialchars($row['section']); ?>"
                        data-year="<?php echo htmlspecialchars($row['year']); ?>"
                        data-name="<?php echo htmlspecialchars($row['fullname']); ?>">
                    <?php echo htmlspecialchars($row['fullname']); ?> 
                    (S.Y. <?php echo htmlspecialchars($row['year']); ?>)
                </option>
            <?php endwhile; ?>
        </select>

        <input type="hidden" name="student_name" id="studentNameField">
        
        <label>Section</label>
        <input type="text" name="section" id="sectionField" readonly>

        <label>Offense Type</label>
        <input type="text" name="offense_type" required>

        <label>Description</label>
        <textarea name="description" rows="4" required></textarea>

        <label>Action Taken</label>
        <textarea name="action_taken" rows="3" required></textarea>

        <label>Severity</label>
        <select name="severity" required>
            <option value="Minor">Minor</option>
            <option value="Major">Major</option>
            <option value="Critical">Critical</option>
        </select>

        <label>Status</label>
        <select name="status" required>
            <option value="Pending">Pending</option>
            <option value="Resolved">Resolved</option>
        </select>

        <label>Date Reported</label>
        <input type="date" name="date_reported" required>
        </select>

        <button type="submit" name="submit">Add Record</button>
        &nbsp;
        <button type="button" id="openDisciplineBtn">View Discipline List</button>
    </form>
</div>

<div id="disciplineModal" class="modal">
  <div class="modal-content">
    <span class="close">&times;</span>
    <h3>Existing Discipline Records</h3>
    <div id="disciplineContent">Loading...</div>
  </div>
</div>

<script>
// Initialize Tom Select for searchable dropdown
new TomSelect('#studentSelect', {
    placeholder: 'Search for a student...',
    allowEmptyOption: true
});

// AUTO-FILL SECTION AND DATE VALIDATION
document.getElementById('studentSelect').addEventListener('change', function() {
    var selectedOption = this.options[this.selectedIndex];
    var section = selectedOption.getAttribute('data-section');
    var year = selectedOption.getAttribute('data-year');
    var name = selectedOption.getAttribute('data-name');
    
    document.getElementById('sectionField').value = section || '';
    document.getElementById('studentNameField').value = name || '';

    // Handle date validation based on school year
    var dateInput = document.querySelector('[name="date_reported"]');
    if (year) {
        const [startYear, endYear] = year.split('-');
        const validStartDate = `${startYear}-06-01`;  // June 1st of start year
        const validEndDate = `${endYear}-03-31`;      // March 31st of end year
        
        dateInput.min = validStartDate;
        dateInput.max = validEndDate;
        dateInput.title = `Valid dates: ${validStartDate} to ${validEndDate} (S.Y. ${year})`;
    } else {
        dateInput.removeAttribute('min');
        dateInput.removeAttribute('max');
        dateInput.removeAttribute('title');
    }
});

// OPEN MODAL
document.getElementById('openDisciplineBtn').addEventListener('click', function() {
    var modal = document.getElementById('disciplineModal');
    var content = document.getElementById('disciplineContent');
    modal.style.display = 'block';
    content.innerHTML = "Loading...";

    var xhr = new XMLHttpRequest();
    // Use the existing 'discipline.php' content fetching script 
    // (You still need this file for AJAX display, but not for form processing)
    xhr.open('GET', 'discipline.php', true); 
    xhr.onload = function() {
        if (xhr.status === 200) {
            content.innerHTML = xhr.responseText;
        } else {
            content.innerHTML = "Error loading content. Please check 'discipline.php'.";
        }
    };
    xhr.send();
});

// CLOSE MODAL
document.querySelector('.close').addEventListener('click', function() {
    document.getElementById('disciplineModal').style.display = 'none';
});

// CLOSE IF CLICKED OUTSIDE
window.onclick = function(event) {
    var modal = document.getElementById('disciplineModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
};
</script>

</body>
</html>