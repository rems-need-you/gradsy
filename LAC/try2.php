<?php
include('../partials-front/constantsss.php');
if (session_status() === PHP_SESSION_NONE) session_start();

// ======= GET GRADE, SECTION, YEAR DYNAMICALLY =======
$header_sql = "SELECT grade, section, year FROM student LIMIT 1";
$header_result = $conn->query($header_sql);

$header_grade = $header_section = $header_year = '';
if($header_result && $header_result->num_rows > 0){
    $header_row = $header_result->fetch_assoc();
    $header_grade = $header_row['grade'];
    $header_section = $header_row['section'];
    $header_year = $header_row['year'];
}

// ======= FETCH STUDENTS =======
$stmt = $conn->prepare("
    SELECT s.id AS student_id, s.name AS student_name,
           s.grade AS grade_level, s.section, s.year,
           s2.ww1, s2.ww2, s2.ww3,
           s2.pt1, s2.pt2, s2.pt3,
           s2.qa1, s2.qa2
    FROM student s
    LEFT JOIN student2 s2 ON s.name = s2.student_name
    WHERE s.grade = ? AND s.section = ? AND s.year = ?
    ORDER BY s.name ASC
");
$stmt->bind_param("sss", $header_grade, $header_section, $header_year);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $row['ww1'] = $row['ww1'] ?? 0;
        $row['ww2'] = $row['ww2'] ?? 0;
        $row['ww3'] = $row['ww3'] ?? 0;
        $row['pt1'] = $row['pt1'] ?? 0;
        $row['pt2'] = $row['pt2'] ?? 0;
        $row['pt3'] = $row['pt3'] ?? 0;
        $row['qa1'] = $row['qa1'] ?? 0;
        $row['qa2'] = $row['qa2'] ?? 0;
        $rows[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Grade Sheet</title>
<link rel="stylesheet" href="../css/gs.css">
</head>
<body>
<a href="LACindex.php" class="back-link">← Back to Dashboard</a>
<div class="container">
  <h1>Grade Sheet</h1>
  <?php if($header_grade !== ''): ?>
    <h3>Grade: <?= $header_grade ?> | Section: <?= $header_section ?> | Year: <?= $header_year ?></h3>
  <?php endif; ?>

  <div id="quarters-container">
    <!-- Quarter 1 -->
    <div class="quarter-block" data-quarter="1">
      <h2>1st Quarter</h2>
      <div class="controls">
        <button class="addQuizBtn">+ Add Quiz</button>
        <button class="addTaskBtn">+ Add Task</button>
        <button class="deleteQuarterBtn">🗑 Delete Quarter</button>
        <button class="saveQuarterBtn">💾 Save Quarter</button>
      </div>
      <table class="gradeTable">
        <thead>
          <tr>
            <th>Student Name</th>
            <th colspan="5">Written Works (20%)</th>
            <th>Total</th><th>PS</th><th>WS</th>
            <th colspan="3">Performance Tasks (60%)</th>
            <th>Total</th><th>PS</th><th>WS</th>
            <th>Quarterly Exam</th><th>PS</th><th>WS</th>
            <th>Initial Grade</th><th>Quarterly Grade</th>
          </tr>
          <tr>
            <th></th>
            <th>Quiz 1</th><th>Quiz 2</th><th>Quiz 3</th><th>Quiz 4</th><th>Quiz 5</th>
            <th>Total</th><th>(20%)</th><th>(20%)</th>
            <th>Task 1</th><th>Task 2</th><th>Task 3</th>
            <th>Total</th><th>(60%)</th><th>(60%)</th>
            <th>Exam</th><th>(20%)</th><th>(20%)</th>
            <th></th><th></th>
          </tr>
          <tr class="max-row">
            <th>Possible Score</th>
            <th><input type="number" value="10" class="max-ww"></th>
            <th><input type="number" value="10" class="max-ww"></th>
            <th><input type="number" value="10" class="max-ww"></th>
            <th><input type="number" value="10" class="max-ww"></th>
            <th><input type="number" value="10" class="max-ww"></th>
            <th>(30)</th><th></th><th></th>
            <th><input type="number" value="20" class="max-pt"></th>
            <th><input type="number" value="20" class="max-pt"></th>
            <th><input type="number" value="20" class="max-pt"></th>
            <th>(60)</th><th></th><th></th>
            <th><input type="number" value="20" class="max-qa"></th>
            <th></th><th></th>
            <th></th><th></th>
          </tr>
        </thead>
        <tbody>
        <?php if(!empty($rows)): ?>
          <?php foreach($rows as $row): ?>
            <tr data-student="<?= $row['student_id'] ?>">
              <td><?= $row['student_name'] ?></td>
              <td><input type="number" class="ww" value="<?= $row['ww1'] ?>"></td>
              <td><input type="number" class="ww" value="<?= $row['ww2'] ?>"></td>
              <td><input type="number" class="ww" value="<?= $row['ww3'] ?>"></td>
              <td><input type="number" class="ww" value="0"></td>
              <td><input type="number" class="ww" value="0"></td>
              <td class="ww_total"></td><td class="ww_ps"></td><td class="ww_ws"></td>
              <td><input type="number" class="pt" value="<?= $row['pt1'] ?>"></td>
              <td><input type="number" class="pt" value="<?= $row['pt2'] ?>"></td>
              <td><input type="number" class="pt" value="<?= $row['pt3'] ?>"></td>
              <td class="pt_total"></td><td class="pt_ps"></td><td class="pt_ws"></td>
              <td><input type="number" class="qa" value="<?= $row['qa1'] ?>"></td>
              <td class="qa_ps"></td><td class="qa_ws"></td>
              <td class="initial"></td>
              <td class="quarterly"></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="20">No students found</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <button id="addQuarterBtn">+ Add Quarter</button>
</div>

<script>
let quarterCount = 1;

// ===== UPDATE ROW =====
function updateRow(row){
    let wwTotal=0, ptTotal=0, qaTotal=0;
    let wwMax=0, ptMax=0, qaMax=0;

    row.closest('table').querySelectorAll('.max-ww').forEach(i=> wwMax += parseFloat(i.value)||0 );
    row.closest('table').querySelectorAll('.max-pt').forEach(i=> ptMax += parseFloat(i.value)||0 );
    row.closest('table').querySelectorAll('.max-qa').forEach(i=> qaMax += parseFloat(i.value)||0 );

    row.querySelectorAll('input.ww').forEach(i=> wwTotal += parseFloat(i.value)||0 );
    row.querySelectorAll('input.pt').forEach(i=> ptTotal += parseFloat(i.value)||0 );
    row.querySelectorAll('input.qa').forEach(i=> qaTotal += parseFloat(i.value)||0 );

    let wwPercent = (wwMax>0)? (wwTotal/wwMax)*20 : 0;
    let ptPercent = (ptMax>0)? (ptTotal/ptMax)*60 : 0;
    let qaPercent = (qaMax>0)? (qaTotal/qaMax)*20 : 0;

    row.querySelector('.ww_total').textContent = wwTotal;
    row.querySelector('.pt_total').textContent = ptTotal;
    row.querySelector('.ww_ps').textContent = wwPercent.toFixed(2);
    row.querySelector('.ww_ws').textContent = wwPercent.toFixed(2);
    row.querySelector('.pt_ps').textContent = ptPercent.toFixed(2);
    row.querySelector('.pt_ws').textContent = ptPercent.toFixed(2);
    row.querySelector('.qa_ps').textContent = qaPercent.toFixed(2);
    row.querySelector('.qa_ws').textContent = qaPercent.toFixed(2);

    const initial = wwPercent + ptPercent + qaPercent;
    row.querySelector('.initial').textContent = initial.toFixed(2);
    row.querySelector('.quarterly').textContent = Math.round(initial);
}

// ===== ADD/DELETE COLUMNS =====
function addColumn(type, table){
    const header2 = table.querySelectorAll('thead tr')[1];
    const maxRow = table.querySelector('.max-row');
    const tbody = table.querySelector('tbody');
    let insertIndex, colText;

    if(type === 'ww'){ insertIndex = 6; colText = "Quiz " + (header2.querySelectorAll('input.max-ww').length + 1); }
    else if(type === 'pt'){ insertIndex = 12; colText = "Task " + (header2.querySelectorAll('input.max-pt').length + 1); }

    const newTh = document.createElement('th');
    newTh.innerHTML = `${colText} <button class="delColBtn">x</button>`;
    header2.insertBefore(newTh, header2.children[insertIndex]);

    const newInputTh = document.createElement('th');
    const input = document.createElement('input');
    input.type = 'number';
    input.value = (type==='ww')?10:20;
    input.className = (type==='ww')?'max-ww':'max-pt';
    newInputTh.appendChild(input);
    maxRow.insertBefore(newInputTh, maxRow.children[insertIndex]);

    tbody.querySelectorAll('tr').forEach(row=>{
        const newTd = document.createElement('td');
        const inp = document.createElement('input');
        inp.type='number';
        inp.value=0;
        inp.className=type;
        newTd.appendChild(inp);
        row.insertBefore(newTd, row.children[insertIndex]);
        inp.addEventListener('input', ()=>updateRow(row));
    });
    bindDeleteColumnButtons();
}

function bindDeleteColumnButtons(){
    document.querySelectorAll('.delColBtn').forEach(btn=>{
        btn.onclick = ()=>{
            const th = btn.closest('th');
            const index = th.cellIndex;
            const table = th.closest('table');
            th.remove();
            table.querySelector('.max-row').children[index].remove();
            table.querySelectorAll('tbody tr').forEach(r=> r.children[index].remove());
        };
    });
}

// ===== DELETE QUARTER =====
function bindDeleteButtons(){
    document.querySelectorAll('.deleteQuarterBtn').forEach(btn=>{
        btn.onclick = ()=>{
            const block = btn.closest('.quarter-block');
            if(block.dataset.quarter==="1"){ alert("❌ First Quarter cannot be deleted."); return; }
            block.remove();
        };
    });
}

// ===== SAVE QUARTER =====
function bindSaveButtons(){
    document.querySelectorAll('.saveQuarterBtn').forEach(btn=>{
        btn.onclick = ()=>{
            const block = btn.closest('.quarter-block');
            const quarter = block.dataset.quarter;
            let data=[]; let hasValue=false;

            block.querySelectorAll('tbody tr').forEach(r=>{
                let student_id = r.dataset.student;
                let ww=[], pt=[], qa=[];
                r.querySelectorAll('input.ww').forEach(i=>{ ww.push(i.value); if(parseFloat(i.value)>0) hasValue=true; });
                r.querySelectorAll('input.pt').forEach(i=>{ pt.push(i.value); if(parseFloat(i.value)>0) hasValue=true; });
                r.querySelectorAll('input.qa').forEach(i=>{ qa.push(i.value); if(parseFloat(i.value)>0) hasValue=true; });
                data.push({student_id, ww, pt, qa});
            });

            if(!hasValue){ alert("❌ Cannot save. Enter at least one score."); return; }

            fetch('save_quarter.php',{
                method:'POST',
                headers:{'Content-Type':'application/json'},
                body: JSON.stringify({quarter, data})
            }).then(res=>res.text()).then(resp=>alert("✅ Saved!\n"+resp));
        };
    });
}

// ===== ADD QUARTER =====
document.getElementById('addQuarterBtn').addEventListener('click', ()=>{
    quarterCount++;
    const container = document.getElementById('quarters-container');
    const first = document.querySelector('.quarter-block');
    const newQuarter = first.cloneNode(true);
    newQuarter.dataset.quarter = quarterCount;
    newQuarter.querySelector('h2').textContent = quarterCount + getQuarterSuffix(quarterCount) + " Quarter";
    newQuarter.querySelectorAll('input[type=number]').forEach(inp=>inp.value=0);
    newQuarter.querySelectorAll('tbody tr input').forEach(input=>input.addEventListener('input',()=>updateRow(input.closest('tr'))));
    newQuarter.querySelector('.addQuizBtn').onclick=()=>addColumn('ww', newQuarter.querySelector('table'));
    newQuarter.querySelector('.addTaskBtn').onclick=()=>addColumn('pt', newQuarter.querySelector('table'));
    container.appendChild(newQuarter);
    bindDeleteButtons();
    bindDeleteColumnButtons();
    bindSaveButtons();
});

function getQuarterSuffix(num){ if(num===1) return "st"; if(num===2) return "nd"; if(num===3) return "rd"; return "th"; }

// ===== INITIAL CALC =====
document.querySelectorAll('tbody tr').forEach(r=>updateRow(r));
document.querySelector('.quarter-block .addQuizBtn').onclick=()=>addColumn('ww', document.querySelector('.quarter-block table'));
document.querySelector('.quarter-block .addTaskBtn').onclick=()=>addColumn('pt', document.querySelector('.quarter-block table'));
bindDeleteButtons();
bindDeleteColumnButtons();
bindSaveButtons();
</script>

</body>
</html>
<?php $conn->close(); ?>
