# Архитектурный план: Wix to WordPress Post Migrator

## 1. Главная цель и архитектурный принцип

**Главная цель:**
Создать расширяемый, надёжный и безопасный плагин для WordPress, предназначенный для миграции статей блога из платформы **Wix** в **WordPress**, полностью соответствующий стандартам официального репозитория WordPress.org.

**Принцип расширяемости (Open-Closed Principle):**
> **Ядро плагина закрыто для модификаций, но открыто для добавления новых источников данных.**

Это гарантирует, что начав с **RSS**, мы сможем затем без переписывания ядра подключить **Wix REST API** или **Sitemap Scraper**. Новый источник подключается как отдельный изолированный адаптер (драйвер), не затрагивая механизм сохранения постов, загрузки картинок и работу с базой данных.

---

## 2. Парадигма программирования и архитектурные паттерны (ООП & SOLID)

Плагин пишется строго в парадигме **Объектно-Ориентированного Программирования (ООП)** с соблюдением принципов SOLID:

### 🧩 2.1. Используемые паттерны проектирования:
1. **Паттерн «Стратегия» (Strategy Pattern)**:
   * Интерфейс `W2W_Source_Adapter_Interface` задаёт единый контракт для источников данных.
   * Конкретные реализации: `W2W_Source_RSS`, `W2W_Source_API`, `W2W_Source_Sitemap`.
2. **Паттерн «Фабрика и Реестр» (Registry & Factory)**:
   * Класс `W2W_Source_Manager` хранит список доступных адаптеров и создаёт нужный экземпляр.
   * Поддержка фильтра `apply_filters( 'w2w_registered_sources', ... )` — открывает возможность для внешних аддонов.
3. **Паттерн «Объект передачи данных» (DTO - Data Transfer Object)**:
   * Класс `W2W_Post_DTO` с жестко типизированными свойствами (PHP 7.4+ type hinting).
   * Изолирует ядро плагина от формата входных данных (будь то XML, JSON из REST API или распарсенный HTML).
4. **Паттерн «Сервисный слой / Конвейер» (Pipeline / Single Responsibility)**:
   * Каждый этап обработки статьи изолирован в отдельный сервис:
     * `W2W_Content_Processor` — анализ HTML, выявление картинок `static.wixstatic.com`.
     * `W2W_Media_Downloader` — скачивание изображений и регистрация в Media Library WP.
     * `W2W_Taxonomy_Manager` — создание и связывание рубрик/тегов.
     * `W2W_Post_Writer` — создание записи в `wp_posts` с мета-данными.
5. **Встроенный логгер (`W2W_Logger`)**:
   * Детальное логирование этапов миграции в файл с возможностью просмотра и скачивания отчета пользователем.

---

## 3. Стандарты официального репозитория WordPress.org (WPCS)

Плагин разрабатывается с расчётом на **100% прохождение ревью команды WordPress.org Plugin Review Team**:

### 🔒 3.1. Безопасность (Security First)
1. **Защита от прямого доступа к файлам**:
   В начале каждого PHP-файла плагина обязательна проверка:
   ```php
   if ( ! defined( 'ABSPATH' ) ) {
       exit; // Exit if accessed directly.
   }
   ```
2. **Проверка прав доступа (Capability Checks)**:
   Действия в админке и AJAX-эндпоинтах обязаны проверять права:
   ```php
   if ( ! current_user_can( 'manage_options' ) ) {
       wp_send_json_error( array( 'message' => __( 'Forbidden', 'wix-to-wp-migrator' ) ), 403 );
   }
   ```
3. **Защита от CSRF (Nonces)**:
   Все запросы защищаются токенами безопасности (`wp_create_nonce` и `check_ajax_referer`).
4. **Очистка входящих данных (Sanitization)**:
   Очистка всех входящих параметров: `sanitize_text_field( wp_unslash( ... ) )`, `esc_url_raw()`, `absint()`.
5. **Экранирование вывода (Late Escaping)**:
   `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`.
6. **Безопасная работа с базой**:
   Использование core-функций WP (`wp_insert_post`, `update_post_meta`). Прямые запросы — только через `$wpdb->prepare()`.

