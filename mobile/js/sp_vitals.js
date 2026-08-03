// B-Care Mobile - バイタル画面
// 配置先: mobile/js/sp_vitals.js
// window.vitalData は sp_vitals.php が出力（labels / temperature / systolic / diastolic / pulse / spo2）

const vitalData = window.vitalData;

const seriesConfig = {
  temperature: { color: "#ed650e", min: 35, max: 40, step: 0.5, dash: "" },
  systolic:    { color: "#2968ca", min: 40, max: 200, step: 20, dash: "" },
  diastolic:   { color: "#2968ca", min: 40, max: 200, step: 20, dash: "8 7" },
  pulse:       { color: "#0d7a27", min: 40, max: 130, step: 10, dash: "" },
  spo2:        { color: "#d71920", min: 84, max: 100, step: 2, dash: "" },
  respiratory: { color: "#7c3aed", min: 8, max: 36, step: 4, dash: "" }
};

const svg = document.getElementById("vitalChart");
const tableBody = document.getElementById("vitalTableBody");
const tabs = [...document.querySelectorAll(".metric-tab")];

const hiddenSeries = new Set();

function createSvgElement(name, attrs = {}, text = "") {
  const element = document.createElementNS("http://www.w3.org/2000/svg", name);
  Object.entries(attrs).forEach(([key, value]) => element.setAttribute(key, value));
  if (text !== "") element.textContent = text;
  return element;
}

function scale(value, min, max, top, bottom) {
  return bottom - ((value - min) / (max - min)) * (bottom - top);
}

function drawChart() {
  const width = 1490;
  const height = 510;
  const plot = { left: 78, right: 1115, top: 58, bottom: 420 };

  svg.setAttribute("viewBox", `0 0 ${width} ${height}`);
  svg.replaceChildren();

  const styles = getComputedStyle(document.documentElement);
  const gridColor = styles.getPropertyValue("--line-soft").trim() || "#edf1ef";
  const fontFamily = "-apple-system,BlinkMacSystemFont,Segoe UI,Yu Gothic,Meiryo,sans-serif";

  const bg = createSvgElement("rect", {
    x: plot.left, y: plot.top,
    width: plot.right - plot.left, height: plot.bottom - plot.top,
    fill: "#fff"
  });
  svg.appendChild(bg);

  // 細かな横グリッド
  for (let i = 0; i <= 20; i++) {
    const y = plot.top + ((plot.bottom - plot.top) / 20) * i;
    svg.appendChild(createSvgElement("line", {
      x1: plot.left, y1: y, x2: plot.right, y2: y,
      stroke: i % 2 === 0 ? "#dfe6e2" : gridColor,
      "stroke-width": i % 2 === 0 ? 1 : 0.7
    }));
  }

  // 時刻ごとの縦グリッド
  vitalData.labels.forEach((label, index) => {
    const x = plot.left + ((plot.right - plot.left) / (vitalData.labels.length - 1)) * index;
    svg.appendChild(createSvgElement("line", {
      x1: x, y1: plot.top, x2: x, y2: plot.bottom,
      stroke: index % 2 === 0 ? "#dfe6e2" : gridColor,
      "stroke-width": 1
    }));

    svg.appendChild(createSvgElement("text", {
      x, y: plot.bottom + 27,
      fill: "#4d5751", "font-size": "13", "text-anchor": "middle", "font-family": fontFamily
    }, label));
  });

  drawAxis("temperature", plot.left, "end", -13);
  drawAxis("systolic", plot.right + 8, "start", 14);
  drawAxis("pulse", plot.right + 92, "start", 14);
  drawAxis("spo2", plot.right + 183, "start", 14);
  drawAxis("respiratory", plot.right + 274, "start", 14);

  const unitLabels = [
    { x: plot.left - 40, y: plot.bottom + 47, text: "(℃)", color: seriesConfig.temperature.color },
    { x: plot.right + 14, y: plot.bottom + 47, text: "(mmHg)", color: seriesConfig.systolic.color },
    { x: plot.right + 102, y: plot.bottom + 47, text: "(回/分)", color: seriesConfig.pulse.color },
    { x: plot.right + 196, y: plot.bottom + 47, text: "(%)", color: seriesConfig.spo2.color },
    { x: plot.right + 287, y: plot.bottom + 47, text: "(回/分)", color: seriesConfig.respiratory.color }
  ];

  unitLabels.forEach(item => {
    svg.appendChild(createSvgElement("text", {
      x: item.x, y: item.y, fill: item.color, "font-size": "13", "font-weight": "700", "font-family": fontFamily
    }, item.text));
  });

  ["temperature", "systolic", "diastolic", "pulse", "spo2", "respiratory"].forEach(seriesName => {
    if (!hiddenSeries.has(seriesName)) {
      drawSeries(seriesName, plot);
    }
  });

  function drawAxis(seriesName, x, anchor, tickOffset) {
    const cfg = seriesConfig[seriesName];

    svg.appendChild(createSvgElement("line", {
      x1: x, y1: plot.top, x2: x, y2: plot.bottom,
      stroke: cfg.color, "stroke-width": 1.2, opacity: 0.75
    }));

    for (let value = cfg.min; value <= cfg.max + 0.0001; value += cfg.step) {
      const y = scale(value, cfg.min, cfg.max, plot.top, plot.bottom);

      svg.appendChild(createSvgElement("line", {
        x1: anchor === "end" ? x - 6 : x, y1: y,
        x2: anchor === "end" ? x : x + 6, y2: y,
        stroke: cfg.color, "stroke-width": 1
      }));

      svg.appendChild(createSvgElement("text", {
        x: x + tickOffset, y: y + 4,
        fill: cfg.color, "font-size": "13", "text-anchor": anchor, "font-family": fontFamily
      }, seriesName === "temperature" ? value.toFixed(1) : String(value)));
    }
  }

  function drawSeries(seriesName, plotArea) {
    const cfg = seriesConfig[seriesName];
    const values = vitalData[seriesName];
    const points = [];

    values.forEach((value, index) => {
      if (value === null) return;
      const x = plotArea.left + ((plotArea.right - plotArea.left) / (vitalData.labels.length - 1)) * index;
      const y = scale(value, cfg.min, cfg.max, plotArea.top, plotArea.bottom);
      points.push({ x, y, value });
    });

    if (points.length > 1) {
      svg.appendChild(createSvgElement("polyline", {
        points: points.map(point => `${point.x},${point.y}`).join(" "),
        fill: "none", stroke: cfg.color, "stroke-width": 3,
        "stroke-linejoin": "round", "stroke-linecap": "round",
        "stroke-dasharray": cfg.dash
      }));
    }

    points.forEach(point => {
      svg.appendChild(createSvgElement("circle", {
        cx: point.x, cy: point.y, r: 6, fill: "#fff", stroke: cfg.color, "stroke-width": 3
      }));

      const labelOffset = seriesName === "diastolic" ? -18 : -17;
      svg.appendChild(createSvgElement("text", {
        x: point.x, y: point.y + labelOffset,
        fill: cfg.color, "font-size": "15", "font-weight": "700", "text-anchor": "middle",
        "paint-order": "stroke", stroke: "#fff", "stroke-width": "4", "stroke-linejoin": "round",
        "font-family": fontFamily
      }, seriesName === "temperature" ? point.value.toFixed(1) : String(point.value)));
    });
  }
}

