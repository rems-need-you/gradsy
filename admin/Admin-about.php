<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>About — LAC Student Management System</title>
  <meta name="description" content="About page for the LAC Student Management System — overview, features, team, and contact." />
    <link rel="stylesheet" href="../css/about.css" />
</head>
<body>
  <main class="container">
    <div class="card">
      <header>
        <div class="logo">LAC</div>
        <div>
          <h1>Learning Area Chair</h1>
        </div>
      </header>

      <div class="section-title">
        <span class="pill">Overview</span>
      </div>
      <p class="muted">Learning Area Chair manage students, enter grades per quarter, compute averages, and generate printable reports. Designed for simplicity and fast data entry while keeping integrity of grade calculations and school records.</p>

      <div class="section-title">
        <span class="pill">Key features</span>
      </div>
      <div class="grid">
        <div class="feature">
          <h3>Student Profiles</h3>
          <p class="muted">Store student details, grade level, section, and averages.</p>
        </div>
        <div class="feature">
          <h3>Quarterly Grade Entry</h3>
          <p class="muted">Enter weighted components (WW, PT, QA), validate inputs, and save per quarter.</p>
        </div>
        <div class="feature">
          <h3>Auto Calculations</h3>
          <p class="muted">Automatic computation of quarter and final averages with configurable weightings.</p>
        </div>
        <div class="feature">
          <h3>Reports & Print</h3>
          <p class="muted">Printable grade records.</p>
        </div>
      </div>

      <div class="section-title">
        <span class="pill">Tech & deployment</span>
      </div>
      <p class="muted">Built with PHP and MySQL on the backend, vanilla JavaScript for client interactions and a responsive HTML/CSS frontend. </p>

      <div class="section-title">
        <span class="pill">Team & contributors</span>
      </div>
      <div class="team">
        <div class="person">
          <div class="avatar">RM</div>
          <div>
            <div style="font-weight:700">Reme Gadugdug</div>
            <div class="muted">Programmer</div>
          </div>
        </div>
        <div class="person">
          <div class="avatar">Docs</div>
          <div>
            <div style="font-weight:700">Rheanna Joy Gomez</div>
            <div style="font-weight:700">Ganelyn Gomez</div>
            <div class="muted">Documentation</div>
        </div>
        </div>
        </div>
    

      <div class="section-title" style="margin-top:20px">
        <span class="pill">Contact & support</span>
      </div>
      <p class="muted">For questions, feature requests, or to report bugs, open an issue on the project repo or contact the system administrator at <strong>remegadugdug@gmail.com</strong> . Include a screenshot and steps to reproduce when reporting bugs.</p>

      <div style="display:flex;justify-content:space-between;align-items:center;margin-top:18px;gap:10px;flex-wrap:wrap">
        <div class="muted">Version: <strong>1.0.0</strong> • Last updated: <strong>2025-09-28</strong></div>    
      </div>

      <footer>
        © <span id="year"></span> INTEGRATED GRADING, EXTRACURRICULAR, AND DISCIPLINE TRACKING. All rights reserved.
      </footer>
    </div>
  </main>

  <script>
    document.getElementById('year').textContent = new Date().getFullYear();
  </script>
</body>
</html>
s