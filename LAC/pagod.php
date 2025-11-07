<?php
include('../partials-front/constantsss.php');
if (session_status() === PHP_SESSION_NONE) session_start();

// ✅ Ensure teacher is logged in
if (!isset($_SESSION['teacher_id'])) {
    header("Location: ../login.php");
    exit;
}

// ===== Get Teacher & Assigned Info from Session =====
$teacher_id = $_SESSION['teacher_id'];
$teacher_name = $_SESSION['teacher_name'] ?? 'Teacher';
$assigned_subject = $_SESSION['subject'] ?? '';
$assigned_grade = $_SESSION['grade'] ?? '';
$assigned_section = $_SESSION['section'] ?? '';
$assigned_year = $_SESSION['year'] ?? '';

if (empty($assigned_subject) || empty($assigned_grade) || empty($assigned_section) || empty($assigned_year)) {
    echo "<h2 style='color:red;text-align:center;'>❌ No assigned subject/section/year found for this teacher.</h2>";
    exit;
}


// ===== Type Casting for Binding (Essential for int(11) columns) =====
// Cast $teacher_id (from session)
$teacher_id_int = (int)$teacher_id;
// Cast grade and section to integer based on the 'student' table structure
$grade_int = (int)$assigned_grade;
$section_int = (int)$assigned_section;

// ===== Fetch Teacher Info (for display) =====
$teacher_sql = "SELECT id, name, email, department FROM teacher_account WHERE id = ?";
$teacher_stmt = $conn->prepare($teacher_sql);
if ($teacher_stmt === false) {
    die("Error preparing teacher query: " . $conn->error);
}

$teacher_stmt->bind_param("i", $teacher_id_int);

if (!$teacher_stmt->execute()) {
    die("Error executing teacher query: " . $teacher_stmt->error);
}

$teacher_res = $teacher_stmt->get_result()->fetch_assoc();

// --------------------------------------------------------------------------
// ===== Fetch Students & Grades (FIXED SQL) =====
// Using INNER JOIN assign_teacher to filter the student cohort
// and LEFT JOIN grades3 to pull scores for that cohort/teacher.
$students_sql = "
    SELECT 
        s.id AS student_id,
        CONCAT(s.surname, ', ', s.name, ' ', s.middle_name, '.') AS student_name,
        g.quarter, g.ww, g.pt, g.qa
    FROM student s
    
    /* 1. INNER JOIN assign_teacher (a) to filter the cohort based on the assignment */
    INNER JOIN assign_teacher a
        ON s.grade = a.grade      
        AND s.section = a.section 
    
    /* 2. LEFT JOIN grades3 (g) to get the actual scores entered by this teacher */
    LEFT JOIN grades3 g 
        ON s.id = g.student_id 
        AND g.teacher_id = ? /* Placeholder 1: g.teacher_id */
        
    WHERE  
    a.teacher_id = ?        
    AND a.subject = ?       
    AND a.grade = ?         
    AND a.section = ?       
    AND a.year = ?           
    AND s.year = a.year       
        
    ORDER BY s.surname ASC, s.name ASC
";


$stmt = $conn->prepare($students_sql);

// IMPORTANT: Check for failure after prepare
if ($stmt === false) {
    // This will now show any MySQL errors in the query structure (e.g., column not found)
    die("Error preparing students query: " . $conn->error);
}

// Bind Parameters: i, i, s, i, i (5 placeholders)
// The correct sequence based on the SQL placeholders is: 
// teacher_id, teacher_id, assigned_subject, assigned_grade, assigned_section
$stmt->bind_param("iisiis", 
    $teacher_id_int, 
    $teacher_id_int, 
    $assigned_subject, 
    $grade_int, 
    $section_int,
    $assigned_year
);

if (!$stmt->execute()) {
    die("Error executing students query: " . $stmt->error);
}

$result = $stmt->get_result();
// --------------------------------------------------------------------------