function renderTable() {
  const rows = [
    {
      label: "体温（℃）",
      className: "row-temperature",
      values: vitalData.temperature.map(value => value === null ? "－" : value.toFixed(1))
    },
    {
      label: "血圧（上/下） mmHg",
      className: "row-blood",
      values: vitalData.systolic.map((value, index) => {
        if (value === null || vitalData.diastolic[index] === null) return "－";
        return `${value} / ${vitalData.diastolic[index]}`;
      })
    },
    {
      label: "脈拍（回/分）",
      className: "row-pulse",
      values: vitalData.pulse.map(value => value === null ? "－" : value)
    },
    {
      label: "SpO₂（%）",
      className: "row-spo2",
      values: vitalData.spo2.map(value => value === null ? "－" : value)
    },
    {
      label: "呼吸数（回/分）",
      className: "row-respiratory",
      values: vitalData.respiratory.map(value => value === null ? "－" : value)
    }
  ];

  tableBody.innerHTML = rows.map(row => `
    <tr class="${row.className}">
      <th scope="row">${row.label}</th>
      ${row.values.map(value => `<td>${value}</td>`).join("")}
    </tr>
  `).join("");
}

tabs.forEach(tab => {
  tab.addEventListener("click", () => {
    const seriesNames = tab.dataset.series.split(",");
    const willHide = tab.classList.contains("is-active");

    seriesNames.forEach(name => {
      if (willHide) hiddenSeries.add(name);
      else hiddenSeries.delete(name);
    });

    tab.classList.toggle("is-active", !willHide);
    drawChart();
  });
});

renderTable();
drawChart();

window.addEventListener("resize", drawChart);

const datePicker = document.getElementById("datePicker");
if (datePicker) {
  datePicker.addEventListener("change", () => {
    if (!datePicker.value) return;
    const params = new URLSearchParams({
      patient_id: datePicker.dataset.patientId,
      date: datePicker.value
    });
    location.href = `sp_vitals.php?${params}`;
  });
}
