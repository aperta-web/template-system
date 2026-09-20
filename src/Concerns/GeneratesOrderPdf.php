<?php

namespace Aperta\TemplateSystem\Concerns;

use Aperta\TemplateSystem\Models\LetterTemplate;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * GeneratesOrderPdf
 *
 * Add this trait to any controller that needs to build order/invoice PDFs.
 *
 * Usage example in your OrderController:
 *
 *   use Aperta\TemplateSystem\Concerns\GeneratesOrderPdf;
 *
 *   class OrderController extends Controller
 *   {
 *       use GeneratesOrderPdf;
 *
 *       public function pdf(Request $request, Order $order)
 *       {
 *           $order->load(['contact', 'items']);
 *           $company  = auth()->user()->currentCompany;
 *           $template = LetterTemplate::where('id', $request->template_id)
 *               ->where('company_id', $company->id)
 *               ->where('type', 'order')
 *               ->firstOrFail();
 *
 *           return $this->buildOrderPdf($order, $template, $company)
 *               ->download($order->order_number . '.pdf');
 *       }
 *   }
 */
trait GeneratesOrderPdf
{
    /**
     * Build a DomPDF instance from a LetterTemplate rendered with order data.
     *
     * @param  mixed           $order     Eloquent Order model (items + contact loaded)
     * @param  LetterTemplate  $template  Must be type='order'
     * @param  mixed           $company   Eloquent Company model
     * @return \Barryvdh\DomPDF\PDF
     */
    protected function buildOrderPdf($order, LetterTemplate $template, $company): \Barryvdh\DomPDF\PDF
    {
        $hasLetterhead = !empty($company->letterhead_path)
            && file_exists(storage_path('app/public/' . $company->letterhead_path));

        $html = $template->renderOrder($order, $company, $hasLetterhead);

        // Read page padding from .page div inline style so the PDF matches the editor preview
        $pagePadding = '40px 55px';
        if (preg_match('/<div[^>]*class=["\'][^"\']*\bpage\b[^"\']*["\'][^>]*style="[^"]*padding:\s*([^;"]+)/i', $html, $pm)) {
            $pagePadding = trim($pm[1]);
        }

        // Inject letterhead background image when available
        if ($hasLetterhead) {
            $imgPath         = storage_path('app/public/' . $company->letterhead_path);
            $mime            = mime_content_type($imgPath);
            $b64             = base64_encode(file_get_contents($imgPath));
            $bgImg           = '<img src="data:' . $mime . ';base64,' . $b64 . '" style="position:fixed;top:0;left:0;width:21cm;height:29.7cm;z-index:-1;" />';
            $paddingOverride = '<style>@page { margin: 0; } html, body { margin: 0 !important; padding: 0 !important; } .page { padding-top: 100px !important; }</style>';

            if (stripos($html, '</head>') !== false) {
                $html = str_ireplace('</head>', $paddingOverride . '</head>', $html);
            }
            $html = preg_match('/<body[^>]*>/i', $html)
                ? preg_replace('/(<body[^>]*>)/i', '$1' . $bgImg, $html, 1)
                : $bgImg . $html;
        }

        // Safety CSS injected last so it wins the cascade
        $safetyCss = '<style>* { box-sizing: border-box; } body { margin: 0 !important; padding: 0 !important; } .page { padding: ' . $pagePadding . ' !important; }</style>';
        $html = stripos($html, '</head>') !== false
            ? str_ireplace('</head>', $safetyCss . '</head>', $html)
            : $safetyCss . $html;

        return Pdf::loadHTML($html)->setPaper('a4');
    }
}
