<?php
$basePath = $basePath ?? '';
$typeLabel = $type === 'approach_kpi' ? 'Подход вагонов' : 'Отправление вагонов';
$config = is_array($config) ? $config : [];
$detail = is_array($config['detail'] ?? null) ? $config['detail'] : [];
$conditions = (array) ($config['conditions'] ?? []);
$fieldMap = array_column($fields, null, 'key');
$allFieldMap = array_column($allFields ?? $fields, null, 'key');
if (!$conditions)
  $conditions = [['field' => $fields[0]['key'] ?? '', 'operator' => '=', 'value' => '']];
?>
<!DOCTYPE html>
<html lang="ru">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($appName) ?> — <?= $edited ? 'Редактирование KPI' : 'Новый KPI' ?></title>
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/app.css">
  <style>
    .user-kpi-form {
      width: 100%;
      max-width: 920px;
      margin: 0 auto 40px
    }

    .user-kpi-section {
      margin-bottom: 14px;
      border-radius: 12px;
      overflow: hidden
    }

    .user-kpi-section .ui-card-head {
      padding: 15px 18px;
      font-size: 14px;
      font-weight: 700
    }

    .user-kpi-section .ui-card-body {
      display: grid;
      gap: 16px;
      padding: 18px
    }

    .user-kpi-section .ui-field {
      min-width: 0;
      display: grid;
      gap: 7px
    }

    .user-kpi-section .ui-field>label {
      font-size: 10px;
      font-weight: 750;
      letter-spacing: .07em;
      color: var(--text-3)
    }

    .user-kpi-section input:not([type=checkbox]),
    .user-kpi-section select {
      width: 100%;
      min-width: 0;
      height: 40px;
      box-sizing: border-box;
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 0 11px;
      background: var(--surface);
      color: var(--text-1);
      font: inherit
    }

    .user-kpi-section input:focus,
    .user-kpi-section select:focus {
      outline: none;
      border-color: var(--accent);
      box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 12%, transparent)
    }

    .user-kpi-condition-row {
      display: grid;
      grid-template-columns: minmax(0, 1.2fr) minmax(0, .85fr) minmax(0, 1fr) 40px;
      gap: 9px;
      align-items: center
    }

    .user-kpi-condition-row select,
    .user-kpi-condition-row input {
      height: 40px;
      min-width: 0;
      width: 100%;
      box-sizing: border-box;
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 0 10px;
      background: var(--surface);
      color: var(--text-1)
    }

    .user-kpi-condition-row .user-kpi-remove {
      height: 40px;
      width: 40px;
      padding: 0;
      font-size: 17px
    }

    .user-kpi-hint {
      margin: 0;
      color: var(--text-3);
      font-size: 11px;
      line-height: 1.45
    }

    .user-kpi-hidden {
      display: none !important
    }

    .user-kpi-error {
      min-height: 18px;
      margin: 0;
      color: var(--brand-neg);
      font-size: 13px
    }

    .user-kpi-form .ui-form-actions {
      display: flex;
      justify-content: space-between;
      gap: 9px;
      padding-top: 4px
    }

    .user-kpi-main-actions {
      display: flex;
      gap: 9px;
      margin-left: auto
    }

    .user-kpi-delete {
      color: var(--brand-neg)
    }

    @media(max-width:700px) {
      .user-kpi-form {
        max-width: none
      }

      .user-kpi-condition-row {
        grid-template-columns: 1fr 1fr
      }

      .user-kpi-condition-row .user-kpi-value {
        grid-column: 1/-1
      }

      .user-kpi-checks {
        grid-template-columns: 1fr 1fr
      }
    }

    @media(max-width:460px) {
      .user-kpi-section .ui-card-body {
        padding: 14px
      }
    }
  </style>
</head>

