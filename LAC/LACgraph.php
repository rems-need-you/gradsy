<?php
// index.php

include('../partials-front/constantsss.php');

// CRITICAL: Updated Query to fetch necessary data for PHP calculation and filtering
// 1. We need the Base Average (s.average).
// 2. We need the latest Extracurricular percent (p.percent).
// 3. The initial quarter-by-quarter filter (g.qX < 86) is handled in the WHERE clause.

$sql = "
    SELECT 
        s.id,
        CONCAT(s.surname, ', ', s.name, ' ', s.middle_name, '.') AS fullname,
        s.grade, 
        s.section, 
        s.year, 
        s.average AS base_average,
        /* Use subquery to check if ANY extracurricular records exist */
        EXISTS (
            SELECT 1 FROM participations WHERE student_id = s.id
        ) as has_extra,
        COALESCE(p.percent, 0.0) AS extra_percent,
        COALESCE(d.has_major_offense, 0) AS has_major_offense
    FROM student s
    LEFT JOIN (
        SELECT student_id, percent
        FROM participations
        WHERE (student_id, created_at) IN (
            SELECT student_id, MAX(created_at)
            FROM participations
            GROUP BY student_id
        )
    ) p ON p.student_id = s.id
    LEFT JOIN (
        SELECT student_id, MAX(CASE WHEN severity = 'Major' THEN 1 ELSE 0 END) AS has_major_offense
        FROM discipline_records
        GROUP BY student_id
    ) d ON d.student_id = s.id
    WHERE s.grade IS NOT NULL 
    AND s.average IS NOT NULL
    AND s.average >= 86 
    AND COALESCE(d.has_major_offense, 0) = 0
    /* ✅ Exclude students with ANY quarterly grade below 86 */
    AND NOT EXISTS (
        SELECT 1
        FROM grades3 g
        WHERE g.student_id = s.id
        AND g.quarterly < 86
    )
";

$result = $conn->query($sql);

$students = [];
$grades = [];
$sections = [];
$years = [];
$rankings = []; // Store original rankings

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $baseAverage = (float)$row["base_average"];
        $extraPercent = (float)$row["extra_percent"];
        $hasExtra = (bool)$row["has_extra"];
        
        // Only show extra if student has extracurricular records
        $withExtra = $hasExtra ? round($baseAverage + $extraPercent, 2) : null;
        $withoutExtra = round($baseAverage, 2);
        
        if ($baseAverage >= 86) {
            $students[] = [
                "id" => (int)$row["id"],
                "name" => $row["fullname"],
                "grade" => (int)$row["grade"],
                "section" => (int)$row["section"],
                "year" => $row["year"],
                "has_extra" => $hasExtra,
                "average_with_extra" => $withExtra,
                "average_without_extra" => $withoutExtra
            ];
            $grades[] = $row["grade"];
            $sections[] = $row["section"];
            $years[] = $row["year"];
        }
    }
    
    // Sort considering null values for those without extra
    usort($students, function($a, $b) {
        $aVal = $a['average_with_extra'] ?? $a['average_without_extra'];
        $bVal = $b['average_with_extra'] ?? $b['average_without_extra'];
        return $bVal - $aVal;
    });
    
    foreach ($students as $index => $student) {
        $rankings[$student['id']] = $index + 1;
    }
}
$conn->close();

// Unique + sorted values for filters
$grades = array_unique($grades);
sort($grades);

$sections = array_unique($sections);
sort($sections);

$years = array_unique($years);
sort($years);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Ranking Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../css/graph.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* CSS for the Modal */
        .modal {
            display: none; 
            position: fixed; 
            z-index: 1000; 
            left: 0;
            top: 0;
            width: 100%; 
            height: 100%; 
            overflow: auto; 
            background-color: rgba(0,0,0,0.4); 
        }
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto; /* Adjusted margin to be higher */
            padding: 20px;
            border: 1px solid #888;
            width: 90%; 
            max-width: 950px; /* Increased max width to fit 7 columns */
            border-radius: 8px;
            position: relative;
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }
        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }
        .clickable-name {
            cursor: pointer;
            color: #007bff; /* Highlight as link */
            text-decoration: underline;
        }
        #disciplinaryTable th, #extracurricularTable th {
            text-align: left;
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }
        #disciplinaryTable td, #extracurricularTable td {
            padding: 8px;
            border-bottom: 1px solid #eee;
        }
    </style>
</head>
<body>

<header>
    <h1>Top Student Rankings</h1>
</header>

