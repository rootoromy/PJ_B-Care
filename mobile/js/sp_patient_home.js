// B-Care Mobile - 患者ホーム画面
// 配置先: js/sp_patient_home.js
// バイタルカードの「更新」ボタン：ページ遷移せずに最新値を取得して表示を差し替える

(function () {
  const card = document.getElementById("vitalCard");
  const updateBtn = document.getElementById("vitalUpdateBtn");
  if (!card || !updateBtn) return;

  const patientId = card.dataset.patientId;
  const fields = {
    systolic_bp: document.getElementById("vitalBpSys"),
    temperature: document.getElementById("vitalTemp"),
    diastolic_bp: document.getElementById("vitalBpDia"),
    pulse: document.getElementById("vitalPulse"),
    spo2: document.getElementById("vitalSpo2"),
    respiratory_rate: document.getElementById("vitalResp"),
  };
  const lastUpdated = document.getElementById("vitalLastUpdated");

  updateBtn.addEventListener("click", () => {
    if (updateBtn.disabled) return;
    updateBtn.disabled = true;
    updateBtn.classList.add("is-loading");

    fetch(`sp_vitals_latest.php?${new URLSearchParams({ patient_id: patientId })}`)
      .then((response) => response.json())
      .then((data) => {
        if (!data.success) return;

        Object.entries(fields).forEach(([key, el]) => {
          if (!el) return;
          el.textContent = data[key] !== null ? data[key] : "－";
        });
        if (lastUpdated) {
          lastUpdated.textContent = data.measured_at !== null ? data.measured_at : "記録なし";
        }
      })
      .finally(() => {
        updateBtn.disabled = false;
        updateBtn.classList.remove("is-loading");
      });
  });
})();
