# Admin/POS Mobile App — API Guide (v4)

This document describes the REST API this Laravel panel exposes for a companion **admin/employee mobile app** (built for a single local shop — not a multi-vendor marketplace). It's written so an AI assistant (or a developer) can scaffold a **React Native (Expo)** app against it without reading the backend source.

- Base path: `/api/v4/admin`
- Full base URL (dev example): `http://spanel.test/api/v4/admin`
- Full base URL (prod example): `https://your-domain.com/api/v4/admin`
- Format: JSON in, JSON out. Send `Accept: application/json`.
- Auth: Bearer token (see below).

> Note: `v4` here is this project's internal API version name, unrelated to HTTP versions or React/Expo SDK versions.

---

## 1. Authentication

Token-based, not OAuth/JWT. A successful login returns an opaque random token that must be sent on every subsequent request.

### POST `/auth/login` (public, no token required)

Request body:
```json
{ "email": "admin@example.com", "password": "123456" }
```

Success `200`:
```json
{
  "token": "AbCdEf...50-char-random-string",
  "admin": {
    "id": 1,
    "name": "Admin",
    "email": "admin@example.com",
    "phone": "+8801xxxxxxxxx",
    "admin_role_id": 1,
    "is_super_admin": true
  }
}
```

Failure `401`:
```json
{ "errors": [{ "code": "auth-001", "message": "credentials does not match or your account has been suspended" }] }
```

Validation failure `403` (missing/invalid fields) — see §3 for the shape.

### Using the token

Every other endpoint requires:
```
Authorization: Bearer <token>
Accept: application/json
```

