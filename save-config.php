<?php
// save-config.php - Сохранение конфигурации мониторинга
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Проверка метода запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Метод не разрешен']);
    exit;
}

// Получаем данные
$input = file_get_contents('php://input');
$newConfig = json_decode($input, true);

if (!$newConfig) {
    http_response_code(400);
    echo json_encode(['error' => 'Неверный формат данных']);
    exit;
}

// Создаем директорию для резервных копий, если её нет
$backupDir = 'backups/';
if (!file_exists($backupDir)) {
    if (!mkdir($backupDir, 0755, true)) {
        error_log("Не удалось создать директорию backups");
    }
}

// Создаем резервную копию только если файл существует
if (file_exists('monitoring-config.js')) {
    $backupName = 'monitoring-config.backup.' . date('Y-m-d-H-i-s') . '.js';
    $backupPath = $backupDir . $backupName;
    
    if (copy('monitoring-config.js', $backupPath)) {
        // Удаляем старые резервные копии, оставляем только последние 3
        $maxBackups = 3; // Количество резервных копий для хранения
        
        // Получаем список всех резервных копий
        $backupFiles = glob($backupDir . 'monitoring-config.backup.*.js');
        
        // Сортируем по времени создания (новые первые)
        usort($backupFiles, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        // Удаляем старые копии
        if (count($backupFiles) > $maxBackups) {
            $filesToDelete = array_slice($backupFiles, $maxBackups);
            foreach ($filesToDelete as $file) {
                if (unlink($file)) {
                    error_log("Удалена старая резервная копия: " . basename($file));
                }
            }
        }
        
        error_log("Создана резервная копия: " . $backupName . ". Всего копий: " . min(count($backupFiles), $maxBackups));
    } else {
        error_log("Не удалось создать резервную копию");
    }
}

// Читаем оригинальный файл
$originalFile = 'monitoring-config.js';
$translationsBlock = '';
$configManagerBlock = '';

if (file_exists($originalFile)) {
    $originalContent = file_get_contents($originalFile);
    
    // Извлекаем блок переводов
    if (preg_match('/const MonitoringTranslations = \{[\s\S]*?\n\};/m', $originalContent, $translationsMatch)) {
        $translationsBlock = $translationsMatch[0];
    }
    
    // Извлекаем блок ConfigManager
    if (preg_match('/const MonitoringConfigManager = \{[\s\S]*?\n\};/m', $originalContent, $managerMatch)) {
        $configManagerBlock = $managerMatch[0];
    }
}

// Если не нашли переводы, используем полный блок из оригинального файла
if (empty($translationsBlock)) {
    $translationsBlock = file_get_contents('monitoring-translations.js');
    if (!$translationsBlock) {
        // Минимальный блок переводов
        $translationsBlock = 'const MonitoringTranslations = {
    ru: {
        header: { title: "Панель Мониторинга", live: "Live", settings: "Настройки" },
        filters: {
            period: { label: "Период", options: { "1h": "Последний час", "24h": "Последние 24 часа", "7d": "Последняя неделя", "30d": "Последний месяц", "custom": "Произвольный период" } },
            configuration: { label: "Конфигурация", all: "Все конфигурации" },
            platform: { label: "Платформа", all: "Все платформы" },
            search: { placeholder: "Поиск по IP, стране, городу..." },
            buttons: { refresh: "Обновить", analyzeAll: "Анализ всех", analyzeByLanguage: "Анализ по языку", analyzeLabel: "Анализ диалогов" }
        },
        stats: {
            totalUsers: { title: "Всего пользователей", trend: "за период" },
            activeSessions: { title: "Активные сессии", trend: "Стабильно" },
            avgSessionTime: { title: "Среднее время сессии", trend: "за период" },
            totalMessages: { title: "Всего сообщений", trend: "за период" }
        },
        charts: {
            activity: { title: "Активность по времени", refresh: "Обновить график", yAxis: "Количество событий", currentHour: "Текущий час", events: "События" },
            geography: { title: "География пользователей", refresh: "Обновить график", noData: "Нет данных для отображения" },
            platforms: { title: "Распределение по платформам", refresh: "Обновить график", noData: "Нет данных для отображения" }
        },
        table: {
            title: "Пользователи", export: "Экспорт", noData: "Нет данных для отображения", loading: "Загрузка данных...",
            columns: { sessionId: "Session ID", ipAddress: "IP адрес", country: "Страна", city: "Город", platform: "Платформа", configuration: "Конфигурация", startTime: "Время начала", duration: "Длительность", messages: "Сообщений", satisfaction: "Удовлетворенность", status: "Статус", actions: "Действия" },
            status: { active: "Активен", inactive: "Неактивен" },
            actions: { viewDialog: "Диалог", analyze: "Анализ", viewAnalysis: "Результат" }
        },
        pagination: { previous: "Назад", next: "Вперед" },
        dialogs: {
            dialog: { title: "Диалог", loading: "Загрузка диалога...", notFound: "Диалог не найден", error: "Ошибка загрузки диалога", user: "Пользователь", bot: "Бот" },
            analysis: { title: "Анализ диалога", loading: "Анализируем диалог", error: "Ошибка при анализе диалога", analyzingAll: "Анализируем все диалоги...", timeNotice: "Это может занять несколько минут" },
            language: { title: "Выберите язык для анализа", russian: "Русский", english: "Английский" },
            settings: {
                title: "Настройки мониторинга",
                autoAnalysis: { title: "Автоматический анализ диалогов", enable: "Включить автоанализ", delay: "Задержка после неактивности", minutes: "минут", serverMode: "Серверный режим", enabledNotice: "Серверный автоанализ включен (проверка каждые 5 минут)", disabledNotice: "Серверный автоанализ выключен" },
                dbCleanup: { title: "Автоматическая очистка базы данных", active: "Активна (ежедневно в 3:00)", monitoringData: "Хранить данные мониторинга", analysisResults: "Хранить результаты анализов", days: "дней" },
                buttons: { save: "Сохранить все настройки", cancel: "Отмена" }
            }
        },
        formatting: { today: "Сегодня", yesterday: "Вчера", seconds: "сек", minutes: "мин", hours: "ч", unknown: "Н/Д", guest: "Гость" },
        errors: { loadData: "Не удалось загрузить данные. Проверьте подключение.", connectionError: "Ошибка подключения к серверу" },
        analysis: {
            emotionalTone: { title: "Эмоциональный тон диалога", overall: "Общий тон", satisfaction: "Удовлетворенность клиента", positive: "Позитивный", negative: "Негативный", neutral: "Нейтральный" },
            needs: { title: "Выявленные потребности" },
            missedOpportunities: { title: "Упущенные возможности" },
            recommendations: { title: "Рекомендации по улучшению" },
            statistics: { title: "Общая статистика", totalDialogs: "Проанализировано диалогов", avgSatisfaction: "Средняя удовлетворенность", resolved: "Решено вопросов" }
        },
        notifications: { settingsSaved: "Все настройки успешно сохранены", settingsError: "Ошибка сохранения настроек", periodWarning: "Период хранения мониторинга должен быть от 7 до 365 дней", analysisWarning: "Период хранения анализов должен быть от 30 до 365 дней" }
    },
    en: {
        header: { title: "Monitoring Dashboard", live: "Live", settings: "Settings" }
    }
};';
    }
}