### 🏷 3.2. Именование и префиксы
* Префикс всех функций, глобальных переменных и опций: `w2w_` или `wix_to_wp_`.
* Префикс всех классов: `W2W_`.
* Префикс констант: `W2W_` (например, `W2W_PLUGIN_DIR`, `W2W_PLUGIN_URL`, `W2W_VERSION`).
* Именование файлов: по стандарту WordPress (`class-{name}.php`, `interface-{name}.php`).

### 🌐 3.3. Интернационализация (i18n)
* Все строки обёрнуты в функции локализации: `__( 'Text', 'wix-to-wp-migrator' )`.
* Заголовок `Text Domain: wix-to-wp-migrator`, папка `languages/`.

### 📦 3.4. Ресурсы и сеть
* **Никаких сторонних CDN**: все CSS/JS ассеты поставляются локально в плагине.
* Сетевые вызовы — только через `wp_remote_get()` / `wp_remote_post()` с проверкой `is_wp_error()`.
* Файлы `readme.txt` (стандарт WP.org) и `uninstall.php` (очистка при удалении).
* Лицензия: GPLv2+.

---

## 4. Стратегия тестирования и генерации отчётов (QA Protocol)

Для гарантированной стабильности плагина разработка строится по принципу: **новая фича / итерация -> запуск тестов -> генерация отчёта в папку `tests/reports/`**.

### 🧪 4.1. Структура тестовой среды
В проекте создаётся выделенный каталог `tests/`:
```text
tests/
├── bootstrap.php                   # Окружение для запуска тестов (мок функций WP)
├── run-tests.php                   # Автономный тест-раннер (CLI/PHP)
├── unit/
│   ├── test-autoloader.php         # Тест корректности автозагрузки
│   ├── test-post-dto.php           # Тест валидации и маппинга DTO
│   ├── test-source-rss.php         # Тест парсинга реальных XML фидов Wix
│   ├── test-content-processor.php  # Тест извлечения и замены URL картинок wixstatic
│   ├── test-media-importer.php     # Тест логики загрузки медиа
│   └── test-post-importer.php      # Тест дедупликации и создания постов
└── reports/                        # Папка с авто-отчётами по каждой итерации
    ├── iteration-1-foundation.md
    ├── iteration-2-rss-mvp.md
    └── ...
```

### 📋 4.2. Формат отчётов о тестировании (`tests/reports/iteration-X-*.md`)
После реализации каждого этапа формируется подробный Markdown-отчёт, содержащий:
1. **Метаданные**: Дата, время, версия плагина, итерация.
2. **Список проверенных сценариев (Test Cases)**: Успешные и краевые случаи (edge-cases).
3. **Результаты (Pass/Fail)**: Количество выполненных тестов, время выполнения.
4. **Обнаруженные и устраненные баги**: Описание того, что было скорректировано в процессе.
5. **Готовность к переходу на следующий этап**: Статус готовности кода.

---

## 5. Архитектура: 4 независимых слоя

```mermaid
graph TD
    subgraph Layer1[Слой 1: Пользовательский интерфейс и AJAX]
        UI[Админ-панель плагина WP] --> AjaxController[AJAX Runner / Очередь задач]
    end

    subgraph Layer2[Слой 2: Реестр и адаптеры источников (Strategy/Factory)]
        AjaxController --> SourceManager[Менеджер источников: W2W_Source_Manager]
        SourceManager --> RSSAdapter[RSS Feed Adapter - Этап 2]
        SourceManager -.-> APIAdapter[Wix REST API Adapter - Этап 4]
        SourceManager -.-> ScraperAdapter[Sitemap / HTML Scraper - Этап 5]
    end

    subgraph DTO[Единый стандарт данных]
        RSSAdapter --> PostDTO[Normalized W2W_Post_DTO]
        APIAdapter -.-> PostDTO
        ScraperAdapter -.-> PostDTO
    end

    subgraph Layer3[Слой 3: Сервисный конвейер импорта (Независим от источника!)]
        PostDTO --> Coordinator[W2W_Migration_Coordinator]
        Coordinator --> ContentProcessor[W2W_Content_Processor]
        Coordinator --> MediaImporter[W2W_Media_Downloader]
        Coordinator --> TaxonomyHandler[W2W_Taxonomy_Manager]
        Coordinator --> PostWriter[W2W_Post_Writer + Дедупликация]
    end

    subgraph Layer4[Слой 4: База данных и хранилище WP]
        PostWriter --> WPDB[(База данных WordPress)]
        MediaImporter --> WPUploads[(Медиабиблиотека WP: /uploads/)]
    end
```

