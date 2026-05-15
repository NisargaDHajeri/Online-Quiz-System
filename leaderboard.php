<?php
// leaderboard.php
// Shows top users, medals, and allows clicking a user to view their recent attempts graph.

// adjust path if your structure differs
include 'php/database.php';
?>

<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Leaderboard — QuizHub</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<style>
/* Small self-contained CSS for leaderboard (you can move to style.css) */
.leader-card { background:#0f0f0f; border:1px solid #222; color:#fff; border-radius:10px; padding:18px; }
.leader-row { display:flex; align-items:center; gap:12px; padding:12px; border-radius:8px; transition:all .18s; cursor:pointer; }
.leader-row:hover { transform:translateY(-4px); box-shadow:0 8px 20px rgba(255,204,0,0.06); }
.rank-badge { width:56px; height:56px; display:flex; align-items:center; justify-content:center; border-radius:8px; font-weight:bold; }
.medal-gold { background: linear-gradient(135deg,#ffd54a,#ffcc00); color:#111; box-shadow:0 6px 18px rgba(255,204,0,0.12); }
.medal-silver { background: linear-gradient(135deg,#dfe6ee,#bfc9d8); color:#111; box-shadow:0 6px 18px rgba(160,160,160,0.08); }
.medal-bronze { background: linear-gradient(135deg,#f0d8c0,#d7a87a); color:#111; box-shadow:0 6px 18px rgba(180,120,60,0.06); }

.leader-name { font-size:1.05rem; font-weight:600; color:#fff; }
.leader-meta { color:#cfcfcf; font-size:.9rem; }

.badge-percent { margin-left:auto; background:#111; padding:8px 12px; border-radius:8px; border:1px solid #333; color:#ffcc00; font-weight:700; }

.glow-top { box-shadow:0 8px 30px rgba(255,204,0,0.12); transform:translateY(-3px); }

.small-muted { color:#bdbdbd; }

/* Review/performance modal styles */
#perfModal .modal-content { background:#0f0f0f; color:#fff; border-radius:12px; border:1px solid #222; }
#perfChart { width:100%; height:320px; }
</style>
</head>
<body style="background:#070707;color:#fff;">

<nav class="navbar px-3 mb-4">
  <a class="navbar-brand" href="index.php" style="color:#ffcc00;font-weight:bold">QuizHub</a>
</nav>

<div class="container py-3">
  <div class="row">
    <div class="col-md-7">
      <div class="leader-card">
        <h3 style="color:#ffcc00">Leaderboard</h3>
        <p class="small-muted">Top performers (by best percentage). Click a user to view recent attempts and performance graph.</p>
        <div id="leadersList" class="mt-3">
          <!-- PHP will render rows here -->
          <?php
          // Query top performers: best percentage per user, attempts count, most recent time
          $sql = "SELECT user_name, MAX(percentage) AS best_pct, COUNT(*) AS attempts, MAX(taken_at) AS last_taken
                  FROM scores
                  WHERE user_name IS NOT NULL AND user_name != ''
                  GROUP BY user_name
                  ORDER BY best_pct DESC, last_taken DESC
                  LIMIT 30";
          $res = $conn->query($sql);
          $rank = 0;
          if($res){
              while($row = $res->fetch_assoc()){
                  $rank++;
                  $name = htmlspecialchars($row['user_name']);
                  $best = round(floatval($row['best_pct']),2);
                  $attempts = intval($row['attempts']);
                  $last = htmlspecialchars($row['last_taken']);
                  // medal class for top 3
                  $medalClass = $rank===1 ? 'medal-gold' : ($rank===2 ? 'medal-silver' : ($rank===3 ? 'medal-bronze' : ''));
                  $rankBadge = "<div class='rank-badge $medalClass'>";

                  if($rank===1) $rankBadge .= "🥇";
                  elseif($rank===2) $rankBadge .= "🥈";
                  elseif($rank===3) $rankBadge .= "🥉";
                  else $rankBadge .= $rank;

                  $rankBadge .= "</div>";
                  echo "<div class='leader-row mb-2' data-user=\"".htmlspecialchars($row['user_name'])."\">";
                  echo $rankBadge;
                  echo "<div>";
                  echo "<div class='leader-name'>".$name."</div>";
                  echo "<div class='leader-meta'>Best: <strong style='color:#ffcc00;'>{$best}%</strong> • Attempts: {$attempts} • Last: {$last}</div>";
                  echo "</div>";
                  echo "<div class='badge-percent'>{$best}%</div>";
                  echo "</div>";
              }
          } else {
              echo "<div class='text-muted'>No leaderboard data yet.</div>";
          }
          ?>
        </div>
      </div>
    </div>

    <div class="col-md-5">
      <div class="leader-card">
        <h5 style="color:#ffcc00">Selected Player — Performance</h5>
        <p class="small-muted" id="selectedInfo">Click a player on the left to view their recent attempts.</p>

        <div style="position:relative;">
          <canvas id="perfChart"></canvas>
        </div>

        <div id="perfDetails" class="mt-3 small-muted"></div>
      </div>
    </div>
  </div>
</div>

<!-- Modal fallback for mobile -->
<div class="modal fade" id="perfModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content p-3">
      <div class="modal-header border-0">
        <h5 class="modal-title" id="perfModalLabel" style="color:#ffcc00">Performance</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <canvas id="modalPerfChart" style="width:100%;height:320px;"></canvas>
        <div id="modalPerfInfo" class="mt-3 small-muted"></div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
/* leaderboard client JS (inline for simplicity) */
const leadersList = document.getElementById('leadersList');
const perfChartCtx = document.getElementById('perfChart').getContext('2d');
let perfChart = null;

function fetchAttempts(user) {
    return fetch('api/get_user_attempts.php?user=' + encodeURIComponent(user), {cache:'no-store'})
        .then(r => r.json());
}

function renderPerfChart(labels, data, user) {
    if(perfChart) perfChart.destroy();
    perfChart = new Chart(perfChartCtx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: user + ' - %',
                data: data,
                fill: true,
                tension: 0.25,
                borderColor: '#ffcc00',
                backgroundColor: 'rgba(255,204,0,0.08)',
                pointBackgroundColor: '#ffcc00',
                pointRadius: 4,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero:true, max:100, ticks:{color:'#ddd'} },
                x: { ticks:{color:'#ddd'} }
            },
            plugins: { legend:{ display:false } }
        }
    });
}

// attach click handlers to leader rows
document.querySelectorAll('#leadersList .leader-row').forEach(el => {
    el.addEventListener('click', async () => {
        const user = el.dataset.user;
        // highlight
        document.querySelectorAll('#leadersList .leader-row').forEach(r=>r.classList.remove('glow-top'));
        el.classList.add('glow-top');

        document.getElementById('selectedInfo').textContent = 'Loading ' + user + ' attempts...';

        try {
            const json = await fetchAttempts(user);
            if(!json.ok) {
                document.getElementById('selectedInfo').textContent = 'No attempts found for ' + user;
                return;
            }
            const rows = json.attempts || [];
            if(rows.length === 0) {
                document.getElementById('selectedInfo').textContent = 'No attempts found for ' + user;
                return;
            }

            const labels = rows.map(r => r.taken_at.replace(' ', '\n'));
            const data = rows.map(r => parseFloat(r.percentage));
            renderPerfChart(labels, data, user);

            // details summary
            const best = Math.max(...data).toFixed(2);
            const avg = (data.reduce((a,b)=>a+b,0)/data.length).toFixed(2);
            document.getElementById('perfDetails').innerHTML = `<strong>${user}</strong> • Attempts: ${data.length} • Best: ${best}% • Avg: ${avg}%`;
        } catch (e) {
            console.error(e);
            document.getElementById('selectedInfo').textContent = 'Failed to load attempts';
        }
    });
});

</script>
</body>
</html>
