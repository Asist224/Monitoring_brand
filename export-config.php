<?php
// export-config.php - Экспорт полной конфигурации для скачивания
header('Content-Type: application/javascript; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Проверка метода запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '// Метод не разрешен';
    exit;
}

// Получаем данные конфигурации
$input = file_get_contents('php://input');
$config = json_decode($input, true);

if (!$config) {
    http_response_code(400);
    echo '// Неверный формат данных';
    exit;
}

// Читаем блоки из существующего файла или используем стандартные
$translationsBlock = '';
$configManagerBlock = '';

// Пытаемся прочитать существующий файл конфигурации
if (file_exists('monitoring-config.js')) {
    $existingContent = file_get_contents('monitoring-config.js');
    
    // Извлекаем блок переводов
    if (preg_match('/const MonitoringTranslations = \{[\s\S]*?\n\};/m', $existingContent, $translationsMatch)) {
        $translationsBlock = $translationsMatch[0];
    }
    
    // Извлекаем блок ConfigManager
    if (preg_match('/const MonitoringConfigManager = \{[\s\S]*?\n\};/m', $existingContent, $managerMatch)) {
        $configManagerBlock = $managerMatch[0];
    }
}

// Если не нашли переводы, пытаемся загрузить из отдельного файла
if (empty($translationsBlock) && file_exists('monitoring-translations.js')) {
    $translationsContent = file_get_contents('monitoring-translations.js');
    if (preg_match('/const MonitoringTranslations = \{[\s\S]*?\n\};/m', $translationsContent, $translationsMatch)) {
        $translationsBlock = $translationsMatch[0];
    }
}

// Если все еще нет переводов, используем минимальный блок
if (empty($translationsBlock)) {
    $translationsBlock = 'const MonitoringTranslations = {
    ru: {
        header: { title: "Панель Мониторинга", live: "Live", settings: "Настройки" },
        filters: { period: { label: "Период" } },
        stats: { totalUsers: { title: "Всего пользователей" } },
        charts: { activity: { title: "Активность по времени" } },
        table: { title: "Пользователи" }
    },
    en: {
        header: { title: "Monitoring Dashboard", live: "Live", settings: "Settings" }
    }
};';
}

// Если нет ConfigManager, используем стандартный
if (empty($configManagerBlock)) {
    $configManagerBlock = 'const MonitoringConfigManager = {
    getLanguage() {
        return MonitoringConfig.language || "ru";
    },
    setLanguage(lang) {
        if (MonitoringTranslations[lang]) {
            MonitoringConfig.language = lang;
            return true;
        }
        return false;
    },
    getTranslation(path) {
        const lang = this.getLanguage();
        const translations = MonitoringTranslations[lang] || MonitoringTranslations.ru;
        const keys = path.split(".");
        let result = translations;
        for (const key of keys) {
            result = result[key];
            if (!result) return path;
        }
        return result;
    },
    exportConfig() {
        return JSON.stringify(MonitoringConfig, null, 2);
    }
};';
}

// Генерируем полный файл конфигурации
$output = "// monitoring-config.js - Конфигурация системы мониторинга
// =====================================================================================
// СИСТЕМА КОНФИГУРАЦИИ ДЛЯ МОНИТОРИНГА ЧАТОВ
// =====================================================================================
// Экспортировано: " . date('d.m.Y H:i:s') . "

// ===============================================
// ГЛАВНЫЕ НАСТРОЙКИ МОНИТОРИНГА
// ===============================================
const MonitoringConfig = " . json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ";

// ===============================================
// ПЕРЕВОДЫ ИНТЕРФЕЙСА
// ===============================================
" . $translationsBlock . "

// ===============================================
// ФУНКЦИИ РАБОТЫ С КОНФИГУРАЦИЕЙ
// ===============================================
" . $configManagerBlock . "

// Экспорт в глобальную область
window.MonitoringConfig = MonitoringConfig;
window.MonitoringTranslations = MonitoringTranslations;
window.MonitoringConfigManager = MonitoringConfigManager;

console.log('✅ Конфигурация мониторинга загружена. Язык:', MonitoringConfig.language);";

// Отправляем содержимое
echo $output;
?>