  <?php 
  include('../partials-front/constantsss.php'); 

  // --- Save Grades Logic ---
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $student_id = intval($_POST['student_id']);
      $gradesData = $_POST['grades'];

      $overallSum = 0;
      $subjectCount = 0;

      foreach ($gradesData as $subject => $quarters) {
          $q1 = floatval($quarters['q1'] ?? 0);
          $q2 = floatval($quarters['q2'] ?? 0);
          $q3 = floatval($quarters['q3'] ?? 0);
          $q4 = floatval($quarters['q4'] ?? 0);

          // Calculate subject average if all quarters have values
          if ($q1 && $q2 && $q3 && $q4) {
              $subjectAvg = ($q1 + $q2 + $q3 + $q4) / 4;
              $overallSum += $subjectAvg;
              $subjectCount++;
          }

          // Insert or update grades
          $stmt = $conn->prepare("
              INSERT INTO grades (student_id, subject, q1, q2, q3, q4)
              VALUES (?, ?, ?, ?, ?, ?)
              ON DUPLICATE KEY UPDATE q1=VALUES(q1), q2=VALUES(q2), q3=VALUES(q3), q4=VALUES(q4)
          ");
          $stmt->bind_param("isdddd", $student_id, $subject, $q1, $q2, $q3, $q4);
          $stmt->execute();
      }

      // Update overall average in students table
      $overallAverage = $subjectCount > 0 ? round($overallSum / $subjectCount, 2) : 0;
      $conn->query("UPDATE student SET average = $overallAverage WHERE id = $student_id");

      // Redirect with success flag
      header("Location: LACgrade.php?success=1");
      exit;
  }

  // --- Fetch Data ---
  $students = [];
  $result = $conn->query("SELECT id, CONCAT(surname, ', ', name, ' ', middle_name, '.') AS full_name, grade, section, year, average FROM student");


  $subjects = [
      "English",
      "Filipino",
      "Mathematics",
      "Science",
      "Araling Panlipunan (Social Studies)",
      "Edukasyon sa Pagpapakatao (EsP)",
      "Christian Living Education",
      "Music",
      "Arts",
      "Physical Education",
      "Health",
      "Edukasyong Pantahanan at Pangkabuhayan (EPP)"
  ];

  while ($s = $result->fetch_assoc()) {
      $grades = [];
      foreach ($subjects as $subj) {
          $grades[$subj] = ["q1" => "", "q2" => "", "q3" => "", "q4" => ""];
      }

      // Fetch grades from DB
      $get_grade = $conn->query("SELECT subject, q1, q2, q3, q4 FROM grades WHERE student_id = " . intval($s['id']));
      while ($g = $get_grade->fetch_assoc()) {
          $grades[$g['subject']] = [
              "q1" => $g['q1'],
              "q2" => $g['q2'],
              "q3" => $g['q3'],
              "q4" => $g['q4']
          ];
      }

     $students[] = [
    'id' => $s['id'],
    'name' => $s['full_name'], // ito na yung buo
    'grade_level' => $s['grade'],
    'section' => $s['section'],
    'year' => $s['year'],
    'average' => $s['average'],
    'grades' => $grades
    ];

  }
  ?>
  <!DOCTYPE html>
  <html lang="en">
  <head>
  <meta charset="UTF-8">
  <title>Student Quarterly Grades</title>
  <link rel="stylesheet" href="../css/grade.css">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  </head>
  <body>
  <header>
    <h1>Student Quarterly Grades</h1>
  </header>
  <div class="container">
    <?php if (isset($_GET['success'])): ?>
      <div class="success-msg">Grades saved successfully!</div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="filters">
      <input type="text" id="search" placeholder="Search student by name...">
      <select id="gradeFilter">
        <option value="">All Grades</option>
        <option value="4">Grade 4</option>
        <option value="5">Grade 5</option>
        <option value="6">Grade 6</option>
      </select>
      <select id="yearFilter">
        <option value="">All Years</option>
        <?php foreach(array_unique(array_column($students, 'year')) as $y): ?>
          <option value="<?= $y ?>"><?= $y ?></option>
        <?php endforeach; ?>
      </select>
      <select id="sectionFilter">
        <option value="">All Sections</option>
        <?php foreach(array_unique(array_column($students, 'section')) as $sec): ?>
          <option value="<?= htmlspecialchars($sec) ?>"><?= htmlspecialchars($sec) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div id="studentList">
      <?php foreach ($students as $s): ?>
        <div class="student-card" 
            data-name="<?= strtolower($s['name']) ?>"
            data-grade="<?= $s['grade_level'] ?>"
            data-year="<?= $s['year'] ?>"
            data-section="<?= strtolower($s['section']) ?>">

          <h2><?= htmlspecialchars($s['name']) ?></h2>
          <p><strong>Grade:</strong> <?= $s['grade_level'] ?> |
            <strong>Section:</strong> <?= htmlspecialchars($s['section']) ?> |
            <strong>Year:</strong> <?= $s['year'] ?> |
            <strong>Overall Average:</strong> <?= $s['average'] ?></p>

          <form method="POST" action="">
            <input type="hidden" name="student_id" value="<?= $s['id'] ?>">
            <table>
              <tr>
                <th>Subject</th>
                <th>Q1</th>
                <th>Q2</th>
                <th>Q3</th>
                <th>Q4</th>
                <th>Average</th>
                <th>Remarks</th>
              </tr>
              <?php foreach ($subjects as $subj): 
                $q1 = $s['grades'][$subj]['q1'] ?? "";
                $q2 = $s['grades'][$subj]['q2'] ?? "";
                $q3 = $s['grades'][$subj]['q3'] ?? "";
                $q4 = $s['grades'][$subj]['q4'] ?? "";
                $avg = ($q1 && $q2 && $q3 && $q4) ? round(($q1 + $q2 + $q3 + $q4) / 4, 2) : "";
                $remarks = ($avg !== "" && $avg < 75) ? "Failed" : (($avg !== "") ? "Passed" : "");
              ?>
                <tr>
                  <td><?= $subj ?></td>
                  <?php for ($i=1; $i<=4; $i++): ?>
                    <td><input type="number" class="grade-input" name="grades[<?= $subj ?>][q<?= $i ?>]" 
                              value="<?= ${"q$i"} ?>" min="0" max="100" step="0.01"></td>
                  <?php endfor; ?>
                  <td class="subj-avg"><?= $avg ?></td>
                  <td class="remarks <?= strtolower($remarks) ?>"><?= $remarks ?></td>
                </tr>
              <?php endforeach; ?>
            </table>
            <div class="overall">Overall Average: <span class="overall-avg"><?= $s['average'] ?></span></div>
            <button type="submit" class="save-btn">Save Grades</button>
            <button type="button" class="save-btn" onclick="printStudent(this)">Print Grades</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <script>
  function applyFilters() {
      let keyword = document.getElementById('search').value.toLowerCase();
      let grade = document.getElementById('gradeFilter').value;
      let year = document.getElementById('yearFilter').value;
      let section = document.getElementById('sectionFilter').value.toLowerCase();

      document.querySelectorAll('.student-card').forEach(card => {
          let matches = true;
          if (keyword && !card.dataset.name.includes(keyword)) matches = false;
          if (grade && card.dataset.grade !== grade) matches = false;
          if (year && card.dataset.year !== year) matches = false;
          if (section && card.dataset.section !== section) matches = false;
          card.style.display = matches ? '' : 'none';
      });
  }

  document.querySelectorAll('input.grade-input').forEach(input => {
      input.addEventListener('input', function() {
          let row = this.closest('tr');
          let grades = Array.from(row.querySelectorAll('.grade-input')).map(g => parseFloat(g.value) || 0);
          let filled = grades.filter(g => g > 0).length;
          let avgCell = row.querySelector('.subj-avg');
          let remarksCell = row.querySelector('.remarks');

          if (filled === 4) {
              let avg = (grades.reduce((a,b)=>a+b,0) / 4).toFixed(2);
              avgCell.textContent = avg;
              if (avg < 75) {
                  remarksCell.textContent = "Failed";
                  remarksCell.className = "remarks failed";
              } else {
                  remarksCell.textContent = "Passed";
                  remarksCell.className = "remarks passed";
              }
          } else {
              avgCell.textContent = "";
              remarksCell.textContent = "";
              remarksCell.className = "remarks";
          }

          // Overall average calculation
          let card = this.closest('.student-card');
          let allAvgs = Array.from(card.querySelectorAll('.subj-avg'))
              .map(a => parseFloat(a.textContent) || 0)
              .filter(a => a > 0);
          let overallAvg = allAvgs.length > 0 ? 
              (allAvgs.reduce((a,b)=>a+b,0) / allAvgs.length).toFixed(2) : "";
          card.querySelector('.overall-avg').textContent = overallAvg;
      });
  });

  function printStudent(btn) {
      let card = btn.closest('.student-card').cloneNode(true);
      card.querySelectorAll('input').forEach(inp => {
          inp.setAttribute('value', inp.value);
          inp.outerHTML = inp.value;
      });
      card.querySelectorAll('button').forEach(b => b.remove());

      let printWindow = window.open('', '', 'height=900,width=800');
      printWindow.document.write('<html><head><title>Student Grades</title>');
      printWindow.document.write('<style>');
      printWindow.document.write(`
          body { font-family: Arial; margin: 20px; }
          h2 { text-align: center; }
          table { width: 100%; border-collapse: collapse; margin-top: 20px; }
          th, td { border: 1px solid #333; padding: 8px; text-align: center; }
          th { background: #eee; }
          p { margin: 4px 0; }
          .overall { font-weight: bold; margin-top: 10px; }
      `);
      printWindow.document.write('</style></head><body>');
      printWindow.document.write(card.innerHTML);
      printWindow.document.write('</body></html>');
      printWindow.document.close();
      printWindow.print();
  }

  document.getElementById('search').addEventListener('input', applyFilters);
  document.getElementById('gradeFilter').addEventListener('change', applyFilters);
  document.getElementById('yearFilter').addEventListener('change', applyFilters);
  document.getElementById('sectionFilter').addEventListener('change', applyFilters);
  </script>

  </body>
  </html>
