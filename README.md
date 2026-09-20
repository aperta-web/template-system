# Aperta Template System

A reusable **letter & order/invoice PDF template system** for Laravel + Inertia.js (Vue 3) projects.

Features:
- GrapesJS drag-and-drop template editor
- Letter templates (with placeholders for company info, recipient, body, etc.)
- Order/Invoice templates (with order items table, totals, client info, etc.)
- PDF generation via `barryvdh/laravel-dompdf`
- Letterhead background support
- Multi-company aware (templates are scoped to `company_id`)

---

## Requirements

| Requirement | Version |
|---|---|
| PHP | ^8.2 |
| Laravel | ^11 or ^12 |
| Inertia.js | v2 |
| Vue | 3 |
| spatie/laravel-permission | any (for `hasRole('admin')`) |
| barryvdh/laravel-dompdf | ^3.0 |
| grapesjs (npm) | latest |

---

## Installation in a new Laravel project

### Step 1 — Register the local package as a path repository

Open **`composer.json`** in your new project and add the `repositories` block:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "C:/Users/Richard/herd/packages/aperta-template-system",
            "options": {
                "symlink": true
            }
        }
    ],
    "require": {
        "aperta/template-system": "@dev"
    }
}
```

Then run:

```bash
composer require aperta/template-system:@dev
```

> **Tip**: `"symlink": true` means any edits you make to the package files are **instantly** reflected in the project — no re-install needed.

---

### Step 2 — Publish the migrations

```bash
php artisan vendor:publish --tag=aperta-template-migrations
php artisan migrate
```

This creates the `letter_templates` table.

---

### Step 3 — Publish the Vue files

```bash
php artisan vendor:publish --tag=aperta-template-views
```

This copies into your project:
- `resources/js/Pages/LetterTemplates/Index.vue`
- `resources/js/Pages/LetterTemplates/Form.vue`
- `resources/js/Components/GrapesEditor.vue`

> After publishing, these files are **yours** — edit them freely. They will no longer auto-update from the package.

---

### Step 4 — Install the npm dependency

```bash
npm install grapesjs
```

---

### Step 5 — Add routes

In `routes/web.php`:

```php
use Aperta\TemplateSystem\Http\Controllers\LetterTemplateController;

Route::middleware(['auth'])->group(function () {
    Route::resource('letter-templates', LetterTemplateController::class)
        ->except(['show']);
    Route::post('letter-templates/{letterTemplate}/duplicate',
        [LetterTemplateController::class, 'duplicate'])
        ->name('letter-templates.duplicate');
});
```

---

### Step 6 — Add a nav link

In your `AuthenticatedLayout.vue`, add a link to `route('letter-templates.index')`.

---

### Step 7 — Use the model in your own controllers

```php
use Aperta\TemplateSystem\Models\LetterTemplate;

// Get all order templates for a company
$templates = LetterTemplate::where('company_id', $companyId)
    ->where('type', 'order')
    ->get();

// Render a letter
$html = $template->render($data, $company);

// Render an order/invoice
$html = $template->renderOrder($order, $company);
```

---

### Step 8 — Generate order PDFs (add the trait to your OrderController)

```php
use Aperta\TemplateSystem\Concerns\GeneratesOrderPdf;
use Aperta\TemplateSystem\Models\LetterTemplate;

class OrderController extends Controller
{
    use GeneratesOrderPdf;

    public function pdf(Request $request, Order $order)
    {
        $order->load(['contact', 'items']);
        $company  = auth()->user()->currentCompany;

        $template = LetterTemplate::where('id', $request->validate(['template_id' => 'required|integer|exists:letter_templates,id'])['template_id'])
            ->where('company_id', $company->id)
            ->where('type', 'order')
            ->firstOrFail();

        $filename = $order->order_number . '-' . now()->format('Ymd') . '.pdf';
        return $this->buildOrderPdf($order, $template, $company)->download($filename);
    }
}
```

Add these routes:

```php
Route::get('orders/{order}/pdf', [OrderController::class, 'pdf'])->name('orders.pdf');
Route::post('orders/{order}/pdf/save', [OrderController::class, 'savePdf'])->name('orders.savePdf');
```

---

## What your Order model needs

The `renderOrder()` method expects these fields/relations on `$order`:

| Field / Relation | Type | Description |
|---|---|---|
| `order_number` | string | Displayed as invoice number |
| `order_date` | Carbon | Formatted as "April 26, 2026" |
| `delivery_date` | Carbon\|null | Delivery / due date |
| `status` | string | Raw status key |
| `notes` | string\|null | Notes |
| `subtotal` | numeric | |
| `tax_rate` | numeric | e.g. `21` for 21% |
| `tax_amount` | numeric | |
| `total` | numeric | |
| `items` | Collection | Each item: `description`, `quantity`, `unit`, `unit_price`, `total` |
| `contact` | Model | `first_name`, `last_name`, `organization`, `email`, `address` |

---

## What your Company model needs

| Field | Description |
|---|---|
| `name` | Company name |
| `address` | Street address |
| `city` | City |
| `phone` | Phone number |
| `email` | Company email |
| `website` | Website URL |
| `tax_id` | Tax / VAT number |
| `registration_number` | Chamber of Commerce number |
| `logo_path` | Relative to `storage/app/public/` |
| `letterhead_path` | Optional. Full-page background image for PDF. Relative to `storage/app/public/` |

---

## What your User model needs

| Method / Property | Description |
|---|---|
| `current_company_id` | The active company ID for the logged-in user |
| `hasRole('admin')` | Returns `true` if user is an admin (spatie/laravel-permission) |

---

## Updating the package

Since the package is a **symlink** (path repository), any changes you make in  
`C:\Users\Richard\packages\aperta-template-system\`  
are immediately available in every project that uses it.

To re-run migrations after adding a new migration file to the package:

```bash
php artisan migrate
```

No `composer update` needed for PHP code changes. For Vue file changes, run:

```bash
php artisan vendor:publish --tag=aperta-template-views --force
npm run build
```

---

## Package structure

```
aperta-template-system/
├── composer.json
├── README.md
├── database/
│   └── migrations/
│       └── 2000_01_01_000001_create_letter_templates_table.php
├── resources/
│   └── js/
│       ├── Components/
│       │   └── GrapesEditor.vue          ← drag-and-drop HTML editor
│       └── Pages/
│           └── LetterTemplates/
│               ├── Index.vue             ← template list + placeholder reference
│               └── Form.vue              ← create/edit form with GrapesJS
└── src/
    ├── TemplateSystemServiceProvider.php
    ├── Concerns/
    │   └── GeneratesOrderPdf.php         ← trait for order PDF generation
    ├── Http/
    │   └── Controllers/
    │       ├── Controller.php
    │       └── LetterTemplateController.php
    └── Models/
        └── LetterTemplate.php            ← model + render() + renderOrder()
```