---

## 6. Поэтапный план разработки с контролем качества

### 🔹 Этап 1: Архитектурный фундамент, ядро импорта и тестовая база
> **Результат этапа:** Каркас плагина по стандартам WPCS, модель DTO, контракт адаптеров, сервисный пайплайн импорта и среда тестирования.
1. Создание главного файла `wix-to-wp-migrator.php` (заголовки WP, константы `W2W_`).
2. Автозагрузчик классов по стандарту WordPress `class-autoloader.php`.
3. Контракт источников `interface-source-adapter.php` (`W2W_Source_Adapter_Interface`).
4. Реестр источников `class-source-manager.php`.
5. Класс модели данных `class-post-dto.php` со строгой типизацией.
6. Сервисный конвейер:
   * `class-content-processor.php`: парсинг ссылок `static.wixstatic.com`.
   * `class-media-downloader.php`: загрузка картинок и привязка к посту.
   * `class-taxonomy-manager.php`: рубрики и метки.
   * `class-post-writer.php`: сохранение поста, защита от дублей по мета-ключу `_w2w_original_wix_id`.
7. Логгер `class-logger.php`.
8. Настройка окружения тестирования: `tests/bootstrap.php` и `tests/run-tests.php`.
9. **Тестирование Этапа 1**: Написание Unit-тестов для DTO, процессора контента и запись отчета в `tests/reports/iteration-1-foundation.md`.

---

### 🔹 Этап 2: Адаптер RSS-ленты (MVP)
> **Результат этапа:** Полностью готовый первый рабочий процесс миграции статей через RSS с предпросмотром и AJAX-пакетами.
1. Разработка `class-source-rss.php` (`W2W_Source_RSS`):
   * Запрос к URL через `wp_remote_get()`.
   * Парсинг XML через `simplexml_load_string()` / `DOMDocument`.
   * Извлечение заголовка, контента (`content:encoded`), ссылки, даты, категорий, картинок (`enclosure` или `<img>`).
   * Маппинг в `W2W_Post_DTO`.
2. Регистрация RSS-адаптера в `Source_Manager`.
3. Страница управления в админке WordPress:
   * Меню **Инструменты → Wix to WordPress**.
   * Локальные CSS/JS ассеты без CDN.
   * Ввод URL RSS / Wix блога, кнопка «Предпросмотр постов».
   * Таблица постов с чекбоксами.
4. AJAX-обработчик пакетного импорта (`class-ajax-handler.php`):
   * Проверка nonce и capability `manage_options`.
   * Пакетный импорт по 2-3 поста за запрос (защита от таймаута PHP).
   * Прогресс-бар и логгер в реальном времени.
5. **Тестирование Этапа 2**: Тесты парсинга RSS, генерация отчета в `tests/reports/iteration-2-rss-mvp.md`.

---

### 🔹 Этап 3: Полировка первой версии и релизная готовность v1.0
> **Результат этапа:** Стабильный, оттестированный релиз для WordPress.org.
1. Тестирование на реальных данных Wix RSS (кодировки, спецсимволы, даты).
2. Обработка краевых случаев:
   * Недоступные картинки (graceful fallback).
   * Превышение таймаутов, докачка прерванного импорта.
3. Подготовка файла `readme.txt` и `uninstall.php`.
4. **Тестирование Этапа 3**: Итоговый регрессионный тест, отчёт в `tests/reports/iteration-3-release-v1.md`.

---

