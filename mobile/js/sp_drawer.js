// 共通ドロワー開閉処理（縦向き・バー型ページ用）
// 配置先: js/sp_drawer.js
// 対応HTML: mobile/includes/sp_header.php + mobile/includes/sp_drawer.php
// ※ sp_vitals.php は独自実装のため、このファイルを読み込みません。

(function () {
  const menuButton = document.getElementById("menuButton");
  const drawer = document.getElementById("drawer");
  const closeButton = document.getElementById("drawerClose") || document.querySelector(".drawer-close");
  const backdrop = document.getElementById("drawerBackdrop") || document.querySelector(".drawer-backdrop");

  if (!menuButton || !drawer || !closeButton || !backdrop) return;

  function openDrawer() {
    drawer.removeAttribute("inert");
    drawer.classList.add("open");
    drawer.setAttribute("aria-hidden", "false");
    menuButton.setAttribute("aria-expanded", "true");
    backdrop.hidden = false;
    document.body.classList.add("drawer-open");
  }

  function closeDrawer() {
    if (drawer.contains(document.activeElement)) {
      menuButton.focus();
    }
    drawer.classList.remove("open");
    drawer.setAttribute("aria-hidden", "true");
    drawer.setAttribute("inert", "");
    menuButton.setAttribute("aria-expanded", "false");
    backdrop.hidden = true;
    document.body.classList.remove("drawer-open");
  }

  menuButton.addEventListener("click", openDrawer);
  closeButton.addEventListener("click", closeDrawer);
  backdrop.addEventListener("click", closeDrawer);
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") closeDrawer();
  });
})();
