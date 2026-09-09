// B-Care Mobile - ラベル一覧画面
// 配置先: mobile/js/sp_esl_label_list.js
// 対応HTML: mobile/sp_esl_label_list.php
//
// ESLラベルの一覧を「割当済み」「未割当」の2ブロックに分けて描画し、
// それぞれ開閉できる。病棟・患者検索の絞り込みは両ブロックに共通で効く
// （未割当ラベルはどの病棟にも属さないため病棟フィルタでは常に表示する）。
// 「患者を選択」は割当先を指定するためのもので、割当済みラベルの「解除」・
// 未割当ラベルの「割当」は非表示フォーム(#listAssignForm/#listUnassignForm)を
// sp_confirm.js の submitFormViaFetch() 経由で sp_esl_label_process.php に渡す
// （ネイティブ<form>送信のままだとhttp運用のためブラウザの「安全でないフォーム
// 送信」警告が出るため、fetchで送ってからリダイレクト先へ遷移する）。
// 割当・解除の結果はサーバー側の再描画（ページ遷移）で反映されるため、
// 割当されたラベルは自動的に「割当済み」ブロックへ、解除されたラベルは
// 「未割当」ブロックへ移る。

const labelRows = Array.isArray(window.ESL_LABEL_ROWS) ? window.ESL_LABEL_ROWS : [];
const unassignedPatients = Array.isArray(window.ESL_UNASSIGNED_PATIENTS) ? window.ESL_UNASSIGNED_PATIENTS : [];
const isViewer = window.IS_VIEWER === true;

const wardFilter = document.getElementById("wardFilter");
const patientSearchInput = document.getElementById("patientSearchInput");
const patientSelect = document.getElementById("patientSelect");
const assignedRowList = document.getElementById("assignedRowList");
const unassignedRowList = document.getElementById("unassignedRowList");
const assignedCount = document.getElementById("assignedCount");
const unassignedCount = document.getElementById("unassignedCount");
const assignedEmptyMessage = document.getElementById("assignedEmptyMessage");
const unassignedEmptyMessage = document.getElementById("unassignedEmptyMessage");
const toast = document.getElementById("toast");
const assignForm = document.getElementById("listAssignForm");
const unassignForm = document.getElementById("listUnassignForm");

let currentWard = "";
let currentKeyword = "";
let toastTimer;

function normalize(value) {
  return String(value ?? "").replaceAll(" ", "").trim().toLowerCase();
}

function escapeHtml(value) {
  return String(value ?? "").replace(/[&<>"']/g, (ch) => ({
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#39;",
  })[ch]);
}

function showToast(message) {
  clearTimeout(toastTimer);
  toast.textContent = message;
  toast.classList.add("is-visible");
  toastTimer = setTimeout(() => toast.classList.remove("is-visible"), 1700);
}

function createRow(row) {
  const article = document.createElement("article");
  article.className = "esl-row";

  const wardRoom = [row.ward_name, row.room_no ? `${row.room_no}号室${row.bed_no || ""}` : ""]
    .filter(Boolean)
    .join(" ");

  const patientBlock = row.assigned
    ? `<span class="esl-row-patient-name">${escapeHtml(row.patient_id)}　${escapeHtml(row.patient_name)}</span><small>${escapeHtml(wardRoom)}</small>`
    : `<span class="esl-row-patient-empty">-</span>`;

  const actionBlock = isViewer
    ? ""
    : row.assigned
    ? `<button type="button" class="esl-row-btn esl-row-btn--unassign" data-patient-id="${escapeHtml(row.patient_id)}" data-patient-name="${escapeHtml(row.patient_name)}">解除</button>`
    : `<button type="button" class="esl-row-btn esl-row-btn--assign" data-code="${escapeHtml(row.code)}">割当</button>`;

  article.innerHTML = `
    <div class="esl-row-main">
      <div class="esl-row-top">
        <span class="esl-row-code">${escapeHtml(row.code)}</span>
        <span class="esl-badge-status ${row.assigned ? "esl-badge-status--assigned" : "esl-badge-status--unassigned"}">${row.assigned ? "割当済" : "未割当"}</span>
      </div>
      <div class="esl-row-patient">${patientBlock}</div>
    </div>
    <div class="esl-row-action">${actionBlock}</div>
  `;

  return article;
}

