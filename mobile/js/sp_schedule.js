// B-Care Mobile - 予定表画面
// 配置先: js/sp_schedule.js
// 日付ピッカーで日付を選ぶと、その日の予定を表示するページへ遷移する

(function () {
  const datePicker = document.getElementById("datePicker");
  if (!datePicker) return;

  datePicker.addEventListener("change", () => {
    if (!datePicker.value) return;
    const params = new URLSearchParams({
      patient_id: datePicker.dataset.patientId,
      date: datePicker.value
    });
    location.href = `sp_schedule.php?${params}`;
  });
})();
