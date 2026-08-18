<?php
/**
 * AIMS (SoluM ESL) 連携用の共通関数
 * 配置先: includes/aims_functions.php
 */

/**
 * 患者情報(patientsテーブルのレコード + doctor_name/primary_nurse)を
 * AIMS Article API (POST /articles) の dataList 要素1件分に変換する。
 *
 * data配下のDATA_1〜のフィールド割り当ては、AIMS側のラベルテンプレート
 * (Template Designerで作成する表示レイアウト)にどのフィールドを
 * バインドしたかによって意味が変わる。ここでの割り当ては仮のものなので、
 * 実際に使うテンプレートを作成した後、テンプレート側の項目名と
 * 突き合わせて調整すること。
 */
function patientToAimsArticlePayload(array $patient): array {
    $riskLabel = !empty($patient['fall_risk'])
        ? '転倒危険度' . (int)$patient['fall_risk']
        : '転倒危険度0';

    return [
        'id' => $patient['patient_id'],
        'name' => $patient['patient_name'] . '様の画面',
        'nfc' => '',
        'stationCode' => AIMS_STATION_CODE,
        'reservedOne' => $patient['patient_name'],
        'reservedTwo' => 'manager/patient_detail.php?patient_id=' . $patient['patient_id'],
        'reservedThree' => $patient['ward_name'],
        'data' => [
            'Product_ID' => $patient['patient_id'],
            'Product_Name' => $patient['patient_name'],
            'DATA_1' => $patient['room_no'],
            'DATA_2' => $patient['bed_no'],
            'DATA_3' => $patient['gender'] ?? '',
            'DATA_4' => (string)($patient['age'] ?? ''),
            'DATA_5' => $patient['doctor_name'] ?? '',
            'DATA_6' => $patient['primary_nurse'] ?? '',
            'DATA_7' => $riskLabel,
            'DATA_8' => $patient['transfer_type'] ?? '',
            'DATA_9' => empty($patient['qr_url']) ? '' : BCARE_BASE_URL . '/' . ltrim($patient['qr_url'], '/'),
        ],
    ];
}

/**
 * 複数患者分の変換結果を POST /articles のリクエストボディ形式にまとめる。
 */
function patientsToAimsArticlesRequestBody(array $patients): array {
    $dataList = [];
    foreach ($patients as $patient) {
        $dataList[] = patientToAimsArticlePayload($patient);
    }
    return ['dataList' => $dataList];
}

/**
 * AIMSへのHTTPリクエストを実行する共通関数。
 * @return array{httpCode:int, body:?array, rawBody:string, error:string}
 */
function aimsRequest(string $method, string $path, ?array $jsonBody = null): array {
    $url = AIMS_BASE_URL . $path;

    $headers = ['Accept: application/json'];
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 30,
    ];
    if ($jsonBody !== null) {
        $options[CURLOPT_POSTFIELDS] = json_encode($jsonBody, JSON_UNESCAPED_UNICODE);
        $headers[] = 'Content-Type: application/json';
        $options[CURLOPT_HTTPHEADER] = $headers;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, $options);
    $rawBody = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    $body = null;
    if ($rawBody !== false && $rawBody !== '') {
        $decoded = json_decode($rawBody, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $body = $decoded;
        }
    }

    return [
        'httpCode' => $httpCode,
        'body' => $body,
        'rawBody' => (string)$rawBody,
        'error' => $error,
    ];
}

/**
 * ESLラベル一覧を取得する(GET /labels)。
 */
function getAimsLabels(): array {
    return aimsRequest('GET', '/labels?' . http_build_query([
        'stationCode' => AIMS_STATION_CODE,
        'page' => 0,
        'size' => 200,
    ]));
}

/**
 * ラベル状態のサマリーを取得する(GET /labels/summary)。
 */
function getAimsLabelsSummary(): array {
    return aimsRequest('GET', '/labels/summary?' . http_build_query([
        'stationCode' => AIMS_STATION_CODE,
    ]));
}

/**
 * 全ラベルのAlive状態(オンライン/オフライン)をAIMS側に再チェックさせる(PUT /labels/alive)。
 */
function refreshAimsLabelsAlive(): array {
    return aimsRequest('PUT', '/labels/alive?' . http_build_query([
        'stationCode' => AIMS_STATION_CODE,
    ]));
}

/**
 * 患者(Article)を指定のESLラベル(labelCode)に紐付けて配信する。
 * POST /labels/link/article/{stationCode} を使用し、Article登録と
 * ラベルへの紐付け・配信を1回で行う（STEP5のPOST /articlesとは別経路）。
 *
 * templateNameは省略可能（AIMS側スキーマ上required外）だが、省略すると
 * ラベルに元々割り当てられていた別テンプレートがそのまま使われてしまい、
 * 患者名が表示されない不具合があったため、AIMS_TEMPLATE_NAMEで明示的に指定する。
 */
function linkPatientArticleToLabel(array $patient, string $labelCode): array {
    $article = patientToAimsArticlePayload($patient);

    $requestBody = [
        'dataList' => [$article],
        'labelCode' => $labelCode,
        'templateName' => AIMS_TEMPLATE_NAME,
    ];

    return aimsRequest('POST', '/labels/link/article/' . AIMS_STATION_CODE, $requestBody);
}
