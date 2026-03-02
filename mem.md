
# SweetPath Project Knowledge

## Architecture & Conventions
- **Backend**: Pure PHP with PDO. APIs return JSON (`api/*.php`).
- **Frontend**: Vanilla HTML/JS/CSS. Uses ES6 modules.
- **Database**: MySQL (`sweetpath_db`).
- **Store Status**: Controlled by `lib/store_status.php`. It checks business hours and manual pause from the `config` table. Both `orders_create.php` (EXPRESS) and `orders_request.php` (CUSTOM/PACK) enforce this check.

## Key Frontend Modules
- **`js/api.js`**: Handles `fetch` requests (`apiGet`, `apiPost`) and basic error throwing.
- **`js/cart-manager.js`**: Centralized `localStorage` cart management (`getCart`, `setCart`, `addToCart`, `clearCart`, `updateCartMini`). Always use this instead of direct `localStorage` manipulation.
- **`js/ui-utils.js`**: Reusable UI components and validations.
  - `card(p)`: Generates the HTML for product cards used in `home.js` and `products.js`.
  - `validatePhone(phone)`: Ensures phone numbers have at least 8 digits.
  - `getMinDate(type)`: Calculates minimum allowed date based on order type (EXPRESS: today, PACK: +24h, CUSTOM: +72h).

## Order Flow
1. **EXPRESS**: Handled by `api/orders_create.php`. Direct cart checkout.
2. **CUSTOM / PACK**: Handled by `api/orders_request.php`. Requires a `details` JSON object in the payload. The backend constructs the WhatsApp summary to avoid frontend tampering.
3. **Payment**: After admin approval, clients pay via QR at `pay.php?code=ORDER_CODE` and upload proof.

## Best Practices
- **Validation**: Always validate dates (`min` attribute) and phones on the frontend before submitting.
- **Payload Consistency**: Ensure frontend payload keys match backend expectations (e.g., `details` instead of `custom_json`).
- **No Logic Duplication**: Use `ui-utils.js` and `cart-manager.js` to avoid duplicating HTML strings or cart logic across different pages.