function getFilteredRows() {
  return labelRows.filter((row) => {
    // 未割当ラベルはどの病棟にも属さないため、病棟フィルタでは除外しない
    if (currentWard && row.assigned && row.ward_name !== currentWard) return false;

    if (currentKeyword) {
      const matches =
        normalize(row.patient_id).includes(currentKeyword) ||
        normalize(row.patient_name).includes(currentKeyword);
      if (!matches) return false;
    }

    return true;
  });
}

function render() {
  const filtered = getFilteredRows();
  const assigned = filtered.filter((row) => row.assigned);
  const unassigned = filtered.filter((row) => !row.assigned);

  assignedRowList.replaceChildren(...assigned.map(createRow));
  unassignedRowList.replaceChildren(...unassigned.map(createRow));

  assignedCount.textContent = String(assigned.length);
  unassignedCount.textContent = String(unassigned.length);

  assignedEmptyMessage.hidden = assigned.length !== 0;
  unassignedEmptyMessage.hidden = unassigned.length !== 0;
}

// 「患者を選択」の選択肢を、病棟フィルタに合わせて絞り込む。
// <option hidden> はネイティブの選択UI（特にモバイル）で無視されることがあるため、
// 選択肢そのものを毎回組み直す。選択中の患者が絞り込みで対象外になった場合は
// 選択を解除する。
function renderPatientOptions(ward) {
  if (!patientSelect) return;
  const previousValue = patientSelect.value;
  const matched = unassignedPatients.filter((p) => !ward || p.ward_name === ward);

  const optionsHtml = ['<option value="">患者を選択してください</option>']
    .concat(matched.map((p) => `<option value="${escapeHtml(p.patient_id)}">${escapeHtml(p.patient_id)}　${escapeHtml(p.patient_name)}</option>`))
    .join("");

  patientSelect.innerHTML = optionsHtml;
  patientSelect.value = matched.some((p) => p.patient_id === previousValue) ? previousValue : "";
}

wardFilter.addEventListener("change", () => {
  currentWard = wardFilter.value;
  renderPatientOptions(currentWard);
  render();
});

patientSearchInput.addEventListener("input", () => {
  currentKeyword = normalize(patientSearchInput.value);
  render();
});

async function handleRowListClick(event) {
  const assignBtn = event.target.closest(".esl-row-btn--assign");
  if (assignBtn) {
    const targetPatientId = patientSelect.value;
    if (!targetPatientId) {
      showToast("先に「患者を選択」してください");
      return;
    }
    const targetOption = patientSelect.selectedOptions[0];
    const targetLabel = targetOption ? targetOption.textContent.trim() : targetPatientId;
    const ok = await confirmDialog({
      title: "ラベルを割り当て",
      message: `${targetLabel} にラベル ${assignBtn.dataset.code} を割り当てますか？`,
    });
    if (!ok) return;

    assignForm.querySelector('[name="patient_id"]').value = targetPatientId;
    assignForm.querySelector('[name="label_code"]').value = assignBtn.dataset.code;
    submitFormViaFetch(assignForm);
    return;
  }

  const unassignBtn = event.target.closest(".esl-row-btn--unassign");
  if (unassignBtn) {
    const ok = await confirmDialog({
      title: "ラベル割り当てを解除",
      message: `${unassignBtn.dataset.patientName}様のラベル割り当てを解除しますか？`,
    });
    if (!ok) return;
    unassignForm.querySelector('[name="patient_id"]').value = unassignBtn.dataset.patientId;
    submitFormViaFetch(unassignForm);
  }
}

assignedRowList.addEventListener("click", handleRowListClick);
unassignedRowList.addEventListener("click", handleRowListClick);

// 割当済み／未割当ブロックの開閉
document.querySelectorAll(".collapsible").forEach((toggleHeader) => {
  toggleHeader.addEventListener("click", () => {
    const body = document.getElementById(toggleHeader.dataset.target);
    body.hidden = !body.hidden;
    toggleHeader.classList.toggle("is-collapsed", body.hidden);
  });
});

render();
