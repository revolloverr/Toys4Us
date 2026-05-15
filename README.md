
# Toys4Us Project

Toys4Us is an e-commerce website where you can buy toys, build your own customized plush, and find great products for great prices. Users can navigate between different categories of items, set up an account, and buy items with the help of Stripe for payment processing.

## Features

-   **Product Catalog** -- Browse for toys in many different categories.
-   **Build-a-Plush** -- Make your own custom plush using the different bases and accessories provided to you in the `Build-A-Plush` tab.
-   **Shopping Cart** -- Add products to your own shopping cart, make sure you're logged in for it to save for your next visit.
-   **Checkout** -- Enter a shipping address and pay securely via Stripe
-   **Order History** -- View your past orders and their status from your profile page
-   **User Accounts** -- Register, log in, manage your profile, and change your password
-   **Two-Factor Authentication (TOTP)** -- Optional, Enable in profile after login
-   **Bilingual Support** -- Full English and French language support
-   **Admin Dashboard** -- Manage products, categories, plush bases, accessories, and users
-   **Accessibility Features** -- Change the font size, website theme, contrast mode, reduce motion or even enable a Dyslexia-Friendly Font to make sure the website is as user-friendly as possible

## Tech Stack

| Layer | Choice |
|---|---|
| Language | PHP 8.4 |
| Router / Middleware | Slim 4 (`slim/slim`, `slim/psr7`) |
| Templates | Twig 3 |
| ORM | RedBeanPHP 5.7 |
| Container | PHP-DI 7 |
| Env Loader | `vlucas/phpdotenv` 5.6 |
| i18n | `symfony/translation` 8 |
| TOTP 2FA | `robthree/twofactorauth` 3 |
| QR Codes | `bacon/bacon-qr-code` 3.1 |
| Payments | `stripe/stripe-php` 20 |
| Front-end | Bootstrap 5 + Bootstrap Icons (CDN), plain JavaScript |

## Setup
Follow the instructions in [Toys4Us - self-host instructions.md](Toys4Us - self-host instructions.md) to self host our project.

## Routes Overview

### Public
| Method | Path | Controller | Description |
|--------|------|------------|-------------|
| GET | `/` | closure | Home page with featured products |
| GET | `/about` | closure | About Us page |
| GET | `/lang/{locale}` | closure | Switch language (`en` / `fr`) |
| GET | `/products` | `ProductsController::index` | Product catalog |
| GET | `/products/search-json` | `ProductsController::searchJson` | Product search (JSON) |
| GET | `/products/{id}` | `ProductsController::show` | Product detail page |
| GET | `/build` | `PlushController::index` | Build-a-Plush page |
| GET | `/build/{plush_id}` | `PlushController::edit` | Edit existing custom plush |
| POST | `/build` | `PlushController::save` | Save custom plush to cart |

### Cart
| Method | Path | Controller | Description |
|--------|------|------------|-------------|
| GET | `/cart` | `CheckoutController::showCart` | View cart |
| POST | `/cart/add/{id}` | `CheckoutController::addToCart` | Add product to cart |
| POST | `/cart/remove/{key}` | `CheckoutController::removeFromCart` | Remove item from cart |
| POST | `/cart/update/{key}` | `CheckoutController::updateQty` | Update item quantity |

### Checkout
| Method | Path | Controller | Description |
|--------|------|------------|-------------|
| GET | `/checkout/shipping` | `CheckoutController::showShipping` | Shipping address page |
| POST | `/checkout/shipping` | `CheckoutController::processShipping` | Save address, proceed to payment |
| GET | `/checkout/payment` | `CheckoutController::showPayment` | Order summary + pay button |
| POST | `/checkout/payment` | `CheckoutController::processPayment` | Create Stripe session, redirect |
| GET | `/checkout/success` | `CheckoutController::success` | Post-payment confirmation |

### Auth
| Method | Path | Controller | Description |
|--------|------|------------|-------------|
| GET | `/login` | `AuthController::showLogin` | Login page |
| POST | `/login` | `AuthController::login` | Process login |
| GET | `/register` | `AuthController::showRegister` | Register page |
| POST | `/register` | `AuthController::register` | Process registration |
| POST | `/logout` | `AuthController::logout` | Log out |
| GET | `/totp/verify` | `AuthController::showTotpVerify` | TOTP verification page |
| POST | `/totp/verify` | `AuthController::verifyTotp` | Verify TOTP code |
| POST | `/totp/skip` | `AuthController::skipTotpSetup` | Skip TOTP setup |

### Profile
| Method | Path | Controller | Description |
|--------|------|------------|-------------|
| GET | `/profile` | `ProfileController::index` | Profile page |
| POST | `/profile/edit` | `ProfileController::update` | Update name / email / phone |
| POST | `/profile/change-password` | `ProfileController::changePassword` | Change password |
| POST | `/profile/delete` | `ProfileController::delete` | Schedule account deletion |
| POST | `/profile/address/add` | `ProfileController::addAddress` | Add saved address |
| POST | `/profile/address/delete` | `ProfileController::deleteAddress` | Delete saved address |
| POST | `/profile/totp/setup` | `ProfileController::setupTotp` | Start 2FA setup |
| POST | `/profile/totp/confirm` | `ProfileController::confirmTotp` | Confirm 2FA code |
| POST | `/profile/totp/disable` | `ProfileController::disableTotp` | Disable 2FA |

