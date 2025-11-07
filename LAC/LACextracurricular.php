<?php
include('../partials-front/constantsss.php');
if (session_status() === PHP_SESSION_NONE) session_start();

// Optional: role-based restriction
if (!isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../css/ex.css">
  <!-- ✅ Add Tom Select (for searchable dropdowns) -->
  <link href="https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/tom-select/dist/js/tom-select.complete.min.js"></script>
  <title>Extracurricular Management</title>
</head>
<body>
  <h1>Extracurricular Management</h1>

  <div class="grid-container">
    <!-- ✅ LEFT PANEL: FORMS -->
    <div class="left-panel">
      <h2>Add Activity</h2>
      <form id="activityForm">
        <label>Activity Title:</label>
        <input type="text" name="title" placeholder="e.g. Science Quiz Bee" required />

        <label>Category:</label>
        <select name="category" required>
          <option value="">-- Select Category --</option>
          <option value="General">General</option>
          <option value="Mapeh">Mapeh</option>
        </select>

        <button type="submit">Add Activity</button>
      </form>

      <h2>Record Participation</h2>
      <form id="participationForm">
        <label>Student:</label>
        <!-- ✅ Searchable dropdown -->
        <select name="student_id" id="studentSelect" required></select>

        <label>Activity:</label>
        <select name="activity_id" required></select>

        <label>Level:</label>
        <select name="level" required>
          <option value="">-- Select Level --</option>
          <option>International</option>
          <option>National</option>
          <option>Regional</option>
          <option>Sectoral</option>
          <option>Division</option>
          <option>In-School</option>
        </select>

        <label>Rank:</label>
        <select name="rank_position" required>
          <option value="">-- Select Rank --</option>
          <option>1st</option>
          <option>2nd</option>
          <option>3rd</option>
          <option>Participation</option>
        </select>

        <label>Auto Percent:</label>
        <input type="text" name="percent_display" readonly />

        <label>Date Participated:</label>
        <input type="date" name="date_participated" value="<?=date('Y-m-d')?>" />

        <input name="remarks" placeholder="Remarks (optional)" />

        <button type="submit">Save Participation</button>
      </form>
    </div>

    <!-- ✅ RIGHT PANEL: LEADERBOARD -->
    <div class="right-panel">
      <h2>Leaderboard</h2>
      <div id="leaderboard-container">
        <table id="leaderboard">
          <thead>
            <tr>
              <th>Name</th><th>Grade</th><th>Section</th><th>Total Points</th>
            </tr>
          </thead>
          <tbody></tbody>
          <tfoot>
            <tr>
              <td colspan="4">
                <div class="pagination" id="leaderboard-pagination">
                  <button id="lb-prev">&lt;</button>
                  <span id="lb-page-info">Page 1</span>
                  <button id="lb-next">&gt;</button>
                </div>
              </td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>

  <!-- ✅ RECENT PARTICIPATIONS BELOW -->
  <div class="recent-section">
    <h2>Recent Participations</h2>
    <div id="recent-container">
      <table id="recent">
        <thead>
          <tr>
            <th>Student</th><th>Activity</th><th>Category</th><th>Level</th>
            <th>Rank</th><th>Percent</th><th>Date</th><th>Remarks</th>
          </tr>
        </thead>
        <tbody></tbody>
        <tfoot>
          <tr>
            <td colspan="8">
              <div class="pagination" id="recent-pagination">
                <button id="rec-prev">&lt;</button>
                <span id="rec-page-info">Page 1</span>
                <button id="rec-next">&gt;</button>
              </div>
            </td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</body>

<script>
const percentTable = {
  general: {
    International: { "1st":0.50,"2nd":0.49,"3rd":0.48,"Participation":0.47 },
    National:      { "1st":0.46,"2nd":0.45,"3rd":0.44,"Participation":0.43 },
    Regional:      { "1st":0.42,"2nd":0.41,"3rd":0.40,"Participation":0.39 },
    Sectoral:      { "1st":0.38,"2nd":0.37,"3rd":0.36,"Participation":0.35 },
    Division:      { "1st":0.34,"2nd":0.33,"3rd":0.32,"Participation":0.31 },
    "In-School":   { "1st":0.30,"2nd":0.29,"3rd":0.28,"Participation":0.27 },
  },
  mapeh: {
    International: { "1st":0.25,"2nd":0.24,"3rd":0.23,"Participation":0.22 },
    National:      { "1st":0.21,"2nd":0.20,"3rd":0.19,"Participation":0.18 },
    Regional:      { "1st":0.17,"2nd":0.16,"3rd":0.15,"Participation":0.14 },
    Sectoral:      { "1st":0.13,"2nd":0.12,"3rd":0.11,"Participation":0.10 },
    Division:      { "1st":0.09,"2nd":0.08,"3rd":0.07,"Participation":0.06 },
    "In-School":   { "1st":0.05,"2nd":0.04,"3rd":0.03,"Participation":0.02 },
  }
};

async function api(action, data={}) {
  const fd = new FormData();
  fd.append('action', action);
  for (const k in data) fd.append(k, data[k]);
  const res = await fetch('api.php', { method:'POST', body: fd });
  return await res.json();
}

// ✅ Add Activity
const activityForm = document.getElementById('activityForm');
activityForm.onsubmit = async e => {
  e.preventDefault();
  const data = Object.fromEntries(new FormData(e.target));
  const r = await api('add_activity', data);
  if (r.ok) {
    alert('Activity added!');
    activityForm.reset();
    loadFormData(); // refresh dropdown list
  } else {
    alert('Error: ' + r.error);
  }
};

async function loadFormData() {
  const r = await api('get_form_data');
  if (!r.ok) return alert(r.error);

  // ✅ Load students (Tom Select)
  const studentSelect = document.getElementById('studentSelect');
  studentSelect.innerHTML = r.students.map(s => 
    `<option value="${s.id}" data-school-year="${s.year}">${s.full_name} (S.Y. ${s.year})</option>`
  ).join('');

  // ✅ Initialize Tom Select (searchable dropdown)
  if (studentSelect.tomselect) {
    studentSelect.tomselect.destroy(); // avoid duplicate init
  }
  new TomSelect('#studentSelect', {
    placeholder: 'Search student...',
    allowEmptyOption: true
  });

  // ✅ Load activities
  document.querySelector('[name=activity_id]').innerHTML =
    r.activities.map(a => `<option value="${a.id}" data-cat="${a.category}">${a.title} (${a.category})</option>`).join('');
}

// === Leaderboard and Recent Rendering (same as before) ===
let lbPage = 1, recPage = 1;
const perPage = 20;
let leaderboardData = [], recentData = [];

function renderLeaderboard() {
  const start = (lbPage - 1) * perPage;
  const pageData = leaderboardData.slice(start, start + perPage);
  document.querySelector('#leaderboard tbody').innerHTML = pageData.map(b =>
    `<tr><td>${b.name}</td><td>${b.grade}</td><td>${b.section}</td><td>${b.total_points}</td></tr>`
  ).join('');
  document.getElementById('lb-page-info').textContent = `Page ${lbPage} of ${Math.ceil(leaderboardData.length / perPage)}`;
  document.getElementById('lb-prev').disabled = lbPage === 1;
  document.getElementById('lb-next').disabled = start + perPage >= leaderboardData.length;
}

function renderRecent() {
  const start = (recPage - 1) * perPage;
  const pageData = recentData.slice(start, start + perPage);
  document.querySelector('#recent tbody').innerHTML = pageData.map(p =>
    `<tr>
      <td>${p.student_name}</td>
      <td>${p.activity_title}</td>
      <td>${p.category}</td>
      <td>${p.level}</td>
      <td>${p.rank_position}</td>
      <td>${p.percent}</td>
      <td>${p.date_participated}</td>
      <td>${p.remarks ?? ''}</td>
    </tr>`
  ).join('');
  document.getElementById('rec-page-info').textContent = `Page ${recPage} of ${Math.ceil(recentData.length / perPage)}`;
  document.getElementById('rec-prev').disabled = recPage === 1;
  document.getElementById('rec-next').disabled = start + perPage >= recentData.length;
}

async function loadDashboard() {
  const r = await api('get_dashboard');
  if (!r.ok) return alert(r.error);
  leaderboardData = r.data.leaderboard;
  recentData = r.data.recent;
  lbPage = 1;
  recPage = 1;
  renderLeaderboard();
  renderRecent();
}

document.addEventListener('click', e => {
  if (e.target.id === 'lb-prev' && lbPage > 1) { lbPage--; renderLeaderboard(); }
  if (e.target.id === 'lb-next' && lbPage * perPage < leaderboardData.length) { lbPage++; renderLeaderboard(); }
  if (e.target.id === 'rec-prev' && recPage > 1) { recPage--; renderRecent(); }
  if (e.target.id === 'rec-next' && recPage * perPage < recentData.length) { recPage++; renderRecent(); }
});

const form = document.getElementById('participationForm');
form.addEventListener('change', e => {
  // Update percent display
  const activitySel = form.querySelector('[name=activity_id]');
  const level = form.querySelector('[name=level]').value;
  const rank = form.querySelector('[name=rank_position]').value;
  const cat = activitySel.selectedOptions[0]?.dataset.cat?.toLowerCase() || '';
  if (cat && level && rank && percentTable[cat]?.[level]?.[rank]) {
    form.querySelector('[name=percent_display]').value = percentTable[cat][level][rank];
  } else {
    form.querySelector('[name=percent_display]').value = '';
  }

  // Set date constraints when student is selected
  if (e.target.name === 'student_id') {
    const dateInput = form.querySelector('[name=date_participated]');
    const schoolYear = e.target.selectedOptions[0]?.dataset.schoolYear;
    if (schoolYear) {
      const [startYear, endYear] = schoolYear.split('-');
      const minDate = `${startYear}-06-01`;  // June 1st of start year
      const maxDate = `${endYear}-03-31`;    // March 31st of end year
      dateInput.min = minDate;
      dateInput.max = maxDate;
      dateInput.title = `Valid dates: ${minDate} to ${maxDate} (S.Y. ${schoolYear})`;
    } else {
      dateInput.removeAttribute('min');
      dateInput.removeAttribute('max');
      dateInput.removeAttribute('title');
    }
  }
});

form.onsubmit = async e => {
  e.preventDefault();
  const data = Object.fromEntries(new FormData(e.target));
  const r = await api('record_participation', data);
  if (r.ok) {
    alert('Participation recorded!');
    form.reset();
    loadDashboard();
  } else {
    alert('Error: ' + r.error);
  }
};

// Initial load
loadFormData();
loadDashboard();
</script>
</body>
</html>
