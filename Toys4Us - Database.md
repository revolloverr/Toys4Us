# Toys4Us — Database Schema & Seed Data

## Overview

The database is named `toys4us` and uses MySQL 8.0+ with `utf8mb4` charset. All tables use `InnoDB` for foreign key support.

---

## Tables

### `user`
Stores registered user accounts.

| Column | Type | Notes |
|--------|------|-------|
| `id` | int | Primary key, auto increment |
| `email` | varchar(255) | Unique |
| `name` | varchar(100) | |
| `phone` | varchar(20) | Optional |
| `password` | varchar(255) | bcrypt hashed |
| `role` | varchar(20) | `user` or `admin`, default `user` |
| `totp_secret` | varchar(64) | Null if 2FA not enabled |
| `created_at` | timestamp | Auto |
| `deleted_at` | timestamp | Null unless scheduled for deletion |

---

### `product`
Toys and products available in the store.

| Column | Type | Notes |
|--------|------|-------|
| `id` | int | Primary key, auto increment |
| `name` | varchar(255) | |
| `description` | text | |
| `price` | decimal(10,2) | |
| `rating` | float | Default `0` |
| `category_id` | int | FK → `category.id`, nullable |
| `stock` | int | Default `0` |
| `is_active` | tinyint(1) | Default `1` |
| `image` | varchar(255) | URL |
| `created_at` | timestamp | Auto |

**Seed data:** 9 products including LEGO sets, RC cars, puzzles, plush toys, and more.

---

### `category`
Product categories.

| Column | Type | Notes |
|--------|------|-------|
| `id` | int | Primary key, auto increment |
| `name` | varchar(100) | |
| `slug` | varchar(100) | Unique, URL-friendly |
| `image` | varchar(255) | Optional |
| `created_at` | timestamp | Auto |

**Seed data:** Plush Toys, Action Figures, Board Games, Outdoor, Educational.

---

### `plushbase`
Base plush animals available for the Build-a-Plush feature.

| Column | Type | Notes |
|--------|------|-------|
| `id` | int | Primary key, auto increment |
| `name` | varchar(100) | e.g. "Brown Bear" |
| `species` | varchar(50) | e.g. `bear`, `bunny` |
| `color` | varchar(50) | e.g. `brown`, `pink` |
| `image_path` | varchar(255) | Relative path to asset |
| `base_price` | decimal(10,2) | |
| `is_active` | tinyint(1) | Default `1` |

**Seed data:** 16 bases — 9 bear colors (brown, black, blue, green, orange, pink, purple, white, yellow) and 7 bunny colors (black, brown, green, orange, pink, purple, white). All priced at $14.99.

---

### `plushaccessory`
Accessories that can be added to a custom plush.

| Column | Type | Notes |
|--------|------|-------|
| `id` | int | Primary key, auto increment |
| `name` | varchar(100) | |
| `image_path` | varchar(255) | Relative path to asset |
| `price` | decimal(10,2) | Default `0.00` |
| `category` | varchar(50) | `facewear`, `bodywear`, or `shoes` |
| `is_active` | tinyint(1) | Default `1` |

**Seed data:** Bow (bodywear), Glasses (facewear), Hat (facewear), Pants (shoes), Pink Bow (facewear). All priced at $2.99.

---

### `customplush`
A user-configured custom plush toy.

| Column | Type | Notes |
|--------|------|-------|
| `id` | int | Primary key, auto increment |
| `user_id` | int | FK → `user.id`, nullable (guest) |
| `base_id` | int | FK → `plushbase.id` |
| `name` | varchar(100) | User-given name |
| `voice_message_path` | varchar(255) | Path to TTS audio, optional |
| `total_price` | decimal(10,2) | Calculated total |
| `created_at` | timestamp | Auto |

---

### `customplushaccessory`
Junction table linking a custom plush to its selected accessories.

| Column | Type | Notes |
|--------|------|-------|
| `id` | int | Primary key, auto increment |
| `customplush_id` | int | FK → `customplush.id` |
| `accessory_id` | int | FK → `plushaccessory.id` |

---

### `cart_item`
Persisted cart items for logged-in users.

| Column | Type | Notes |
|--------|------|-------|
| `id` | int | Primary key, auto increment |
| `user_id` | int | FK → `user.id`, cascades on delete |
| `product_id` | int | FK → `product.id`, cascades on delete |
| `quantity` | int | Default `1` |
| `created_at` | timestamp | Auto |

Unique constraint on `(user_id, product_id)` — one row per product per user.

---

### `order`
A completed purchase.

| Column | Type | Notes |
|--------|------|-------|
| `id` | int | Primary key, auto increment |
| `user_id` | int | FK → `user.id`, nullable |
| `total` | decimal(10,2) | |
| `status` | varchar(50) | Default `pending`, set to `paid` after Stripe success |
| `address` | text | Optional shipping address |
| `city` | varchar(100) | |
| `province` | varchar(50) | |
| `postal_code` | varchar(20) | |
| `stripe_payment_id` | varchar(255) | Stripe `payment_intent` ID |
| `created_at` | timestamp | Auto |

---

### `order_item`
Individual line items belonging to an order.

| Column | Type | Notes |
|--------|------|-------|
| `id` | int | Primary key, auto increment |
| `order_id` | int | FK → `order.id`, cascades on delete |
| `product_id` | int | FK → `product.id`, nullable (custom plush orders won't have this) |
| `custom_plush_id` | int | FK → `customplush.id`, nullable |
| `quantity` | int | |
| `price` | decimal(10,2) | Price at time of purchase |

---

### `review`
User reviews for products.
We wanted to implement this, but we had no time. This is a future feature to be implemented. We still have it in the database in case we need it later.
| Column | Type | Notes |
|--------|------|-------|
| `id` | int | Primary key, auto increment |
| `product_id` | int | FK → `product.id`, cascades on delete |
| `user_id` | int | FK → `user.id`, set null on delete |
| `title` | varchar(100) | |
| `rating` | int | 1–5 |
| `comment` | text | |
| `created_at` | timestamp | Auto |

No seed data — reviews are user-generated.

---

### `address`
Saved shipping addresses for users.

| Column | Type | Notes |
|--------|------|-------|
| `id` | int | Primary key, auto increment |
| `user_id` | int | |
| `name` | varchar(255) | Recipient name |
| `address` | varchar(255) | Street address |
| `city` | varchar(100) | |
| `province` | varchar(100) | |
| `postal_code` | varchar(20) | |
| `created_at` | timestamp | Auto |

---

## Foreign Key Summary

| Table | Column | References |
|-------|--------|------------|
| `product` | `category_id` | `category.id` → SET NULL |
| `cart_item` | `user_id` | `user.id` → CASCADE |
| `cart_item` | `product_id` | `product.id` → CASCADE |
| `customplush` | `user_id` | `user.id` → SET NULL |
| `customplush` | `base_id` | `plushbase.id` |
| `customplushaccessory` | `customplush_id` | `customplush.id` |
| `customplushaccessory` | `accessory_id` | `plushaccessory.id` |
| `order` | `user_id` | `user.id` → SET NULL |
| `order_item` | `order_id` | `order.id` → CASCADE |
| `order_item` | `product_id` | `product.id` |
| `order_item` | `custom_plush_id` | `customplush.id` → SET NULL |
| `review` | `product_id` | `product.id` → CASCADE |
| `review` | `user_id` | `user.id` → SET NULL |