If the token is missing/invalid/revoked, every protected endpoint returns:
```json
// 401
{ "auth-001": "Your existing session token does not authorize you any more" }
```
(Note: this one specific error shape is flat, not wrapped in `errors: []` like the others — handle both shapes defensively in the app's API client.)

### GET `/me`
Returns the logged-in admin (same shape as the `admin` object from login).

### GET `/logout`
Invalidates the current token server-side (sets it to null). Client should also discard its stored token.

**Suggested Expo app auth flow:** store the token in `expo-secure-store`, attach it via an Axios/fetch interceptor, and on any `401` response, clear the token and redirect to the login screen.

---

## 2. Pagination

Any endpoint whose response is a list (unless noted as "all, no pagination") returns Laravel's standard paginator shape:

```json
{
  "current_page": 1,
  "data": [ /* array of items */ ],
  "first_page_url": "...",
  "from": 1,
  "last_page": 5,
  "last_page_url": "...",
  "links": [ /* pagination link objects */ ],
  "next_page_url": "...",
  "path": "...",
  "per_page": 15,
  "prev_page_url": null,
  "to": 15,
  "total": 67
}
```

Paginated endpoints accept:
- `?limit=25` — page size (default varies by resource, usually the panel's configured default)
- `?page=2` — page number
- `?searchValue=...` — free-text search where noted

## 3. Errors

Two error shapes appear across the API — **handle both**:

**A. Validation / not-found errors** (most endpoints):
```json
{ "errors": [ { "code": "product-001", "message": "Product not found" } ] }
```
HTTP status is `403` for validation failures, `404` for not-found, `422` for business-rule failures (e.g. insufficient stock).

**B. Auth middleware error** (only from the `admin_api_auth` gate itself, see §1):
```json
{ "auth-001": "Your existing session token does not authorize you any more" }
```

---

## 4. Endpoints

All endpoints below are under `/api/v4/admin` and require the Bearer token unless marked **(public)**.

### 4.1 Auth
| Method | Path | Notes |
|---|---|---|
| POST | `/auth/login` | **(public)** See §1 |
| GET | `/logout` | Invalidate token |
| GET | `/me` | Current admin profile |

### 4.2 Dashboard
| Method | Path | Notes |
|---|---|---|
| GET | `/dashboard` | Home-screen summary (see shape below) |

Response `200`:
```json
{
  "total_products": 15002,
  "total_orders": 3,
  "total_customers": 42,
  "total_employees": 3,
  "low_stock_products": 5,
  "recent_stock_movements": [ /* last 10 StockHistory rows, see §5.6 */ ]
}
```

### 4.3 Categories & Brands (read-only, for pickers/dropdowns)
| Method | Path | Notes |
|---|---|---|
| GET | `/categories` | `?parent_id=<id>` for sub-categories, else returns top-level categories. No pagination (`dataLimit=all`). |
| GET | `/brands` | `?searchValue=...`. No pagination. |

### 4.4 Employees
| Method | Path | Notes |
|---|---|---|
| GET | `/employees` | `?searchValue=`, `?admin_role_id=`, `?limit=`. Paginated. |
| GET | `/employees/{id}` | 404 `employee-001` if missing |

### 4.5 Products
| Method | Path | Notes |
|---|---|---|
| GET | `/products` | `?searchValue=`, `?category_id=`, `?sub_category_id=`, `?brand_id=`, `?status=`, `?limit=`. Paginated. |
| GET | `/products/{id}` | Includes `category`, `brand`, `translations` relations. 404 `product-001`. |
| POST | `/products` | Create a **physical** product (see body below) |
| POST | `/products/{id}` | Partial update — only whitelisted fields (see below), `current_stock` is deliberately NOT editable here |
| DELETE | `/products/{id}` | Deletes product + its translations |
| POST | `/products/{id}/stock` | Manual stock adjustment (in/out), logged to stock history |
| POST | `/products/purchase` | Bulk stock-in from a supplier purchase, logged to stock history and updates `purchase_price` |

**Create product** body:
```json
{
  "name": "Iphone 15 back glass",
  "code": "SKU-001",
  "category_id": 12,
  "sub_category_id": 0,
  "sub_sub_category_id": 0,
  "brand_id": null,
  "unit": "pc",
  "minimum_order_qty": 1,
  "unit_price": 1200,
  "purchase_price": 900,
  "tax": 0,
  "discount": 0,
  "discount_type": "percent",
  "current_stock": 50,
  "details": "optional description",
  "status": 1
}
```
`name`, `code`, `category_id`, `unit`, `unit_price`, `current_stock` are required. Product is created as `product_type: physical`, `added_by: admin`. The initial `current_stock` is logged as a `initial_stock` stock-history entry automatically.

**Update product** — only these fields are accepted, anything else in the body is ignored: `name, code, category_id, sub_category_id, sub_sub_category_id, brand_id, unit, minimum_order_qty, unit_price, purchase_price, tax, discount, discount_type, details, status`. Send only the fields you want to change.

**Adjust stock** (`POST /products/{id}/stock`):
```json
{ "quantity_change": -3, "note": "damaged, written off" }
```
`quantity_change` can be negative (removes stock) or positive (adds stock). Returns the created `StockHistory` row (§5.6). Stock cannot go below 0 (the actual applied change is clamped and reflected in the returned `previous_stock`/`new_stock`).

**Purchase stock** (`POST /products/purchase`) — stock-in from a supplier, optionally updates cost price:
```json
{
  "reference_no": "PO-1001",
  "items": [
    { "product_id": 506, "qty": 20, "unit_cost": 150, "note": "optional" },
    { "product_id": 511, "qty": 5 }
  ]
}
```
If `unit_cost` is given for an item, that product's `purchase_price` is updated to it. `reference_no` is optional — if omitted here, note it's auto-generated only by the web Purchase page, not this raw endpoint (send your own reference if you want one, e.g. a timestamp or PO number, for grouping in reports later).

### 4.6 Stock History
| Method | Path | Notes |
|---|---|---|
| GET | `/stock-history` | `?product_id=`, `?type=`, `?from_date=YYYY-MM-DD`, `?to_date=YYYY-MM-DD`, `?searchValue=`, `?limit=`. Paginated, includes `product` + `admin` relations. |
| GET | `/stock-history/{id}` | 404 `stock-history-001` |

`type` values: `purchase`, `adjustment`, `bulk_edit`, `bulk_import`, `initial_stock`, `order`, `order_cancel`, `return`.

### 4.7 Orders
| Method | Path | Notes |
|---|---|---|
| GET | `/orders` | `?order_status=`, `?payment_status=`, `?searchValue=`, `?limit=`. Paginated, includes `customer` + `orderDetails`. |
| GET | `/orders/{id}` | Includes `customer` + `orderDetails.product`. 404 `order-001`. |
| POST | `/orders/{id}/status` | Body: `{ "order_status": "delivered" }`. Updates stock (e.g. restores it on cancel/return) and the status. |

`order_status` values: `pending, confirmed, processing, out_for_delivery, delivered, returned, canceled, failed`.
`payment_status` values: `paid, partial, due` (see POS section — `partial`/`due` only occur on POS sales with underpayment).

### 4.8 POS — create a sale
| Method | Path | Notes |
|---|---|---|
| POST | `/pos/sale` | The core "checkout" endpoint for the app |

This is how the mobile app rings up a sale. It supports **full payment, partial payment, and fully on-credit ("due") sales** — the core "buy now, pay later" feature for this local shop.

Request body:
```json
{
  "items": [
    { "product_id": 506, "qty": 2, "price": 1200 },
    { "product_id": 511, "qty": 1 }
  ],
  "customer_id": 15,
  "paid_amount": 1200,
  "payment_method": "cash",
  "note": "optional order note"
}
```
- `items[].price` is optional — omit it to use the product's current `unit_price`.
- `customer_id` — omit or send `0` for a walk-in/anonymous sale, **but this is only allowed when `paid_amount` covers the full total**. Underpaying requires a real `customer_id` (there must be someone to owe the money).
- `paid_amount` — how much cash the customer actually handed over right now. Can be `0` (fully on credit), between 0 and the total (partial), or `>=` the total (paid in full — any excess is not tracked as change, just clamp your UI-side display).
- `payment_method` — free text, defaults to `"cash"` if paid in full, `"due"` if nothing was paid.

Response `201`:
```json
{
  "order": { /* full Order object incl. customer + orderDetails */ },
  "order_amount": 1200,
  "paid_amount": 500,
  "due_amount": 700,
  "payment_status": "partial"
}
```

`payment_status` is computed automatically: `paid` (paid_amount >= total), `partial` (0 < paid_amount < total), `due` (paid_amount is 0). When there's a `due_amount > 0`, it's automatically added to that customer's running due balance (see §4.10) and logged to their due ledger, tagged with this order's id.

Error cases:
- `product-001` (404) — a product id in `items` doesn't exist
- `pos-001` (422) — not enough stock for a physical product (message names the product)
- `pos-002` (422) — tried to underpay without a real customer selected
- `customer-001` (404) — `customer_id` given but doesn't exist

**Suggested Expo screens:** Product grid/search → cart → checkout screen with a numeric keypad for `paid_amount`, a customer picker (required if the entered amount is less than the cart total), and a big "Due: X" indicator that appears live as the cashier types an amount less than the total.

### 4.9 Customers
| Method | Path | Notes |
|---|---|---|
| GET | `/customers` | `?searchValue=`, `?is_active=`, `?limit=`. Paginated. |
| GET | `/customers/{id}` | Includes last 10 `orders` and last 10 `dueTransactions`. 404 `customer-001`. |

Customer object includes `due_balance` (float, how much they currently owe the shop) and `wallet_balance` (float, unrelated prepaid credit feature — opposite direction, not used by POS due sales).

### 4.10 Customer Due (credit) management
| Method | Path | Notes |
|---|---|---|
| GET | `/customer-dues` | Due report: every customer with a balance, sorted highest-due first. `?searchValue=`, `?limit=`. |
| GET | `/customer-dues/{customerId}` | Full paginated ledger for one customer |
| POST | `/customer-dues/{customerId}/payments` | Record the customer paying back some/all of what they owe |

`GET /customer-dues` response:
```json
{
  "total_outstanding_due": 15200.00,
  "customers": { /* paginated list of customer objects, each with due_balance */ }
}
```

`GET /customer-dues/{id}` response:
```json
{
  "customer_id": 15,
  "due_balance": 700.00,
  "transactions": { /* paginated CustomerDueTransaction list, see below */ }
}
```

Record a payment — `POST /customer-dues/{customerId}/payments`:
```json
{ "amount": 200, "note": "paid in cash at counter" }
```
Response `201`: the created `CustomerDueTransaction`. Fails `422` `customer-due-001` if `amount` exceeds their current due balance (can't overpay), `404` `customer-001` if the customer doesn't exist.

**CustomerDueTransaction shape:**
```json
{
  "id": 3,
  "user_id": 15,
  "order_id": 100003,
  "type": "sale_due",
  "amount": 700.00,
  "balance_after": 700.00,
  "note": "pos_sale_due #100003",
  "admin_id": 1,
  "created_at": "2026-09-16T21:45:42.000000Z"
}
```
`type` is `sale_due` (a POS sale added to the balance) or `payment` (a paydown). `order_id` is null for payments.

### 4.11 Reports
| Method | Path | Notes |
|---|---|---|
| GET | `/reports/sales` | `?from_date=YYYY-MM-DD&to_date=YYYY-MM-DD` (default: last 30 days) |
| GET | `/reports/stock` | Current inventory snapshot, `?limit=` for the low-stock list size |
| GET | `/reports/profit-loss` | `?from_date=&to_date=` — revenue vs. cost of goods sold |
| GET | `/reports/due` | Outstanding-credit summary, `?limit=` for the top-debtors list size |

`/reports/sales` response:
```json
{
  "from_date": "2026-08-18",
  "to_date": "2026-09-16",
  "total_orders": 3,
  "total_sales": 7910.00,
  "total_paid": 7760.00,
  "total_due": 150.00,
  "by_payment_status": [
    { "payment_status": "paid", "count": 2, "total": 7710.00 },
    { "payment_status": "partial", "count": 1, "total": 200.00 }
  ]
}
```

`/reports/stock` response:
```json
{
  "total_products": 15002,
  "total_units": 1500207,
  "total_stock_value": 16763.00,
  "out_of_stock_count": 0,
  "low_stock_count": 5,
  "low_stock_products": [ { "id": 1, "name": "...", "code": "...", "current_stock": 3, "purchase_price": 90, "unit_price": 120 } ]
}
```

`/reports/profit-loss` response:
```json
{ "from_date": "2026-08-18", "to_date": "2026-09-16", "revenue": 26950.00, "cost": 2250.00, "profit": 24700.00 }
```

`/reports/due` response:
```json
{
  "total_outstanding_due": 15200.00,
  "customers_with_due_count": 6,
  "top_debtors": [ { "id": 15, "f_name": "Jisan", "l_name": "RABBY", "phone": "+8801...", "due_balance": 700.00 } ]
}
```

---

## 5. Key data shapes

### 5.1 Product (abridged)
```json
{
  "id": 506, "name": "...", "code": "SKU-001", "slug": "...",
  "category_id": 12, "sub_category_id": 0, "sub_sub_category_id": 0, "brand_id": null,
  "unit": "pc", "product_type": "physical", "minimum_order_qty": 1,
  "unit_price": 1200, "purchase_price": 900, "tax": 0, "tax_type": "percent",
  "discount": 0, "discount_type": "percent",
  "current_stock": 48, "status": 1, "thumbnail": "...", "images": "[...]"
}
```
Use `getStorageImages`-style URLs — image fields (`thumbnail`, `images`) are raw filenames/paths, not ready-to-use URLs; the panel resolves them server-side via a storage helper. For a fresh mobile app, either request an endpoint that already resolves URLs, or reconstruct them from your storage disk's public base URL + the stored path (check `thumbnail_full_url` if present on the raw model — it's an accessor available on the Eloquent model and IS included in JSON responses since it's an appended attribute).

### 5.2 Order (abridged)
```json
{
  "id": 100003, "customer_id": 15, "customer_type": "customer",
  "payment_status": "partial", "order_status": "delivered",
  "payment_method": "cash", "order_type": "POS",
  "order_amount": 1200.00, "paid_amount": 500.00,
  "seller_id": 1, "seller_is": "admin",
  "created_at": "...", "updated_at": "...",
  "customer": { /* User/customer object */ },
  "orderDetails": [ /* OrderDetail[] */ ]
}
```

### 5.3 OrderDetail (abridged)
```json
{
  "id": 1, "order_id": 100003, "product_id": 506,
  "qty": 2, "price": 1200.00, "discount": 0, "tax": 0,
  "payment_status": "partial", "delivery_status": "delivered",
  "product_details": { /* snapshot of the Product at time of sale */ }
}
```

### 5.4 Customer (User)
```json
{
  "id": 15, "name": "Jisan RABBY", "f_name": "Jisan", "l_name": "RABBY",
  "phone": "+8801974401905", "email": "...",
  "wallet_balance": 0, "due_balance": 700.00,
  "is_active": 1, "created_at": "..."
}
```
Customer id `0` is the built-in "Walking Customer" placeholder used for anonymous/full-payment sales — don't let the app treat it as a real, selectable customer for due sales.

### 5.5 Employee (Admin)
```json
{ "id": 3, "name": "Cashier One", "email": "...", "phone": "...", "admin_role_id": 4, "status": 1, "role": { "id": 4, "name": "Cashier" } }
```

### 5.6 StockHistory
```json
{
  "id": 7, "product_id": 15505, "type": "order",
  "quantity_change": -1, "previous_stock": 100, "new_stock": 99,
  "unit_cost": null, "reference_no": "order-100003", "note": null,
  "admin_id": 1, "created_at": "...",
  "product": { /* Product */ }, "admin": { /* Admin */ }
}
```
`quantity_change` is signed: negative = stock left (sale/adjustment out), positive = stock came in (purchase/adjustment in/initial).

---

## 6. Business rules worth encoding in the app, not just the API

1. **Due sales require a known customer.** The app should force customer selection the moment the entered payment amount drops below the cart total, and block checkout otherwise (the API also enforces this server-side with `pos-002`).
2. **Stock is source-of-truth server-side.** Always re-check `current_stock` from `GET /products/{id}` or the product list before assuming an add-to-cart will succeed — someone else (web POS, another device) may have sold the last unit. The `/pos/sale` call itself is the final authority and will reject with `pos-001` if stock ran out between screens.
3. **`due_balance` is a live running total**, not something you compute client-side from order history — always trust the value from `GET /customers/{id}` or `GET /customer-dues/{id}` over any local cache.
4. **Reports are point-in-time date-range queries**, not real-time dashboards — refetch on pull-to-refresh, don't try to derive them from cached order lists.
5. **No refresh-token flow.** Tokens don't expire automatically server-side (no TTL) but can be invalidated (logout, or an admin's status changed to inactive). Treat any `401` as "log the user out."

---

## 7. Suggested Expo app structure

```
app/
  (auth)/
    login.tsx
  (tabs)/
    dashboard.tsx        -> GET /dashboard
    pos/
      index.tsx          -> product grid, GET /products, GET /categories, GET /brands
      cart.tsx           -> local cart state
      checkout.tsx        -> customer picker (GET /customers), POST /pos/sale
    products/
      index.tsx           -> GET /products
      [id].tsx            -> GET /products/{id}, POST /products/{id}, POST /products/{id}/stock
      add.tsx             -> POST /products
      purchase.tsx        -> POST /products/purchase
    stock-history/
      index.tsx           -> GET /stock-history
    orders/
      index.tsx           -> GET /orders
      [id].tsx            -> GET /orders/{id}, POST /orders/{id}/status
    customers/
      index.tsx           -> GET /customers
      [id].tsx            -> GET /customers/{id}, GET /customer-dues/{id}, POST /customer-dues/{id}/payments
    due/
      index.tsx           -> GET /customer-dues  (who owes money, shop-wide)
    reports/
      sales.tsx           -> GET /reports/sales
      stock.tsx           -> GET /reports/stock
      profit-loss.tsx     -> GET /reports/profit-loss
      due.tsx             -> GET /reports/due
    employees/
      index.tsx           -> GET /employees
      [id].tsx            -> GET /employees/{id}
    settings/
      profile.tsx         -> GET /me
```

### 7.1 Visual theme — Black & Red

The web panel uses a **black & red** brand theme. Match it in the Expo app for a consistent brand feel:

```ts
export const theme = {
  primary: '#E1122F',      // brand red — buttons, active tab, links, badges
  primaryDark: '#B10E25',  // pressed/active state
  black: '#161616',        // headings, primary text, dark surfaces (sidebar-equivalent = bottom tab bar)
  surface: '#1A1A1A',      // dark cards / nav bar background
  background: '#F9F9FB',   // light screen background (keep light-mode content areas readable)
  textPrimary: '#161616',
  textSecondary: '#4A4A4A',
  border: 'rgba(0,0,0,0.12)',
  success: '#07B275',      // keep status colors semantic, NOT red — success/paid stays green
  warning: '#FE961C',
  danger: '#FF5555',       // "danger" stays a distinct red-orange from brand red to avoid ambiguity with due/overdue badges
  white: '#FFFFFF',
};
```

Guidance:
- Use brand red (`primary`) for the bottom tab bar's active icon/label, primary buttons ("Checkout", "Save", "Record Payment"), and the app header/logo area.
- Use near-black (`black`/`surface`) for the header bar or bottom tab bar background, matching the web sidebar.
- **Don't** use brand red for "due"/"danger" badges if you also use it as the primary action color — pick `danger` (a distinct red-orange, e.g. `#FF5555`) for due/overdue indicators so they don't visually blend into ordinary buttons. This mirrors how the web panel keeps status colors (info/success/danger) separate from the brand accent color.