// ===== Organize Data by Quarter =====
$quarters = []; 
while ($row = $result->fetch_assoc()) {
    $q = $row['quarter'] ?? 1;
    $row['ww'] = !empty($row['ww']) ? explode(",", $row['ww']) : [0, 0, 0, 0, 0];
    $row['pt'] = !empty($row['pt']) ? explode(",", $row['pt']) : [0, 0, 0];
    $row['qa'] = !empty($row['qa']) ? explode(",", $row['qa']) : [0];
    $quarters[$q][] = $row;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Grade Sheet - <?= htmlspecialchars($assigned_subject) ?></title>
<link rel="stylesheet" href="../css/gs.css">
</head>
<body>

<div class="container">
    <h1>Grade Sheet</h1>

    <h3>
        Teacher: <?= htmlspecialchars($teacher_res['name'] ?? 'N/A') ?> |
        Subject: <?= htmlspecialchars($assigned_subject) ?> |
        Grade: <?= htmlspecialchars($assigned_grade) ?> |
        Section: <?= htmlspecialchars($assigned_section) ?>
    </h3>

    <div id="quarters-container">
    <?php foreach($quarters as $qNum => $students): ?>
        <div class="quarter-block" data-quarter="<?= $qNum ?>">
            <h2><?= $qNum . ($qNum==1?"st":($qNum==2?"nd":($qNum==3?"rd":"th"))) ?> Quarter</h2>
            <div class="controls">
                <button class="deleteQuarterBtn">🗑 Delete Quarter</button>
                <button class="saveQuarterBtn">💾 Save Quarter</button>
            </div>
            <table class="gradeTable">
                <thead>
                    <tr>
                        <th rowspan="1">Student Name</th>
                        <th colspan="5">Written Works (20%)</th>
                        <th></th><th></th><th></th>
                        <th colspan="3">Performance Tasks (60%)</th>
                        <th></th><th></th><th></th>
                        <th colspan="3">Quarterly Assessment</th>
                        <th rowspan="2">Initial Grade</th>
                        <th rowspan="2">Quarterly Grade</th>
                        <th colspan="10">Conduct</th>
                    </tr>
                    <tr>
                        <th></th><th>1</th><th>2</th><th>3</th><th>4</th><th>5</th>
                        <th>Total</th><th>PS</th><th>WS</th>
                        <th>1</th><th>2</th><th>3</th>
                        <th>Total</th><th>PS</th><th>WS</th>
                        <th>1</th>  
                        <th>PS</th><th>WS</th>
                        <th>1</th><th>2</th>
                        <th>1</th><th>2</th>
                        <th>1</th><th>2</th>
                        <th>1</th><th>2</th>
                        <th>Ave</th><th>LG</th>
                    </tr>
                    <tr class="max-row">
                        <th>Possible Score</th>
                        <th><input type="number" value="10" class="max-ww"></th>
                        <th><input type="number" value="10" class="max-ww"></th>
                        <th><input type="number" value="10" class="max-ww"></th>
                        <th><input type="number" value="10" class="max-ww"></th>
                        <th><input type="number" value="10" class="max-ww"></th>
                        <th></th><th></th><th></th>
                        <th><input type="number" value="20" class="max-pt"></th>
                        <th><input type="number" value="20" class="max-pt"></th>
                        <th><input type="number" value="20" class="max-pt"></th>
                        <th></th><th></th><th></th>
                        <th><input type="number" value="20" class="max-qa"></th>
                        <th></th><th></th>
                        <th></th><th></th>
                        <th><input type="number" class="conduct" value="4"></th>
                        <th><input type="number" class="conduct" value="4"></th>
                        <th><input type="number" class="conduct" value="4"></th>
                        <th><input type="number" class="conduct" value="4"></th>
                        <th><input type="number" class="conduct" value="4"></th>
                        <th><input type="number" class="conduct" value="4"></th>
                        <th><input type="number" class="conduct" value="4"></th>
                        <th><input type="number" class="conduct" value="4"></th>
                        <th></th><th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($students as $row): ?>
                        <tr data-student="<?= $row['student_id'] ?>">
                            <td><?= $row['student_name'] ?></td>
                            <?php foreach($row['ww'] as $ww) echo "<td><input type='number' class='ww' value='$ww'></td>"; ?>
                            <td class="ww_total"></td><td class="ww_ps"></td><td class="ww_ws"></td>
                            <?php foreach($row['pt'] as $pt) echo "<td><input type='number' class='pt' value='$pt'></td>"; ?>
                            <td class="pt_total"></td><td class="pt_ps"></td><td class="pt_ws"></td>
                            <?php foreach($row['qa'] as $qa) echo "<td><input type='number' class='qa' value='$qa'></td>"; ?>
                            <td class="qa_ps"></td><td class="qa_ws"></td>
                            <td class="initial"></td>
                            <td class="quarterly"></td>
                            <td class="conduct-col"><input type="number" min="1" max="4" class="conduct-input" data-key="maka_diyos1" value="<?= htmlspecialchars($conduct['maka_diyos1'] ?? '') ?>"></td>
                            <td class="conduct-col"><input type="number" min="1" max="4" class="conduct-input" data-key="maka_diyos2" value="<?= htmlspecialchars($conduct['maka_diyos2'] ?? '') ?>"></td>
                            <td class="conduct-col"><input type="number" min="1" max="4" class="conduct-input" data-key="makatao1" value="<?= htmlspecialchars($conduct['makatao1'] ?? '') ?>"></td>
                            <td class="conduct-col"><input type="number" min="1" max="4" class="conduct-input" data-key="makatao2" value="<?= htmlspecialchars($conduct['makatao2'] ?? '') ?>"></td>
                            <td class="conduct-col"><input type="number" min="1" max="4" class="conduct-input" data-key="makakalikasan1" value="<?= htmlspecialchars($conduct['makakalikasan1'] ?? '') ?>"></td>
                            <td class="conduct-col"><input type="number" min="1" max="4" class="conduct-input" data-key="makakalikasan2" value="<?= htmlspecialchars($conduct['makakalikasan2'] ?? '') ?>"></td>
                            <td class="conduct-col"><input type="number" min="1" max="4" class="conduct-input" data-key="makabansa1" value="<?= htmlspecialchars($conduct['makabansa1'] ?? '') ?>"></td>
                            <td class="conduct-col"><input type="number" min="1" max="4" class="conduct-input" data-key="makabansa2" value="<?= htmlspecialchars($conduct['makabansa2'] ?? '') ?>"></td>
                            <td class="ave-cell"><?= htmlspecialchars($conduct['ave'] ?? '') ?></td>
                            <td class="lg-cell"><?= htmlspecialchars($conduct['lg'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>
    </div>

    <button id="addQuarterBtn">+ Add Quarter</button>
</div>


<script>
let quarterCount = <?= max(array_keys($quarters)) ?>;
document.getElementById('addQuarterBtn').onclick = async ()=>{
    quarterCount++;
    const res = await fetch('save_quarter.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({
            teacher_id: <?= $teacher_id_int ?>,
            subject: "<?= addslashes($assigned_subject) ?>",
            grade: "<?= $assigned_grade ?>",
            section: "<?= $assigned_section ?>",
            year: "<?= $assigned_year ?>",
            quarter: quarterCount,
            new_quarter: true
        })
    });
    const data = await res.json();
    if (data.success) {
        alert('✅ New quarter added successfully!');
        location.reload();
    } else {
        alert('❌ Failed to add quarter: ' + data.message);
    }
};