// Если не нашли ConfigManager, используем стандартный
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
    getEnabledConfigurations() {
        const enabled = {};
        Object.keys(MonitoringConfig.availableConfigurations).forEach(key => {
            const config = MonitoringConfig.availableConfigurations[key];
            if (config.enabled) {
                enabled[key] = config;
            }
        });
        return enabled;
    },
    getEnabledPlatforms() {
        const enabled = {};
        Object.keys(MonitoringConfig.availablePlatforms).forEach(key => {
            const platform = MonitoringConfig.availablePlatforms[key];
            if (platform.enabled) {
                enabled[key] = platform;
            }
        });
        return enabled;
    },
    getEnabledAnalysisLanguages() {
        const enabled = {};
        Object.keys(MonitoringConfig.availableAnalysisLanguages).forEach(key => {
            const lang = MonitoringConfig.availableAnalysisLanguages[key];
            if (lang.enabled) {
                enabled[key] = lang;
            }
        });
        return enabled;
    },
    isVisible(section, element) {
        return MonitoringConfig.display[section] && MonitoringConfig.display[section][element];
    },
    getDisplaySettings() {
        return MonitoringConfig.display;
    },
    getTechnicalSettings() {
        return MonitoringConfig.technical;
    },
    exportConfig() {
        return JSON.stringify(MonitoringConfig, null, 2);
    },
    importConfig(configString) {
        try {
            const newConfig = JSON.parse(configString);
            Object.assign(MonitoringConfig, newConfig);
            console.log("✅ Конфигурация импортирована");
            return true;
        } catch (error) {
            console.error("❌ Ошибка импорта конфигурации:", error);
            return false;
        }
    }
};';
}

// Генерируем новое содержимое файла
$newContent = "// monitoring-config.js - Конфигурация системы мониторинга
// =====================================================================================
// СИСТЕМА КОНФИГУРАЦИИ ДЛЯ МОНИТОРИНГА ЧАТОВ
// =====================================================================================
// Автоматически сгенерировано: " . date('d.m.Y H:i:s') . "

// ===============================================
// ГЛАВНЫЕ НАСТРОЙКИ МОНИТОРИНГА
// ===============================================
const MonitoringConfig = " . json_encode($newConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ";

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

// Сохраняем новый файл
if (file_put_contents('monitoring-config.js', $newContent)) {
    // Проверяем, что файл сохранился и читается
    if (file_exists('monitoring-config.js') && filesize('monitoring-config.js') > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Конфигурация успешно сохранена',
            'backup' => isset($backupName) ? $backupName : null,
            'timestamp' => date('Y-m-d H:i:s'),
            'fileSize' => filesize('monitoring-config.js')
        ], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Файл сохранен, но пустой или недоступен']);
    }
} else {
    $error = error_get_last();
    http_response_code(500);
    echo json_encode([
        'error' => 'Ошибка при сохранении файла. Проверьте права доступа.',
        'details' => $error ? $error['message'] : 'Неизвестная ошибка'
    ]);
}
// Очищаем кеш PHP если доступен
if (function_exists('opcache_reset')) {
    opcache_reset();
}
if (function_exists('opcache_invalidate')) {
    opcache_invalidate('monitoring-config.js', true);
    opcache_invalidate('cache-control.php', true);
}

// Очищаем системный кеш файлов
clearstatcache(true);
?>