<div class="container">
    <div class="filters">
        <input type="text" id="search" placeholder="Search student by name...">

        <select id="gradeFilter">
            <option value="">All Grades</option>
            <?php foreach($grades as $g): ?>
                <option value="<?= $g ?>">Grade <?= $g ?></option>
            <?php endforeach; ?>
        </select>

        <select id="yearFilter">
            <option value="">All Years</option>
            <?php foreach($years as $y): ?>
                <option value="<?= $y ?>"><?= $y ?></option>
            <?php endforeach; ?>
        </select>

        <select id="sectionFilter">
            <option value="">All Sections</option>
            <?php foreach($sections as $s): ?>
                <option value="<?= $s ?>">Section <?= $s ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <table>
        <thead>
            <tr>
                <th>Rank</th>
                <th>Name</th>
                <th>Grade</th>
                <th>Section</th>
                <th>Year</th>
                <th>With Extracurricular</th>
                <th>Without Extracurricular</th>
            </tr>
        </thead>
        <tbody id="studentTable"></tbody>
    </table>

    <div id="chartContainer">
        <canvas id="avgChart"></canvas>
    </div>
</div>

<div id="studentModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2 id="modalStudentName"></h2>
        
        <hr>
        
        <h3>Extracurricular Records 🏆</h3>
        <table id="extracurricularTable" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th>Activity</th>
                    <th>Category</th>
                    <th>Level</th>
                    <th>Rank</th>
                    <th>Percent</th>
                    <th>Date</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
        <p id="noExtracurricular" style="display:none; color: gray;">No extracurricular records found.</p>

        <hr>
        
        <h3>Disciplinary Records ⚠️</h3>
        <table id="disciplinaryTable" style="width: 100%; border-collapse: collapse;">
            <thead><tr><th>Offense Type</th><th>Action Taken</th><th>Severity</th><th>Date Reported</th></tr></thead>
            <tbody></tbody>
        </table>
        <p id="noDisciplinary" style="display:none; color: gray;">No disciplinary records found.</p>
    </div>
</div>

<script>
// Embed PHP data into JS
const students = <?php echo json_encode($students); ?>;
const rankings = <?php echo json_encode($rankings); ?>;

let chartInstance = null;
const studentModal = document.getElementById('studentModal');
const closeModal = document.querySelector('.close');

// Close modal when X is clicked
closeModal.onclick = function() {
    studentModal.style.display = 'none';
}

// Close modal when clicking outside of it
window.onclick = function(event) {
    if (event.target == studentModal) {
        studentModal.style.display = 'none';
    }
}


function renderTable(data) {
    const tbody = document.getElementById('studentTable');
    tbody.innerHTML = '';
    
    data.forEach((student) => {
        const nameDisplay = `<span class="clickable-name" onclick="showStudentDetails(${student.id}, '${student.name.replace(/'/g, "\\'")}')">${student.name}</span>`;
        
        tbody.innerHTML += `
            <tr>
                <td class="rank">Top ${student.filtered_rank}</td>
                <td>${nameDisplay}</td>
                <td>Grade ${student.grade}</td>
                <td>Section ${student.section}</td>
                <td>${student.year}</td>
                <td>${student.has_extra ? student.average_with_extra.toFixed(2) : '-'}</td>
                <td>${student.average_without_extra.toFixed(2)}</td>
            </tr>
        `;
    });
}

/**
 * Fetches and displays the disciplinary and extracurricular records.
 * @param {number} studentId The ID of the student.
 * @param {string} studentName The full name of the student.
 */