// ===== UPDATE ROW WITH DECIMAL PERCENT =====
function updateRow(row){
    let wwTotal=0, ptTotal=0, qaTotal=0;
    row.querySelectorAll('input.ww').forEach(i=> wwTotal += parseFloat(i.value)||0 );
    row.querySelectorAll('input.pt').forEach(i=> ptTotal += parseFloat(i.value)||0 );
    row.querySelectorAll('input.qa').forEach(i=> qaTotal += parseFloat(i.value)||0 );

    const wwMax = [...row.closest('table').querySelectorAll('.max-ww')].reduce((a,i)=>a+parseFloat(i.value),0);
    const ptMax = [...row.closest('table').querySelectorAll('.max-pt')].reduce((a,i)=>a+parseFloat(i.value),0);
    const qaMax = [...row.closest('table').querySelectorAll('.max-qa')].reduce((a,i)=>a+parseFloat(i.value),0);

    // calculate percentage (0-100 scale)
    let wwPercent = (wwMax>0)? (wwTotal/wwMax)*100 : 0;
    let ptPercent = (ptMax>0)? (ptTotal/ptMax)*100 : 0;
    let qaPercent = (qaMax>0)? (qaTotal/qaMax)*100 : 0;

    row.querySelector('.ww_total').textContent = wwTotal;
    row.querySelector('.pt_total').textContent = ptTotal;
    row.querySelector('.ww_ps').textContent = wwPercent.toFixed(2);
    row.querySelector('.ww_ws').textContent = ((wwPercent/100)*20).toFixed(2); // WS weight
    row.querySelector('.pt_ps').textContent = ptPercent.toFixed(2);
    row.querySelector('.pt_ws').textContent = ((ptPercent/100)*60).toFixed(2); // WS weight
    row.querySelector('.qa_ps').textContent = qaPercent.toFixed(2);
    row.querySelector('.qa_ws').textContent = ((qaPercent/100)*20).toFixed(2); // WS weight

    const initial = ((wwPercent/100)*20) + ((ptPercent/100)*60) + ((qaPercent/100)*20);
    row.querySelector('.initial').textContent = initial.toFixed(2);
    row.querySelector('.quarterly').textContent = Math.round(initial);
}