### 🔹 Этап 4: Подключение Wix REST API (Расширение без изменения ядра!)
> **Результат этапа:** Импорт через официальный API Wix (черновики, SEO-поля, точные структуры).
1. Создание класса `class-source-api.php` (`W2W_Source_API`).
2. Поля авторизации в UI (API Key, Account ID).
3. Запросы к Wix Blog API v3 через `wp_remote_get()`.
4. Конвертация Wix Ricos в HTML/Gutenberg блоки.
5. Маппинг в тот же `W2W_Post_DTO` — ядро импорта остаётся нетронутым!
6. **Тестирование Этапа 4**: Тесты API-адаптера, отчёт в `tests/reports/iteration-4-api-adapter.md`.

---

### 🔹 Этап 5: Подключение Sitemap / Web Scraper (Опционально)
> **Результат этапа:** Импорт для сайтов, где RSS выключен и нет доступа к API Wix.
1. Создание `class-source-sitemap.php`.
2. Парсинг `sitemap.xml` и HTML страниц постов.
3. Маппинг в `W2W_Post_DTO`.
4. **Тестирование Этапа 5**: Отчёт в `tests/reports/iteration-5-scraper.md`.

---

## 7. Итоговая файловая структура плагина

```text
wp-plugin-dev/
├── PROJECT_PLAN.md                  # Архитектурный план, ООП-дизайн, WPCS и QA-протокол
├── readme.txt                       # Официальный файл описания для WordPress.org
├── uninstall.php                    # Скрипт очистки при удалении плагина
├── wix-to-wp-migrator.php           # Точка входа плагина
│
├── languages/                       # Файлы локализации (.pot, .po, .mo)
│   └── wix-to-wp-migrator.pot
│
├── includes/
│   ├── class-plugin.php             # Главный оркестратор плагина (Singleton)
│   ├── class-autoloader.php         # Автозагрузчик классов
│   │
│   ├── dto/
│   │   └── class-post-dto.php       # Объект данных W2W_Post_DTO (типизированный)
│   │
│   ├── interfaces/
│   │   └── interface-source-adapter.php # Контракт W2W_Source_Adapter_Interface
│   │
│   ├── sources/
│   │   ├── class-source-manager.php # Реестр и фабрика источников
│   │   ├── class-source-rss.php     # Адаптер RSS (Этап 2)
│   │   ├── class-source-api.php     # Адаптер REST API (Этап 4)
│   │   └── class-source-sitemap.php # Адаптер Scraper (Этап 5)
│   │
│   ├── engine/
│   │   ├── class-migration-coordinator.php # Координатор конвейера импорта
│   │   ├── class-content-processor.php     # Парсинг картинок wixstatic
│   │   ├── class-media-downloader.php      # Загрузка картинок в wp-content/uploads/
│   │   ├── class-taxonomy-manager.php      # Рубрики и метки
│   │   └── class-post-writer.php           # Запись постов в WP и дедупликация
│   │
│   ├── utils/
│   │   └── class-logger.php         # Встроенная система логирования
│   │
│   └── ajax/
│       └── class-ajax-handler.php   # Контроллер AJAX-очереди с nonces
│
├── admin/
    ├── class-admin.php              # Меню админки, регистрация страниц и ассетов
    ├── views/
    │   ├── main-page.php            # Основной контейнер с вкладками
    │   ├── tab-rss.php              # Форма настроек RSS
    │   ├── preview-table.php        # Таблица предпросмотра статей
    │   └── progress-bar.php         # Прогресс-бар и лог миграции
    ├── css/
    │   └── admin.css                # Локальные стили без внешних CDN
    └── js/
        └── admin.js                 # Клиентский оркестратор очереди AJAX
│
└── tests/                           # Каталог тестирования и отчётов
    ├── bootstrap.php                # Тестовое окружение (WP environment mocks)
    ├── run-tests.php                # Тест-раннер
    ├── unit/                        # Модульные тесты
    │   ├── test-post-dto.php
    │   ├── test-content-processor.php
    │   ├── test-source-rss.php
    │   └── test-post-writer.php
    └── reports/                     # Отчёты тестирования по каждой итерации
        ├── iteration-1-foundation.md
        ├── iteration-2-rss-mvp.md
        └── ...
```
