<?php
/**
 * 表示ブロック: バイタル（最新値）
 * 配置先: mobile/includes/blocks/block_vitals.php
 * ブロックキー: vitals
 *
 * 【呼び出し側で用意しておく変数】
 *   $latest_vital : vitals テーブルの最新1件の連想配列（無ければ null）
 *   $patient_id   : GETから受け取った患者ID文字列
 *   h()           : エスケープ用ヘルパー関数（mobile/sp_patient_home.php で定義）
 *   vitalValue()  : $latest_vital から欠測を null に正規化して値を取り出す関数
 *                   （mobile/sp_patient_home.php で定義）
 */
$bp_sys = vitalValue($latest_vital, 'systolic_bp');
$bp_dia = vitalValue($latest_vital, 'diastolic_bp');
$temp   = vitalValue($latest_vital, 'temperature');
$pulse  = vitalValue($latest_vital, 'pulse');
$spo2   = vitalValue($latest_vital, 'spo2');
$resp   = vitalValue($latest_vital, 'respiratory_rate');
?>
<section class="card section-card vital-card" id="vitalCard" data-patient-id="<?= h($patient_id) ?>">
  <a class="section-title" href="sp_vitals.php?patient_id=<?= urlencode($patient_id) ?>">
    <span><b></b>バイタル</span>
    <svg><use href="#i-chevron"></use></svg>
  </a>
  <div class="section-body">
    <div class="vitals-grid">
      <div><span>血圧(上)<small>mmHg</small></span><strong id="vitalBpSys"><?= $bp_sys !== null ? h($bp_sys) : '－' ?></strong></div>
      <div><span>体温<small>℃</small></span><strong id="vitalTemp"><?= $temp !== null ? h($temp) : '－' ?></strong></div>
      <div><span>血圧(下)<small>mmHg</small></span><strong id="vitalBpDia"><?= $bp_dia !== null ? h($bp_dia) : '－' ?></strong></div>
      <div><span>脈拍<small>bpm</small></span><strong id="vitalPulse"><?= $pulse !== null ? h($pulse) : '－' ?></strong></div>
      <div><span>呼吸数<small>回/分</small></span><strong id="vitalResp"><?= $resp !== null ? h($resp) : '－' ?></strong></div>
      <div><span>SPO2<small>%</small></span><strong id="vitalSpo2"><?= $spo2 !== null ? h($spo2) : '－' ?></strong></div>
    </div>
    <div class="vital-footer">
      <p><svg><use href="#i-clock"></use></svg>最終更新：<span id="vitalLastUpdated"><?= $latest_vital ? h(date('Y/m/d H:i', strtotime($latest_vital['measured_at']))) : '記録なし' ?></span></p>
      <button type="button" class="vital-update-btn" id="vitalUpdateBtn"><svg><use href="#i-refresh"></use></svg>更新</button>
    </div>
  </div>
</section>