// ===== LIMIT INPUT =====
function enforceMaxInput(input){
    const table = input.closest("table");
    let maxVal = 0;
    if(input.classList.contains("ww")){
        const wwInputs = [...input.closest("tr").querySelectorAll(".ww")];
        const wwIndex = wwInputs.indexOf(input);
        const wwCols = table.querySelectorAll(".max-ww");
        if(wwCols[wwIndex]) maxVal = parseFloat(wwCols[wwIndex].value)||0;
    } 
    else if(input.classList.contains("pt")){
        const ptInputs = [...input.closest("tr").querySelectorAll(".pt")];
        const ptIndex = ptInputs.indexOf(input);
        const ptCols = table.querySelectorAll(".max-pt");
        if(ptCols[ptIndex]) maxVal = parseFloat(ptCols[ptIndex].value)||0;
    } 
    else if(input.classList.contains("qa")){
        const qaCol = table.querySelector(".max-qa");
        if(qaCol) maxVal = parseFloat(qaCol.value)||0;
    }
    if(input.value !== "" && parseFloat(input.value) > maxVal){
        input.value = maxVal;
    }
}

// ===== UPDATE HEADER TOTALS =====
function updateHeaderTotals(table){
    const wwMax = [...table.querySelectorAll('.max-ww')].reduce((a,i)=>a+(parseFloat(i.value)||0),0);
    const ptMax = [...table.querySelectorAll('.max-pt')].reduce((a,i)=>a+(parseFloat(i.value)||0),0);
    const qaMax = [...table.querySelectorAll('.max-qa')].reduce((a,i)=>a+(parseFloat(i.value)||0),0);
}

// ===== INITIAL CALC =====
document.querySelectorAll('.gradeTable').forEach(table=>{
    updateHeaderTotals(table);
});
document.querySelectorAll('tbody tr').forEach(r=>updateRow(r));
document.querySelectorAll('input.ww, input.pt, input.qa').forEach(i=>{
    i.addEventListener('input', ()=>{
        enforceMaxInput(i);
        updateRow(i.closest('tr'));
    });
});

// ===== AUTO-RECHECK POSSIBLE SCORE =====
document.querySelectorAll('.max-ww, .max-pt, .max-qa').forEach(maxInput=>{
    maxInput.addEventListener('input', ()=>{
        const table = maxInput.closest("table");
        updateHeaderTotals(table);
        document.querySelectorAll('tbody tr').forEach(r=>{
            r.querySelectorAll('input.ww, input.pt, input.qa').forEach(i=> enforceMaxInput(i));
            updateRow(r);
        });
    });
});

// ===== ADD, DELETE, SAVE QUARTER (Updated JS) =====
function bindDeleteButtons(){
    document.querySelectorAll('.deleteQuarterBtn').forEach(btn=>{
        btn.onclick = async ()=>{
            const block = btn.closest('.quarter-block');
            const quarter = block.dataset.quarter;
            if(quarter==="1"){ alert("❌ First Quarter cannot be deleted."); return; }
            if(!confirm("Are you sure? This will remove all its grades.")) return;
            const res = await fetch('save_quarter.php',{
                method:'POST',
                headers:{'Content-Type':'application/json'},
                body: JSON.stringify({
                    teacher_id: <?= $teacher_id_int ?>,
                    subject_id: <?= $subject_id ?? 'null' ?>, // Assume $subject_id exists or handle null
                    quarter,
                    delete_quarter: true
                })
            });
            const data = await res.json();
            alert(data.message);
            if(data.success) block.remove();
        };
    });
}

function bindSaveButtons(){
    document.querySelectorAll('.saveQuarterBtn').forEach(btn=>{
        btn.onclick = async ()=>{
            const block = btn.closest('.quarter-block');
            const quarter = parseInt(block.dataset.quarter);
            let data=[];
            block.querySelectorAll('tbody tr').forEach(r=>{
                let student_id = parseInt(r.dataset.student);
                let ww=[], pt=[], qa=[];
                r.querySelectorAll('input.ww').forEach(i=> ww.push(parseFloat(i.value)||0));
                r.querySelectorAll('input.pt').forEach(i=> pt.push(parseFloat(i.value)||0));
                r.querySelectorAll('input.qa').forEach(i=> qa.push(parseFloat(i.value)||0));
                data.push({student_id, ww, pt, qa});
            });
            try {
                const res = await fetch('save_quarter.php',{
                    method:'POST',
                    headers:{'Content-Type':'application/json'},
                    body: JSON.stringify({
                        teacher_id: <?= $teacher_id_int ?>,
                        subject_id: <?= $subject_id ?? 'null' ?>, // Assume $subject_id exists or handle null
                        quarter,
                        data
                    })  
                });
                const json = await res.json();
                alert(json.message);
            } catch(err){
                console.error(err);
                alert("Failed to save quarter. See console.");
            }
        };
    });
}

function bindAllButtons(){
    bindDeleteButtons();
    bindSaveButtons();
}
bindAllButtons();
</script>
</body>
</html>