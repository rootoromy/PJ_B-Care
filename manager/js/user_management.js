/**
 * B-Care Manager - ユーザー管理
 * 配置先: manager/js/user_management.js
 *
 * 一覧の絞り込み・モーダル制御を行う。保存はフォームの通常送信でstaffテーブルに反映される
 * （user_management.php側でPOSTを処理）。
 */

const PAGE_SIZE = 10;

const users = window.INITIAL_USERS || [];
let filteredUsers = [];
let currentPage = 1;

const tbody = document.getElementById("userTableBody");
const recordCount = document.getElementById("recordCount");
const pagination = document.getElementById("pagination");
const keywordInput = document.getElementById("keywordInput");
const roleFilter = document.getElementById("roleFilter");
const wardFilter = document.getElementById("wardFilter");
const statusFilter = document.getElementById("statusFilter");
const showInactive = document.getElementById("showInactive");

const modal = document.getElementById("userModal");
const modalTitle = document.getElementById("modalTitle");
const form = document.getElementById("userForm");
const formAction = document.getElementById("formAction");
const staffIdHidden = document.getElementById("staffIdHidden");
const userPassword = document.getElementById("userPassword");
const userPasswordLabel = document.getElementById("userPasswordLabel");

function renderUsers(list) {
  tbody.innerHTML = "";

  list.forEach((user) => {
    const tr = document.createElement("tr");
    tr.innerHTML = `
      <td>${user.id}</td>
      <td>${user.name}</td>
      <td>${user.ward}</td>
      <td>
        <span class="badge ${user.role === "管理者" ? "badge-admin" : "badge-staff"}">
          ${user.role}
        </span>
      </td>
      <td>
        <span class="badge ${user.status === "有効" ? "badge-active" : "badge-inactive"}">
          ${user.status}
        </span>
      </td>
      <td>${user.lastLogin}</td>
      <td><button class="btn-select" data-staff-id="${user.staffId}" type="button">編集</button></td>
    `;
    tbody.appendChild(tr);
  });
}

function renderPagination(totalCount) {
  pagination.innerHTML = "";

  const totalPages = Math.max(1, Math.ceil(totalCount / PAGE_SIZE));
  if (currentPage > totalPages) currentPage = totalPages;

  const prev = document.createElement(currentPage === 1 ? "span" : "a");
  prev.innerHTML = "&#8249;";
  prev.className = currentPage === 1 ? "disabled" : "";
  if (currentPage !== 1) {
    prev.href = "#";
    prev.addEventListener("click", (event) => {
      event.preventDefault();
      goToPage(currentPage - 1);
    });
  }
  pagination.appendChild(prev);

  for (let page = 1; page <= totalPages; page += 1) {
    const item = document.createElement(page === currentPage ? "span" : "a");
    item.textContent = String(page);
    item.className = page === currentPage ? "current" : "";
    if (page !== currentPage) {
      item.href = "#";
      item.addEventListener("click", (event) => {
        event.preventDefault();
        goToPage(page);
      });
    }
    pagination.appendChild(item);
  }

  const next = document.createElement(currentPage === totalPages ? "span" : "a");
  next.innerHTML = "&#8250;";
  next.className = currentPage === totalPages ? "disabled" : "";
  if (currentPage !== totalPages) {
    next.href = "#";
    next.addEventListener("click", (event) => {
      event.preventDefault();
      goToPage(currentPage + 1);
    });
  }
  pagination.appendChild(next);
}

function renderPage() {
  const total = filteredUsers.length;
  const startIndex = (currentPage - 1) * PAGE_SIZE;
  const pageItems = filteredUsers.slice(startIndex, startIndex + PAGE_SIZE);

  renderUsers(pageItems);

  recordCount.textContent = total
    ? `全${total}件中 ${startIndex + 1}〜${startIndex + pageItems.length}件を表示`
    : "該当するユーザーはいません";

  renderPagination(total);
}

function goToPage(page) {
  currentPage = page;
  renderPage();
}

