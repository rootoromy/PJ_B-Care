// B-Care Manager - 預かり品管理
// 配置先: manager/js/deposit_management.js
// 対応HTML: manager/deposit_management.php
//
// 預かり品マスタ・保管場所マスタの新規登録/編集モーダルの開閉と、
// 各マスタ一覧の品名/保管場所名によるクライアント側の絞り込みのみを担当する。
// 登録・返却履歴の絞り込みはサーバー側（GETパラメータ）で行うため対象外。

const itemModal = document.getElementById("itemModal");
const locationModal = document.getElementById("locationModal");

function openItemModal(mode, data) {
  if (!itemModal) return;
  itemModal.classList.remove("hidden");
  itemModal.setAttribute("aria-hidden", "false");

  const form = itemModal.querySelector("form");
  const title = document.getElementById("itemModalTitle");
  const action = document.getElementById("itemFormAction");
  const idHidden = document.getElementById("itemIdHidden");

  form.reset();

  if (mode === "create") {
    title.textContent = "預かり品マスタ 新規登録";
    action.value = "create_item";
    idHidden.value = "";
    document.getElementById("itemOrder").value = 0;
  } else {
    title.textContent = "預かり品マスタ 編集";
    action.value = "update_item";
    idHidden.value = data.id;
    document.getElementById("itemName").value = data.name;
    document.getElementById("itemUnit").value = data.unit;
    document.getElementById("itemOrder").value = data.order;
  }
}

function closeItemModal() {
  if (!itemModal) return;
  itemModal.classList.add("hidden");
  itemModal.setAttribute("aria-hidden", "true");
}

function openLocationModal(mode, data) {
  if (!locationModal) return;
  locationModal.classList.remove("hidden");
  locationModal.setAttribute("aria-hidden", "false");

  const form = locationModal.querySelector("form");
  const title = document.getElementById("locationModalTitle");
  const action = document.getElementById("locationFormAction");
  const idHidden = document.getElementById("locationIdHidden");

  form.reset();

  if (mode === "create") {
    title.textContent = "保管場所マスタ 新規登録";
    action.value = "create_location";
    idHidden.value = "";
    document.getElementById("locationOrder").value = 0;
  } else {
    title.textContent = "保管場所マスタ 編集";
    action.value = "update_location";
    idHidden.value = data.id;
    document.getElementById("locationName").value = data.name;
    document.getElementById("locationOrder").value = data.order;
  }
}

function closeLocationModal() {
  if (!locationModal) return;
  locationModal.classList.add("hidden");
  locationModal.setAttribute("aria-hidden", "true");
}

document.getElementById("openCreateItemModal")?.addEventListener("click", () => openItemModal("create"));
document.getElementById("closeItemModal")?.addEventListener("click", closeItemModal);
document.getElementById("cancelItemModal")?.addEventListener("click", closeItemModal);

document.getElementById("openCreateLocationModal")?.addEventListener("click", () => openLocationModal("create"));
document.getElementById("closeLocationModal")?.addEventListener("click", closeLocationModal);
document.getElementById("cancelLocationModal")?.addEventListener("click", closeLocationModal);

document.getElementById("itemMasterSection")?.addEventListener("click", (event) => {
  const button = event.target.closest("[data-edit-item]");
  if (!button) return;
  openItemModal("edit", {
    id: button.dataset.id,
    name: button.dataset.name,
    unit: button.dataset.unit,
    order: button.dataset.order,
  });
});

document.getElementById("locationMasterSection")?.addEventListener("click", (event) => {
  const button = event.target.closest("[data-edit-location]");
  if (!button) return;
  openLocationModal("edit", {
    id: button.dataset.id,
    name: button.dataset.name,
    order: button.dataset.order,
  });
});

function wireSearchFilter(inputId, sectionId) {
  const input = document.getElementById(inputId);
  const section = document.getElementById(sectionId);
  if (!input || !section) return;
  input.addEventListener("input", () => {
    const keyword = input.value.trim().toLowerCase();
    section.querySelectorAll(".dm-row").forEach((row) => {
      row.hidden = keyword !== "" && !row.dataset.name.includes(keyword);
    });
  });
}

wireSearchFilter("itemSearchInput", "itemMasterSection");
wireSearchFilter("locationSearchInput", "locationMasterSection");
