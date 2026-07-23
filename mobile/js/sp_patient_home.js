// 患者ホーム画面：セクションの折りたたみ開閉
// 配置先: mobile/js/sp_patient_home.js
// 対応HTML: mobile/sp_patient_home.php の [data-toggle] ボタン

document.querySelectorAll('[data-toggle]').forEach((button) => {
  button.addEventListener('click', () => {
    button.closest('.section-card').classList.toggle('collapsed');
  });
});
