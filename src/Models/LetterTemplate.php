<?php

namespace Aperta\TemplateSystem\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * LetterTemplate — stores HTML templates for letters and order/invoice PDFs.
 *
 * Usage:
 *   use Aperta\TemplateSystem\Models\LetterTemplate;
 *
 *   // Render a letter
 *   $html = $template->render($data, $company);
 *
 *   // Render an order/invoice
 *   $html = $template->renderOrder($order, $company);
 *
 * Note: renderOrder() requires $order to have:
 *   - items (collection of objects with: description, quantity, unit, unit_price, total)
 *   - contact (object with: first_name, last_name, organization, email, address)
 *   - order_number, order_date, delivery_date, status, notes
 *   - subtotal, tax_rate, tax_amount, total
 *
 *   $company must have: name, address, city, phone, email, website, tax_id,
 *   registration_number, logo_path (relative to storage/app/public)
 */
class LetterTemplate extends Model
{
    protected $fillable = [
        'company_id', 'name', 'type', 'base_design', 'html_content', 'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    // -----------------------------------------------------------------------
    // Placeholder definitions
    // -----------------------------------------------------------------------

    /** Placeholders available in letter templates */
    public const PLACEHOLDERS = [
        '{{company_name}}'            => 'Company name',
        '{{company_address}}'         => 'Company address',
        '{{company_city}}'            => 'Company city',
        '{{company_phone}}'           => 'Company phone',
        '{{company_email}}'           => 'Company email',
        '{{company_website}}'         => 'Company website',
        '{{company_tax_id}}'          => 'Tax / VAT ID',
        '{{company_registration}}'    => 'Registration number',
        '{{company_logo}}'            => 'Logo image tag',
        '{{date}}'                    => 'Letter date',
        '{{recipient_name}}'          => 'Recipient name',
        '{{recipient_address}}'       => 'Recipient address (multiline)',
        '{{subject}}'                 => 'Subject line',
        '{{reference}}'               => 'Reference / Our Ref.',
        '{{body}}'                    => 'Letter body paragraphs',
        '{{closing}}'                 => 'Closing phrase',
        '{{sender_name}}'             => 'Sender name',
        '{{sender_title}}'            => 'Sender job title',
    ];

    /** Placeholders available in order/invoice templates */
    public const ORDER_PLACEHOLDERS = [
        // Company
        '{{company_name}}'            => 'Company name',
        '{{company_address}}'         => 'Company address',
        '{{company_city}}'            => 'Company city',
        '{{company_phone}}'           => 'Company phone',
        '{{company_email}}'           => 'Company email',
        '{{company_website}}'         => 'Company website',
        '{{company_tax_id}}'          => 'Tax / VAT ID',
        '{{company_registration}}'    => 'Registration number',
        '{{company_logo}}'            => 'Logo image tag',
        // Order
        '{{order_number}}'            => 'Order / invoice number',
        '{{order_date}}'              => 'Order date',
        '{{order_delivery_date}}'     => 'Delivery / due date',
        '{{order_status}}'            => 'Order status label',
        '{{order_notes}}'             => 'Order notes',
        '{{order_items_table}}'       => 'Full line items table (HTML)',
        '{{order_subtotal}}'          => 'Subtotal amount',
        '{{order_tax_rate}}'          => 'Tax rate (%)',
        '{{order_tax_amount}}'        => 'Tax amount',
        '{{order_total}}'             => 'Grand total',
        // Client
        '{{client_name}}'             => 'Client full name',
        '{{client_organization}}'     => 'Client company / organization',
        '{{client_email}}'            => 'Client email',
        '{{client_address}}'          => 'Client address',
    ];

    // -----------------------------------------------------------------------
    // Relations
    // -----------------------------------------------------------------------

    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    // -----------------------------------------------------------------------
    // Render methods
    // -----------------------------------------------------------------------

    /**
     * Render a letter template with the provided data array and company.
     *
     * @param  array   $data  Keys: date, recipient_name, recipient_address,
     *                        subject, reference, body, closing,
     *                        sender_name, sender_title
     * @param  mixed   $company  Eloquent Company model instance
     * @param  bool    $hasLetterhead  When true, logo is suppressed (letterhead bg used instead)
     */
    public function render(array $data, $company, bool $hasLetterhead = false): string
    {
        $logoHtml = '';
        if (!$hasLetterhead && $company->logo_path) {
            $logoPath = storage_path('app/public/' . $company->logo_path);
            if (file_exists($logoPath)) {
                $mime     = mime_content_type($logoPath);
                $b64      = base64_encode(file_get_contents($logoPath));
                $logoHtml = "<img src=\"data:{$mime};base64,{$b64}\" style=\"max-height:60px;max-width:200px;display:block;margin-bottom:8px;\" alt=\"logo\" />";
            }
        }

        $bodyHtml = '';
        foreach (explode("\n\n", $data['body'] ?? '') as $paragraph) {
            if (trim($paragraph)) {
                $bodyHtml .= '<p style="margin-bottom:14px;">' . nl2br(htmlspecialchars(trim($paragraph))) . '</p>';
            }
        }

        $replacements = [
            '{{company_name}}'         => htmlspecialchars($company->name ?? ''),
            '{{company_address}}'      => htmlspecialchars($company->address ?? ''),
            '{{company_city}}'         => htmlspecialchars($company->city ?? ''),
            '{{company_phone}}'        => htmlspecialchars($company->phone ?? ''),
            '{{company_email}}'        => htmlspecialchars($company->email ?? ''),
            '{{company_website}}'      => htmlspecialchars($company->website ?? ''),
            '{{company_tax_id}}'       => htmlspecialchars($company->tax_id ?? ''),
            '{{company_registration}}' => htmlspecialchars($company->registration_number ?? ''),
            '{{company_logo}}'         => $logoHtml,
            '{{date}}'                 => htmlspecialchars($data['date'] ?? ''),
            '{{recipient_name}}'       => htmlspecialchars($data['recipient_name'] ?? ''),
            '{{recipient_address}}'    => nl2br(htmlspecialchars($data['recipient_address'] ?? '')),
            '{{subject}}'              => htmlspecialchars($data['subject'] ?? ''),
            '{{reference}}'            => htmlspecialchars($data['reference'] ?? ''),
            '{{body}}'                 => $bodyHtml,
            '{{closing}}'              => htmlspecialchars($data['closing'] ?? 'Yours sincerely,'),
            '{{sender_name}}'          => htmlspecialchars($data['sender_name'] ?? ''),
            '{{sender_title}}'         => htmlspecialchars($data['sender_title'] ?? ''),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $this->html_content);
    }

    /**
     * Render an order/invoice template with order data.
     *
     * @param  mixed  $order    Eloquent Order model (with items and contact loaded)
     * @param  mixed  $company  Eloquent Company model
     * @param  bool   $hasLetterhead
     */
    public function renderOrder($order, $company, bool $hasLetterhead = false): string
    {
        $logoHtml = '';
        if (!$hasLetterhead && $company->logo_path) {
            $logoPath = storage_path('app/public/' . $company->logo_path);
            if (file_exists($logoPath)) {
                $mime     = mime_content_type($logoPath);
                $b64      = base64_encode(file_get_contents($logoPath));
                $logoHtml = "<img src=\"data:{$mime};base64,{$b64}\" style=\"max-height:60px;max-width:200px;display:block;margin-bottom:8px;\" alt=\"logo\" />";
            }
        }

        $fmt = fn ($v) => number_format((float) $v, 2);

        // Build items table
        $rows = '';
        foreach ($order->items as $item) {
            $rows .= '<tr>'
                . '<td style="text-align:left;padding:10px 10px;border-bottom:1px solid #e5e7eb;">' . htmlspecialchars($item->description) . '</td>'
                . '<td style="text-align:right;padding:10px 10px;border-bottom:1px solid #e5e7eb;">' . $item->quantity . '</td>'
                . '<td style="text-align:right;padding:10px 10px;border-bottom:1px solid #e5e7eb;">' . htmlspecialchars($item->unit) . '</td>'
                . '<td style="text-align:right;padding:10px 10px;border-bottom:1px solid #e5e7eb;">' . $fmt($item->unit_price) . '</td>'
                . '<td style="text-align:right;padding:10px 10px;border-bottom:1px solid #e5e7eb;font-weight:600;">' . $fmt($item->total) . '</td>'
                . '</tr>';
        }

        $itemsTable = '<table style="width:100%;border-collapse:collapse;font-size:10px;">'
            . '<thead><tr style="background-color:#f9fafb;">'
            . '<th style="text-align:left;padding:8px 10px;border-bottom:2px solid #e5e7eb;font-size:11px;text-transform:uppercase;color:#6b7280;">Description</th>'
            . '<th style="text-align:right;padding:8px 10px;border-bottom:2px solid #e5e7eb;font-size:11px;text-transform:uppercase;color:#6b7280;">Qty</th>'
            . '<th style="text-align:right;padding:8px 10px;border-bottom:2px solid #e5e7eb;font-size:11px;text-transform:uppercase;color:#6b7280;">Unit</th>'
            . '<th style="text-align:right;padding:8px 10px;border-bottom:2px solid #e5e7eb;font-size:11px;text-transform:uppercase;color:#6b7280;">Unit Price</th>'
            . '<th style="text-align:right;padding:8px 10px;border-bottom:2px solid #e5e7eb;font-size:11px;text-transform:uppercase;color:#6b7280;">Total</th>'
            . '</tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '<tfoot>'
            . '<tr><td colspan="4" style="text-align:right;padding:8px 10px;color:#6b7280;font-size:12px;">Subtotal</td>'
            . '<td style="text-align:right;padding:8px 10px;font-weight:600;">' . $fmt($order->subtotal) . '</td></tr>'
            . '<tr><td colspan="4" style="text-align:right;padding:8px 10px;color:#6b7280;font-size:12px;">Tax (' . $order->tax_rate . '%)</td>'
            . '<td style="text-align:right;padding:8px 10px;font-weight:600;">' . $fmt($order->tax_amount) . '</td></tr>'
            . '<tr style="border-top:2px solid #111827;">'
            . '<td colspan="4" style="text-align:right;padding:10px 10px;font-weight:700;font-size:14px;">Total</td>'
            . '<td style="text-align:right;padding:10px 10px;font-weight:700;font-size:14px;">' . $fmt($order->total) . '</td></tr>'
            . '</tfoot></table>';

        $contact = $order->contact;

        // Resolve status label — tries Order::STATUSES constant, falls back to raw value
        $statusLabel = htmlspecialchars(
            (defined('App\\Models\\Order::STATUSES') ? (\App\Models\Order::STATUSES[$order->status] ?? $order->status) : $order->status)
        );

        $replacements = [
            '{{company_name}}'         => htmlspecialchars($company->name ?? ''),
            '{{company_address}}'      => htmlspecialchars($company->address ?? ''),
            '{{company_city}}'         => htmlspecialchars($company->city ?? ''),
            '{{company_phone}}'        => htmlspecialchars($company->phone ?? ''),
            '{{company_email}}'        => htmlspecialchars($company->email ?? ''),
            '{{company_website}}'      => htmlspecialchars($company->website ?? ''),
            '{{company_tax_id}}'       => htmlspecialchars($company->tax_id ?? ''),
            '{{company_registration}}' => htmlspecialchars($company->registration_number ?? ''),
            '{{company_logo}}'         => $logoHtml,
            '{{order_number}}'         => htmlspecialchars($order->order_number),
            '{{order_date}}'           => $order->order_date?->format('F j, Y') ?? '',
            '{{order_delivery_date}}'  => $order->delivery_date?->format('F j, Y') ?? '',
            '{{order_status}}'         => $statusLabel,
            '{{order_notes}}'          => nl2br(htmlspecialchars($order->notes ?? '')),
            '{{order_items_table}}'    => $itemsTable,
            '{{order_subtotal}}'       => $fmt($order->subtotal),
            '{{order_tax_rate}}'       => (string) $order->tax_rate,
            '{{order_tax_amount}}'     => $fmt($order->tax_amount),
            '{{order_total}}'          => $fmt($order->total),
            '{{client_name}}'          => $contact ? htmlspecialchars(trim($contact->first_name . ' ' . $contact->last_name)) : '',
            '{{client_organization}}'  => htmlspecialchars($contact?->organization ?? ''),
            '{{client_email}}'         => htmlspecialchars($contact?->email ?? ''),
            '{{client_address}}'       => nl2br(htmlspecialchars($contact?->address ?? '')),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $this->html_content);
    }
}