### Admin — protected by `AdminMiddleware`
| Method | Path | Controller | Description |
|--------|------|------------|-------------|
| GET | `/admin` | `AdminController::index` | Dashboard |
| GET | `/admin/products` | `AdminController::products` | Product list |
| POST | `/admin/products/store` | `AdminController::storeProduct` | Add product |
| POST | `/admin/products/update` | `AdminController::updateProduct` | Update product |
| POST | `/admin/products/delete` | `AdminController::deleteProduct` | Delete product |
| GET | `/admin/categories` | `AdminController::categories` | Category list |
| POST | `/admin/categories/store` | `AdminController::storeCategory` | Add category |
| POST | `/admin/categories/update` | `AdminController::updateCategory` | Update category |
| POST | `/admin/categories/delete` | `AdminController::deleteCategory` | Delete category |
| GET | `/admin/bases` | `AdminController::bases` | Plush base list |
| POST | `/admin/bases/store` | `AdminController::storeBase` | Add plush base |
| POST | `/admin/bases/update` | `AdminController::updateBase` | Update plush base |
| POST | `/admin/bases/delete` | `AdminController::deleteBase` | Delete plush base |
| GET | `/admin/accessories` | `AdminController::accessories` | Accessory list |
| POST | `/admin/accessories/store` | `AdminController::storeAccessory` | Add accessory |
| POST | `/admin/accessories/update` | `AdminController::updateAccessory` | Update accessory |
| POST | `/admin/accessories/delete` | `AdminController::deleteAccessory` | Delete accessory |
| GET | `/admin/users` | `AdminController::users` | User list |
| POST | `/admin/users/update` | `AdminController::updateUser` | Update user role |
| POST | `/admin/users/delete` | `AdminController::deleteUser` | Delete user |

### REST API
| Method | Path | Controller | Description |
|--------|------|------------|-------------|
| GET | `/api/products` | `ProductsController::apiIndex` | List all products |
| GET | `/api/products/{id}` | `ProductsController::apiGet` | Get single product |
| POST | `/api/products` | `ProductsController::apiCreate` | Create product |
| PUT | `/api/products/{id}` | `ProductsController::apiUpdate` | Update product |
| DELETE | `/api/products/{id}` | `ProductsController::apiDelete` | Delete product |
## Project Structure

```
Toys4Us/
├── assets/
│   ├── accessories/
│   ├── bases/
│   ├── icons/
│   └── style.css
├── src/
│   ├── Controllers/
│   │   ├── AdminController.php
│   │   ├── AuthController.php
│   │   ├── CheckoutController.php
│   │   ├── PlushController.php
│   │   ├── ProductsController.php
│   │   └── ProfileController.php
│   ├── Middleware/
│   │   ├── AdminMiddleware.php
│   │   ├── AuthMiddleware.php
│   │   ├── MaintenanceMiddleware.php
│   │   └── SecurityHeadersMiddleware.php
│   ├── Models/
│   │   ├── AddressModel.php
│   │   ├── CartModel.php
│   │   ├── CategoryModel.php
│   │   ├── OrderModel.php
│   │   ├── PlushModel.php
│   │   ├── ProductModel.php
│   │   └── UserModel.php
│   └── Services/
│       ├── FlashService.php
│       └── OtpService.php
├── templates/
│   ├── admin/
│   │   ├── accessories.html.twig
│   │   ├── bases.html.twig
│   │   ├── categories.html.twig
│   │   ├── index.html.twig
│   │   ├── products.html.twig
│   │   └── users.html.twig
│   ├── checkout/
│   │   ├── payment.html.twig
│   │   ├── shipping.html.twig
│   │   └── success.html.twig
│   ├── errors/
│   │   └── 404.html.twig
│   ├── about.html.twig
│   ├── auth.html.twig
│   ├── build.html.twig
│   ├── cart.html.twig
│   ├── home.html.twig
│   ├── layout.html.twig
│   ├── product.html.twig
│   ├── products.html.twig
│   ├── profile.html.twig
│   └── totp-verify.html.twig
├── translations/
│   ├── messages.en.php
│   └── messages.fr.php
├── var/
├── .env
├── .gitignore
├── .htaccess
├── composer.json
├── composer.lock
├── index.php
└── toys4us.sql
```

## Common Tasks and Their Respective Commands

| Task | Command |
|------|---------|
| Install or refresh PHP dependencies | `composer install` |
| Refresh autoloading after adding classes | `composer dump-autoload` |
| Clear the application log | `truncate -s 0 var/app.log` |
---
| Task | Command |
|------|---------|
| Removing Maintenance Mode (from Linux server) | `rm [Files Directory]/var/maintenance.flag` |
| Setting Maintenance Mode (from Linux Server) | `touch [Files Directory]/var/maintenance.flag` 

## Database Structure
Viewable inside [Toys4Us - Database.md](Toys4Us - Database.md)

## Authors
> Karim Fahd (6294503)
> Sarah 'Zack' Radi (6293771)

## Acknowledgments
-   `README.md` structure was inspired by  [DomPizzie/README-Template](https://gist.github.com/DomPizzie/7a5ff55ffa9081f2de27c315f5018afc)