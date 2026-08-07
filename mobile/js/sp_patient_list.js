// B-Care Mobile - 患者一覧画面
// 配置先: mobile/js/sp_patient_list.js
// 対応HTML: mobile/sp_patient_list.php
//
// 患者データとピックアップ（ピン留め）状態は PHP 側で patients /
// staff_pinned_patients テーブルから取得し、window.PATIENTS_DATA (JSON)
// として埋め込まれたものを利用する。ピン留めの切り替えは
// sp_patient_pin_toggle.php に fetch し、ログイン中スタッフ単位でDBに保存する。

const patients = Array.isArray(window.PATIENTS_DATA) ? window.PATIENTS_DATA : [];

const searchInput = document.getElementById("searchInput");
const searchButton = document.getElementById("searchButton");
const pickupSection = document.getElementById("pickupSection");
const allSection = document.getElementById("allSection");
const pickupList = document.getElementById("pickupList");
const patientList = document.getElementById("patientList");
const emptyMessage = document.getElementById("emptyMessage");
const pagination = document.getElementById("pagination");
const toast = document.getElementById("toast");

const PAGE_SIZE = 20;

let currentKeyword = "";
let currentPage = 1;
let toastTimer;

function normalize(value) {
  return String(value).replaceAll(" ", "").trim().toLowerCase();
}

function getFilteredPatients() {
  if (!currentKeyword) return patients;

  return patients.filter((patient) => {
    return (
      normalize(patient.patient_id).includes(currentKeyword) ||
      normalize(patient.patient_name).includes(currentKeyword)
    );
  });
}

function createPatientRow(patient, no) {
  const row = document.createElement("article");
  row.className = "patient-row";
  row.dataset.patientId = patient.patient_id;

  row.innerHTML = `
    <span class="patient-no">${no}</span>

    <button
      class="pin-button ${patient.pinned ? "is-pinned" : ""}"
      type="button"
      data-action="pin"
      data-patient-id="${patient.patient_id}"
      aria-label="${patient.patient_name}を${patient.pinned ? "ピックアップから外す" : "ピックアップに追加"}"
      aria-pressed="${patient.pinned}"
      title="${patient.pinned ? "ピックアップから外す" : "ピックアップに追加"}"
    >${patient.pinned ? "★" : "☆"}</button>

    <span class="patient-id">${patient.patient_id}</span>
    <span class="patient-name">${patient.patient_name}</span>
    <span class="patient-gender">${patient.gender ?? ""}</span>
    <span class="patient-age">${patient.age ?? "-"}歳</span>

    <button
      class="select-button"
      type="button"
      data-action="select"
      data-patient-id="${patient.patient_id}"
    >選択</button>
  `;

  return row;
}

function renderPatients() {
  const filtered = getFilteredPatients();
  const pinned = filtered.filter((patient) => patient.pinned);
  const others = filtered.filter((patient) => !patient.pinned);

  const totalPages = Math.max(1, Math.ceil(others.length / PAGE_SIZE));
  currentPage = Math.min(Math.max(currentPage, 1), totalPages);
  const pageStart = (currentPage - 1) * PAGE_SIZE;
  const pageItems = others.slice(pageStart, pageStart + PAGE_SIZE);

  pickupList.replaceChildren(...pinned.map((patient, i) => createPatientRow(patient, i + 1)));
  patientList.replaceChildren(...pageItems.map((patient, i) => createPatientRow(patient, pageStart + i + 1)));

  pickupSection.hidden = pinned.length === 0;
  allSection.hidden = others.length === 0;
  emptyMessage.hidden = filtered.length !== 0;

  renderPagination(totalPages);
}

function renderPagination(totalPages) {
  pagination.replaceChildren();

  if (totalPages <= 1) {
    pagination.hidden = true;
    return;
  }
  pagination.hidden = false;

  const prevBtn = document.createElement("button");
  prevBtn.type = "button";
  prevBtn.textContent = "前へ";
  prevBtn.disabled = currentPage === 1;
  prevBtn.addEventListener("click", () => {
    currentPage -= 1;
    renderPatients();
  });
  pagination.appendChild(prevBtn);

  for (let page = 1; page <= totalPages; page += 1) {
    const pageBtn = document.createElement("button");
    pageBtn.type = "button";
    pageBtn.textContent = String(page);
    pageBtn.setAttribute("aria-current", page === currentPage ? "page" : "false");
    if (page === currentPage) pageBtn.classList.add("is-active");
    pageBtn.addEventListener("click", () => {
      currentPage = page;
      renderPatients();
    });
    pagination.appendChild(pageBtn);
  }

  const nextBtn = document.createElement("button");
  nextBtn.type = "button";
  nextBtn.textContent = "次へ";
  nextBtn.disabled = currentPage === totalPages;
  nextBtn.addEventListener("click", () => {
    currentPage += 1;
    renderPatients();
  });
  pagination.appendChild(nextBtn);
}

function showToast(message) {
  clearTimeout(toastTimer);
  toast.textContent = message;
  toast.classList.add("is-visible");

  toastTimer = setTimeout(() => {
    toast.classList.remove("is-visible");
  }, 1700);
}

function executeSearch() {
  currentKeyword = normalize(searchInput.value);
  currentPage = 1;
  renderPatients();

  if (getFilteredPatients().length === 0) {
    showToast("該当する患者が見つかりません");
  }
}

async function togglePin(patientId, button) {
  const patient = patients.find((item) => item.patient_id === patientId);
  if (!patient) return;

  button.disabled = true;

  try {
    const response = await fetch("sp_patient_pin_toggle.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ patient_id: patientId }),
    });
    const data = await response.json();

    if (!data.success) {
      showToast(data.message || "ピックアップの更新に失敗しました");
      return;
    }

    patient.pinned = data.pinned;
    renderPatients();

    showToast(
      patient.pinned
        ? `${patient.patient_name}をピックアップに追加しました`
        : `${patient.patient_name}をピックアップから外しました`
    );
  } catch (error) {
    console.warn("ピックアップの更新に失敗しました。", error);
    showToast("通信エラーが発生しました");
  } finally {
    button.disabled = false;
  }
}

function selectPatient(patientId) {
  const patient = patients.find((item) => item.patient_id === patientId);
  if (!patient) return;

  location.href = `sp_patient_home.php?patient_id=${encodeURIComponent(patient.patient_id)}`;
}

document.querySelector(".patient-table").addEventListener("click", (event) => {
  const button = event.target.closest("button[data-action]");
  if (!button) return;

  const patientId = button.dataset.patientId;

  if (button.dataset.action === "pin") {
    togglePin(patientId, button);
  }

  if (button.dataset.action === "select") {
    selectPatient(patientId);
  }
});

searchButton.addEventListener("click", executeSearch);

searchInput.addEventListener("keydown", (event) => {
  if (event.key === "Enter") {
    executeSearch();
  }
});

searchInput.addEventListener("search", () => {
  if (searchInput.value === "") {
    currentKeyword = "";
    currentPage = 1;
    renderPatients();
  }
});

renderPatients();