<body class="<?= !empty($modalForm) ? 'user-kpi-modal-page' : '' ?>">
  <?php if (empty($modalForm)): ?>
  <?php
  $headerSub = '<div class="brand-sub">' . htmlspecialchars($typeLabel) . '</div>';
  $headerLeft = '<a href="' . htmlspecialchars($basePath) . '/" class="btn-nav-back" title="На главную">←</a>';
  $headerRight = '';
  include __DIR__ . '/partials/header.php';
  ?>
  <?php endif; ?>
  <div class="app-body">
    <main class="main-content ui-form-page user-kpi-form">
      <div class="ui-page-head ui-page-head--form">
        <?php if (empty($modalForm)): ?>
        <a class="ui-back-link" href="<?= htmlspecialchars($basePath) ?>/">← К карточкам KPI</a>
        <?php endif; ?>
        <h1><?= $edited ? 'Редактирование KPI' : 'Создание KPI' ?></h1>
      </div>
      <section class="ui-card user-kpi-section">
        <div class="ui-card-head">Основные настройки</div>
        <div class="ui-card-body">
          <div class="ui-field"><label for="userKpiLabel">Название KPI</label><input id="userKpiLabel" maxlength="150"
              value="<?= htmlspecialchars((string) ($edited['label'] ?? '')) ?>" required></div>
          <?php if (!empty($canSetGlobal)): ?>
            <div class="ui-field"><label for="userKpiVisibility">Область видимости</label><select id="userKpiVisibility">
                <option value="PRIVATE" <?= ($edited['visibility'] ?? 'PRIVATE') === 'PRIVATE' ? 'selected' : '' ?>>Личная</option>
                <option value="GLOBAL" <?= ($edited['visibility'] ?? '') === 'GLOBAL' ? 'selected' : '' ?>>Общая (для всех пользователей)</option>
              </select></div>
          <?php endif; ?>
        </div>
      </section>
      <section class="ui-card user-kpi-section">
        <div class="ui-card-head">Условия отбора</div>
        <div class="ui-card-body">
          <div id="userKpiConditions">
            <?php foreach ($conditions as $condition): ?>
              <div class="user-kpi-condition-row">
                <select class="user-kpi-field" aria-label="Поле" required>
                  <?php $conditionField = (string) ($condition['field'] ?? ''); ?>
                  <?php if ($conditionField !== '' && !isset($fieldMap[$conditionField]) && isset($allFieldMap[$conditionField])): ?>
                    <option value="<?= htmlspecialchars($conditionField) ?>"
                      data-type="<?= htmlspecialchars($allFieldMap[$conditionField]['type']) ?>" selected hidden><?= htmlspecialchars($allFieldMap[$conditionField]['label']) ?> (недоступно)</option>
                  <?php endif; ?>
                  <?php foreach ($fields as $field): ?>
                    <option value="<?= htmlspecialchars($field['key']) ?>"
                      data-type="<?= htmlspecialchars($field['type']) ?>" <?= ($condition['field'] ?? '') === $field['key'] ? 'selected' : '' ?>><?= htmlspecialchars($field['label']) ?></option><?php endforeach; ?>
                </select>
                <select class="user-kpi-operator" aria-label="Оператор">
                  <option value="=">Равно</option>
                  <option value="!=">Не равно</option>
                  <option value="LIKE">Содержит</option>
                  <option value=">">Больше</option>
                  <option value="<">Меньше</option>
                  <option value=">=">Не меньше</option>
                  <option value="<=">Не больше</option>
                </select>
                <input class="user-kpi-value" type="text" maxlength="200" placeholder="Значение"
                  value="<?= htmlspecialchars((string) ($condition['value'] ?? '')) ?>" required>
                <button type="button" class="btn btn-ghost btn-sm user-kpi-remove" aria-label="Удалить условие">×</button>
              </div>
            <?php endforeach; ?>
          </div>
          <div><button type="button" class="btn btn-ghost btn-sm" id="userKpiAddCondition">Добавить условие</button>
          </div>
        </div>
      </section>
      <section class="ui-card user-kpi-section">
        <div class="ui-card-head">Шаблон детализации</div>
        <div class="ui-card-body">
          <div class="ui-field"><label for="userKpiDetailType">Вариант детализации</label><select
              id="userKpiDetailType">
              <?php foreach ($groupingTemplates as $code => $label): ?>
                <option value="<?= htmlspecialchars($code) ?>" <?= $detailChoice === $code ? 'selected' : '' ?>>
                  <?= htmlspecialchars($label) ?></option><?php endforeach; ?>
            </select></div>
          
        </div>
      </section>
      <p id="userKpiError" class="user-kpi-error" role="alert"></p>
      <div class="ui-form-actions">
        <?php if ($edited): ?><button type="button" class="btn btn-ghost user-kpi-delete" id="userKpiDelete">Удалить KPI</button><?php endif; ?>
        <div class="user-kpi-main-actions"><button type="button" class="btn btn-ghost" id="userKpiCancel">Отмена</button><button
            type="button" class="btn btn-primary" id="userKpiSave">Сохранить KPI</button></div>
      </div>
    </main>
  </div>
  <script>
    window.APP_BASE = <?= json_encode($basePath, JSON_UNESCAPED_UNICODE) ?>;
    window.APP_CSRF = <?= json_encode($csrf, JSON_UNESCAPED_UNICODE) ?>;
    window.USER_KPI_FORM = <?= json_encode(['type' => $type, 'id' => (int) ($edited['id'] ?? 0), 'admin' => !empty($adminEdit), 'modal' => !empty($modalForm), 'canSetGlobal' => !empty($canSetGlobal), 'fields' => $fields], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    window.USER_KPI_CONFIG = <?= json_encode($config, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  </script>
  <script src="<?= htmlspecialchars($basePath) ?>/assets/js/jquery/jquery-3.7.1.min.js"></script>
  <script
    src="<?= htmlspecialchars($basePath) ?>/assets/js/user-kpi-form.js?v=<?= filemtime(__DIR__ . '/../public/assets/js/user-kpi-form.js') ?>"></script>
</body>

</html>
