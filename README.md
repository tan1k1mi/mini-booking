# Mini Booking

Небольшое приложение для бронирования одной переговорной по часовым слотам. Backend — Laravel, frontend — Vue 3 и PrimeVue, база данных — SQLite.

## Требования

- PHP 8.3+
- Composer 2
- Node.js 20.19+
- npm 10+
- расширение PHP `pdo_sqlite`

## Запуск backend

```bash
cd backend
composer install
cp .env.example .env
touch database/database.sqlite
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

API будет доступно по адресу `http://127.0.0.1:8000`.

## Запуск frontend

Во втором терминале:

```bash
cd frontend
npm install
npm run dev
```

Страница будет доступна по адресу `http://127.0.0.1:5173`. Vite проксирует запросы `/api` на Laravel по адресу `http://127.0.0.1:8000`.

## Тесты

```bash
cd backend
php artisan test
```

Тесты проверяют успешное создание бронирования и ответ `409 Conflict` на повторную попытку занять тот же слот.

## API

- `GET /api/slots` — список слотов по возрастанию времени;
- `POST /api/bookings` — создание бронирования;
- `DELETE /api/bookings/{id}` — отмена бронирования.

## Целостность данных

Отмена хранится в поле `bookings.cancelled_at`. Частичный уникальный индекс `bookings_one_active_per_slot` запрещает базе создать больше одной записи с одинаковым `slot_id` и пустым `cancelled_at`. Поэтому при одновременной вставке побеждает только один запрос, а нарушение уникальности преобразуется в `409 Conflict`.

Сидер использует `insertOrIgnore` вместе с уникальным индексом по `slots.start_at`. Повторный запуск за тот же период не меняет существующие слоты и бронирования, а запуск в другой день добавляет недостающие даты.

## Время

Расписание строится в часовом поясе `APP_TIMEZONE` из `.env` (`UTC` по умолчанию). В SQLite время слотов хранится как локальное время этого пояса. API возвращает ISO 8601 со смещением и отдельно передаёт название часового пояса; frontend использует его при форматировании дат и времени.

Незавершённых пунктов нет.
