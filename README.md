# КНУ Мерч · Коллекция «1925»

Одностраничный лендинг мерча Кыргызского национального университета им. Ж. Баласагына на **WordPress**. Сделан к 100-летию КНУ. Это учебный проект.

## Открыть сайт

**Демо (WordPress Playground):**
https://playground.wordpress.net/?mode=seamless&blueprint-url=https://raw.githubusercontent.com/mrbokg/KNU-WEB/main/blueprint.json

- Загрузка занимает 30–60 секунд: настоящий WordPress запускается прямо в браузере.
- Админка открывается ссылкой «Башкаруу панели» в футере или по адресу `/wp-admin/`. Вход выполняется автоматически.
- При каждом открытии данные сбрасываются, демо-заказы не сохраняются.

## Что умеет

- Языки: кыргызский (основной) и русский.
- Каталог: две двойки (худи + джоггеры, свитшот + джоггеры) в двух цветах (серый, тёмно-синий) и размерах XS–XXL, кепка, кружка, брелок.
- Гостевая корзина без регистрации.
- Оформление в 3 шага: корзина → контакты и способ получения (самовывоз в КНУ или доставка по адресу) → оплата (ELQR QR в демо-режиме или при получении).
- Заказы сохраняются в WordPress (раздел «Заказы» в админке) со статусами. Цены сервер пересчитывает сам. Форма защищена nonce и honeypot.
- Товары, цены и контакты редактируются в админке: «Товары» и «Внешний вид → Настройки КНУ».
- Юридические страницы по законам КР: оферта, политика конфиденциальности, обмен и возврат, доставка и оплата. Чек-лист соответствия: [`docs/legal-compliance-kr.md`](docs/legal-compliance-kr.md).

## Стек

WordPress 6, своя классическая тема `theme/knu-merch` (PHP 8, Custom Post Types, REST API, ванильный JS, CSS без фреймворков). Демо работает на WordPress Playground.

## Установка на обычный хостинг

1. Скачайте [`dist/knu-merch.zip`](dist/knu-merch.zip).
2. В WordPress откройте «Внешний вид → Темы → Добавить → Загрузить тему», выберите ZIP и нажмите «Активировать».
3. Тема сама создаст товары, главную и юридические страницы.

## Локальный запуск

```bash
MSYS_NO_PATHCONV=1 npx @wp-playground/cli@latest server --port=9400 --mount=./theme/knu-merch:/wordpress/wp-content/themes/knu-merch --blueprint=./blueprint.local.json --login
```

## Документы

- ТЗ: [`docs/superpowers/specs/2026-11-03-knu-merch-design.md`](docs/superpowers/specs/2026-11-03-knu-merch-design.md)
- План и контракты: [`docs/superpowers/plans/2026-11-03-knu-merch.md`](docs/superpowers/plans/2026-11-03-knu-merch.md)
- Презентация: [`presentation/knu-merch.pptx`](presentation/knu-merch.pptx), PDF: [`presentation/knu-merch.pdf`](presentation/knu-merch.pdf)