function applyFilters() {
  const keyword = keywordInput.value.trim().toLowerCase();
  const role = roleFilter.value;
  const ward = wardFilter.value;
  const status = statusFilter.value;

  filteredUsers = users.filter((user) => {
    const keywordMatch =
      !keyword ||
      user.id.toLowerCase().includes(keyword) ||
      user.name.toLowerCase().includes(keyword) ||
      user.email.toLowerCase().includes(keyword);

    const roleMatch = !role || user.role === role;
    const wardMatch = !ward || user.ward === ward;
    const statusMatch = !status || user.status === status;
    const inactiveMatch = showInactive.checked || user.status !== "無効";

    return keywordMatch && roleMatch && wardMatch && statusMatch && inactiveMatch;
  });

  currentPage = 1;
  renderPage();
}

function resetFilters() {
  keywordInput.value = "";
  roleFilter.value = "";
  wardFilter.value = "";
  statusFilter.value = "";
  showInactive.checked = false;
  applyFilters();
}

function openModal(mode, user = null) {
  modal.classList.remove("hidden");
  modal.setAttribute("aria-hidden", "false");

  if (mode === "create") {
    modalTitle.textContent = "新規ユーザー追加";
    formAction.value = "create";
    staffIdHidden.value = "";
    form.reset();
    document.getElementById("userStatus").value = "有効";
    document.getElementById("userId").disabled = false;
    userPassword.required = true;
    userPasswordLabel.textContent = "初期パスワード";
    userPassword.placeholder = "ログイン用のパスワードを入力";
  } else {
    modalTitle.textContent = "ユーザー編集";
    formAction.value = "update";
    staffIdHidden.value = user.staffId;
    document.getElementById("userId").value = user.id;
    document.getElementById("userName").value = user.name;
    document.getElementById("userEmail").value = user.email;
    document.getElementById("userWard").value = user.ward;
    document.getElementById("userRole").value = user.role;
    document.getElementById("userStatus").value = user.status;
    document.getElementById("userId").disabled = true;
    userPassword.value = "";
    userPassword.required = false;
    userPasswordLabel.textContent = "パスワード（変更する場合のみ入力）";
    userPassword.placeholder = "変更しない場合は空欄のまま";
  }
}

function closeModal() {
  modal.classList.add("hidden");
  modal.setAttribute("aria-hidden", "true");
}

document.getElementById("filterButton").addEventListener("click", applyFilters);
document.getElementById("clearButton").addEventListener("click", resetFilters);
showInactive.addEventListener("change", applyFilters);
keywordInput.addEventListener("keydown", (event) => {
  if (event.key === "Enter") applyFilters();
});

document.getElementById("openCreateModal").addEventListener("click", () => openModal("create"));
document.getElementById("closeModal").addEventListener("click", closeModal);
document.getElementById("cancelModal").addEventListener("click", closeModal);

tbody.addEventListener("click", (event) => {
  const button = event.target.closest(".btn-select");
  if (!button) return;
  const user = users.find((item) => item.staffId === button.dataset.staffId);
  if (user) openModal("edit", user);
});

form.addEventListener("submit", (event) => {
  const isCreate = formAction.value === "create";
  const name = document.getElementById("userName").value.trim();
  const status = document.getElementById("userStatus").value;

  if (isCreate) {
    const id = document.getElementById("userId").value.trim();
    if (users.some((user) => user.id === id)) {
      event.preventDefault();
      alert("同じユーザーIDがすでに登録されています。");
      return;
    }
  } else {
    const current = users.find((user) => user.staffId === staffIdHidden.value);
    const wasActive = current && current.status !== "無効";
    if (wasActive && status === "無効") {
      const confirmed = confirm(
        `${name}さんのアカウントを無効にしますか？\n無効化するとB-Careへログインできなくなります。`
      );
      if (!confirmed) {
        event.preventDefault();
        return;
      }
    }
  }
  // バリデーションを通過した場合はフォームを通常送信し、サーバー側でstaffテーブルに反映する
});

modal.addEventListener("click", (event) => {
  if (event.target === modal) closeModal();
});

applyFilters();
