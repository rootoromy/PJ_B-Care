// window.vitalData は sp_vitals.php が出力（times / datasets）
const { times, datasets } = window.vitalData;

const canvas = document.getElementById("vitalChart");
const ctx = canvas.getContext("2d");

function resizeCanvas() {
  const ratio = window.devicePixelRatio || 1;
  const rect = canvas.getBoundingClientRect();
  canvas.width = Math.round(rect.width * ratio);
  canvas.height = Math.round(rect.height * ratio);
  ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
  drawChart(rect.width, rect.height);
}

function normalize(value, min, max) {
  return (value - min) / (max - min);
}

function drawChart(width, height) {
  ctx.clearRect(0, 0, width, height);

  const pad = { left: 20, right: 28, top: 24, bottom: 42 };
  const plotW = width - pad.left - pad.right;
  const plotH = height - pad.top - pad.bottom;

  ctx.font = '11px -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
  ctx.textAlign = "center";
  ctx.textBaseline = "middle";

  // horizontal grid
  for (let i = 0; i <= 5; i++) {
    const y = pad.top + (plotH / 5) * i;
    ctx.beginPath();
    ctx.strokeStyle = i === 5 ? "#cbd5cd" : "#e7ece8";
    ctx.lineWidth = 1;
    ctx.moveTo(pad.left, y);
    ctx.lineTo(width - pad.right, y);
    ctx.stroke();
  }

  // vertical grid and time labels
  times.forEach((t, index) => {
    const x = pad.left + (plotW / (times.length - 1)) * index;
    ctx.beginPath();
    ctx.strokeStyle = "#e7ece8";
    ctx.moveTo(x, pad.top);
    ctx.lineTo(x, pad.top + plotH);
    ctx.stroke();

    ctx.fillStyle = "#566159";
    ctx.fillText(t, x, height - 17);
  });

  datasets.forEach(ds => {
    // 欠測（null）を除いた点だけを線で結ぶ
    const points = ds.values
      .map((value, index) => (value === null ? null : {
        x: pad.left + (plotW / (times.length - 1)) * index,
        y: pad.top + plotH - normalize(value, ds.min, ds.max) * plotH
      }))
      .filter(p => p !== null);

    if (points.length === 0) return;

    ctx.beginPath();
    ctx.strokeStyle = ds.color;
    ctx.lineWidth = 2.5;
    ctx.lineJoin = "round";
    ctx.lineCap = "round";
    points.forEach((p, i) => i === 0 ? ctx.moveTo(p.x, p.y) : ctx.lineTo(p.x, p.y));
    ctx.stroke();

    points.forEach(p => {
      ctx.beginPath();
      ctx.fillStyle = "#fff";
      ctx.strokeStyle = ds.color;
      ctx.lineWidth = 2;
      ctx.arc(p.x, p.y, 4, 0, Math.PI * 2);
      ctx.fill();
      ctx.stroke();
    });
  });
}

// ドロワー（ハンバーガーメニュー）の開閉は js/sp_drawer.js が担当

resizeCanvas();
window.addEventListener("resize", resizeCanvas);