async function showStudentDetails(studentId, studentName) {
    document.getElementById('modalStudentName').textContent = 'Details for ' + studentName;

    // Clear previous data
    const extraBody = document.getElementById('extracurricularTable').getElementsByTagName('tbody')[0];
    const disciplineBody = document.getElementById('disciplinaryTable').getElementsByTagName('tbody')[0];
    
    // Set column span to 7 for the loading message
    extraBody.innerHTML = '<tr><td colspan="7">Loading...</td></tr>'; 
    disciplineBody.innerHTML = '<tr><td colspan="4">Loading...</td></tr>';
    document.getElementById('noExtracurricular').style.display = 'none';
    document.getElementById('noDisciplinary').style.display = 'none';
    
    // Show the modal immediately
    studentModal.style.display = 'block';

    try {
        const response = await fetch(`get_student_details.php?id=${studentId}`);
        const data = await response.json();

        // 1. Render Extracurricular Records - UPDATED LOGIC FOR 7 COLUMNS
        extraBody.innerHTML = ''; // Clear loading
        if (data.extracurricular && data.extracurricular.length > 0) {
            data.extracurricular.forEach(record => {
                // Use nullish coalescing to safely handle null/undefined remarks
                const remarks = record.remarks ?? ''; 
                
                extraBody.innerHTML += `
                    <tr>
                        <td>${record.activity_title}</td>
                        <td>${record.category}</td>
                        <td>${record.level}</td>
                        <td>${record.rank_position}</td>
                        <td>${record.percent}</td>
                        <td>${record.date_participated}</td>
                        <td>${remarks}</td>
                    </tr>
                `;
            });
        } else {
            document.getElementById('noExtracurricular').style.display = 'block';
            extraBody.innerHTML = '';
        }

        // 2. Render Disciplinary Records
        disciplineBody.innerHTML = ''; // Clear loading
        if (data.disciplinary && data.disciplinary.length > 0) {
            data.disciplinary.forEach(record => {
                disciplineBody.innerHTML += `
                    <tr>
                        <td>${record.offense_type}</td>
                        <td>${record.action_taken}</td>
                        <td>${record.severity}</td>
                        <td>${record.date_reported}</td>
                    </tr>
                `;
            });
        } else {
            document.getElementById('noDisciplinary').style.display = 'block';
            disciplineBody.innerHTML = '';
        }

    } catch (error) {
        console.error('Error fetching student details:', error);
        alert('Could not fetch student details. Check get_student_details.php.');
        extraBody.innerHTML = '<tr><td colspan="7" style="color:red;">Error loading data.</td></tr>'; // Updated colspan to 7
        disciplineBody.innerHTML = '<tr><td colspan="4" style="color:red;">Error loading data.</td></tr>';
    }
}


function renderChart(data) {
    const top50 = data.slice(0, 50);
    const labels = top50.map(s => `Rank ${s.filtered_rank} - ${s.name}`);
    
    const ctx = document.getElementById('avgChart');
    if (chartInstance) chartInstance.destroy();

    const datasets = [{
        label: 'Without Extracurricular',
        data: top50.map(s => s.average_without_extra),
        backgroundColor: 'rgba(192, 75, 75, 0.6)',
        borderColor: 'rgba(192, 75, 75, 1)',
        borderWidth: 1
    }];

    // Only add extracurricular dataset if at least one student has extra points
    if (top50.some(s => s.has_extra)) {
        datasets.unshift({
            label: 'With Extracurricular',
            data: top50.map(s => s.has_extra ? s.average_with_extra : null),
            backgroundColor: 'rgba(75, 192, 192, 0.6)',
            borderColor: 'rgba(75, 192, 192, 1)',
            borderWidth: 1
        });
    }

    chartInstance = new Chart(ctx, {
        type: 'bar',
        data: { labels, datasets },
        options: {
            plugins: {
                title: { display: true, text: 'Data Analytics' }
            },
            scales: {
                y: { beginAtZero: true, suggestedMax: 100, min: 86 }
            }
        }
    });
}

function applyFilters() {
    const keyword = document.getElementById('search').value.toLowerCase();
    const year = document.getElementById('yearFilter').value;
    const grade = document.getElementById('gradeFilter').value;
    const section = document.getElementById('sectionFilter').value;

    // Filter students based on criteria
    const filtered = students.filter(s =>
        s.name.toLowerCase().includes(keyword) &&
        (!year || s.year == year) &&
        (!grade || s.grade == grade) &&
        (!section || s.section == section)
    );

    // If filtering by year/grade/section, recalculate rankings within that group
    if (year || grade || section) {
        // Sort by average within the filtered group
        filtered.sort((a, b) => {
            const aVal = a.average_with_extra ?? a.average_without_extra;
            const bVal = b.average_with_extra ?? b.average_without_extra;
            return bVal - aVal;
        });
        // Assign new ranks 1,2,3... within the filtered group
        filtered.forEach((student, index) => {
            student.filtered_rank = index + 1;
        });
    } else {
        // No filters active - use original global rankings
        filtered.sort((a, b) => rankings[a.id] - rankings[b.id]);
        filtered.forEach(student => {
            student.filtered_rank = rankings[student.id];
        });
    }

    renderTable(filtered);
    renderChart(filtered);
}

// Add events
document.getElementById('search').addEventListener('input', applyFilters);
document.getElementById('yearFilter').addEventListener('change', applyFilters);
document.getElementById('gradeFilter').addEventListener('change', applyFilters);
document.getElementById('sectionFilter').addEventListener('change', applyFilters);

applyFilters(); // initial render
</script>
</body>
</html>