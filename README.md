# BookShelf

書籍の登録・レビュー・お気に入り・レビューへのいいねなどを管理する、Laravel製の書籍レビューアプリです。

## 使用技術

- PHP 8.5
- Laravel 10.50.2
- MySQL 8.4
- Laravel Sail
- Vite 5
- Tailwind CSS 3
- Alpine.js

## ER図

```mermaid
erDiagram
    USERS {
        bigint id PK
        varchar name
        varchar email UK
        timestamp email_verified_at
        varchar password
        varchar remember_token
        timestamp created_at
        timestamp updated_at
    }

    BOOKS {
        bigint id PK
        bigint user_id FK
        varchar title
        varchar author
        varchar isbn UK
        date published_date
        text description
        varchar image_url
        timestamp created_at
        timestamp updated_at
    }

    GENRES {
        bigint id PK
        varchar name UK
        timestamp created_at
        timestamp updated_at
    }

    REVIEWS {
        bigint id PK
        bigint user_id FK
        bigint book_id FK
        tinyint rating
        text comment
        timestamp created_at
        timestamp updated_at
    }

    BOOK_GENRE {
        bigint id PK
        bigint book_id FK
        bigint genre_id FK
        timestamp created_at
        timestamp updated_at
    }

    FAVORITES {
        bigint id PK
        bigint user_id FK
        bigint book_id FK
        timestamp created_at
        timestamp updated_at
    }

    REVIEW_LIKES {
        bigint id PK
        bigint user_id FK
        bigint review_id FK
        timestamp created_at
        timestamp updated_at
    }

    USERS ||--o{ BOOKS : registers
    USERS ||--o{ REVIEWS : posts
    BOOKS ||--o{ REVIEWS : receives
    BOOKS ||--o{ BOOK_GENRE : has
    GENRES ||--o{ BOOK_GENRE : has
    USERS ||--o{ FAVORITES : adds
    BOOKS ||--o{ FAVORITES : receives
    USERS ||--o{ REVIEW_LIKES : adds
    REVIEWS ||--o{ REVIEW_LIKES : receives
```

### 複合UNIQUE制約

- `reviews`：`user_id + book_id`
- `book_genre`：`book_id + genre_id`
- `favorites`：`user_id + book_id`
- `review_likes`：`user_id + review_id`

## 環境構築

環境構築手順、初期データ、テストアカウントなどは、実装の進行に合わせて追記します。