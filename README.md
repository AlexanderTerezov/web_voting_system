# Web Voting System

Уеб приложение за организиране на органи, заседания, дневен ред и гласуване с автоматично генериране на протокол.

## Основни възможности
- Управление на органи (кворум, участници, роли)
- Създаване на заседания (дата/час, продължителност, периодичност)
- Дневен ред с точки и прикачени материали
- Гласуване: Да / Не / Въздържал се
- Генериране на протокол след приключване на заседание

## Технологии
- PHP + MySQL (PDO)
- Данните се пазят в MySQL база (виж променливите `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`)
- Прикачените файлове се пазят в `app/uploads/`

## Стартиране (локално)
1. От директорията на проекта:
   ```bash
   php -S localhost:8000 -t app
   ```
2. Отвори: `http://localhost:8000/index.php`

## Акаунти и роли
- Данните за потребителите са в MySQL базата.
- Паролите са хеширани. За вход можеш:
  - да създадеш нов акаунт през регистрацията, или
  - да смениш паролата с SQL заявка (напр. през `mysql`), като използваш нов хеш, генериран чрез:
    ```bash
    php -r "echo password_hash('НоваПарола', PASSWORD_DEFAULT);"
    ```
    и после:
    ```bash
    mysql -u root -p -D web_voting_system -e "UPDATE users SET password='ХЕШ' WHERE username='Admin';"
    ```
  - можеш и да добавиш като:

    ```bash
    mysql -u root -p -D web_voting_system -e "INSERT INTO users (username,email,password,role,created_at) VALUES ('admin2','admin2@example.com','<HASH>','Admin',NOW());"
    ```
  - инициализация на таблицата:
    ```bash
    CREATE DATABASE web_voting_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    ```


## Права за писане
За да работят регистрация, създаване на заседания и качване на файлове, уеб сървърът трябва да има write права върху:
- MySQL база данни (достъп през `DB_*` променливи)
- `app/uploads/`

## Структура
- `app/` — PHP страници (UI + логика)
- (MySQL база данни)
- `app/uploads/` — прикачени файлове


