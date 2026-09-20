<?php

namespace Aperta\TemplateSystem\Http\Controllers;

use Aperta\TemplateSystem\Models\LetterTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * LetterTemplateController
 *
 * Handles CRUD for letter & order/invoice templates.
 *
 * Prerequisites in the host app:
 *   - User model has: current_company_id, hasRole('admin') [spatie/laravel-permission]
 *   - Route model binding for {letterTemplate} -> letter_templates table
 *
 * Routes to register in the host app's routes/web.php:
 *   Route::resource('letter-templates', \Aperta\TemplateSystem\Http\Controllers\LetterTemplateController::class)
 *       ->except(['show']);
 *   Route::post('letter-templates/{letterTemplate}/duplicate',
 *       [\Aperta\TemplateSystem\Http\Controllers\LetterTemplateController::class, 'duplicate'])
 *       ->name('letter-templates.duplicate');
 */
class LetterTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $companyId = $request->user()->current_company_id;
        $canManage = $request->user()->hasRole('admin');

        $system = LetterTemplate::whereNull('company_id')->where('is_system', true)->get();
        $custom = LetterTemplate::where('company_id', $companyId)->get();

        return Inertia::render('LetterTemplates/Index', [
            'systemTemplates'   => $system,
            'customTemplates'   => $custom,
            'placeholders'      => LetterTemplate::PLACEHOLDERS,
            'orderPlaceholders' => LetterTemplate::ORDER_PLACEHOLDERS,
            'canManage'         => $canManage,
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->hasRole('admin'), 403, 'Only administrators can manage letter templates.');

        $starter = LetterTemplate::whereNull('company_id')->where('name', 'Formal')->first();

        return Inertia::render('LetterTemplates/Form', [
            'template'          => null,
            'placeholders'      => LetterTemplate::PLACEHOLDERS,
            'orderPlaceholders' => LetterTemplate::ORDER_PLACEHOLDERS,
            'starterHtml'       => $starter?->html_content ?? '',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasRole('admin'), 403, 'Only administrators can manage letter templates.');

        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'type'         => 'required|string|in:letter,order',
            'html_content' => 'required|string',
        ]);

        LetterTemplate::create([
            'company_id'   => $request->user()->current_company_id,
            'name'         => $validated['name'],
            'type'         => $validated['type'],
            'base_design'  => 'custom',
            'html_content' => $validated['html_content'],
            'is_system'    => false,
        ]);

        return redirect()->route('letter-templates.index')->with('success', 'Template created.');
    }

    public function edit(Request $request, LetterTemplate $letterTemplate): Response
    {
        abort_unless($request->user()->hasRole('admin'), 403, 'Only administrators can manage letter templates.');
        $this->authorizeAccess($letterTemplate, $request);

        return Inertia::render('LetterTemplates/Form', [
            'template'          => $letterTemplate,
            'placeholders'      => LetterTemplate::PLACEHOLDERS,
            'orderPlaceholders' => LetterTemplate::ORDER_PLACEHOLDERS,
            'starterHtml'       => null,
        ]);
    }

    public function update(Request $request, LetterTemplate $letterTemplate): RedirectResponse
    {
        abort_unless($request->user()->hasRole('admin'), 403, 'Only administrators can manage letter templates.');
        $this->authorizeAccess($letterTemplate, $request);

        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'type'         => 'required|string|in:letter,order',
            'html_content' => 'required|string',
        ]);

        $letterTemplate->update($validated);

        return redirect()->route('letter-templates.index')->with('success', 'Template updated.');
    }

    public function duplicate(Request $request, LetterTemplate $letterTemplate): RedirectResponse
    {
        abort_unless($request->user()->hasRole('admin'), 403, 'Only administrators can manage letter templates.');

        LetterTemplate::create([
            'company_id'   => $request->user()->current_company_id,
            'name'         => 'Copy of ' . $letterTemplate->name,
            'type'         => $letterTemplate->type ?? 'letter',
            'base_design'  => $letterTemplate->base_design,
            'html_content' => $letterTemplate->html_content,
            'is_system'    => false,
        ]);

        return redirect()->route('letter-templates.index')->with('success', 'Template duplicated.');
    }

    public function destroy(Request $request, LetterTemplate $letterTemplate): RedirectResponse
    {
        abort_unless($request->user()->hasRole('admin'), 403, 'Only administrators can manage letter templates.');
        $this->authorizeAccess($letterTemplate, $request);

        $letterTemplate->delete();

        return redirect()->route('letter-templates.index')->with('success', 'Template deleted.');
    }

    private function authorizeAccess(LetterTemplate $template, Request $request): void
    {
        abort_if($template->is_system, 403, 'System templates cannot be modified.');
        abort_unless($template->company_id === $request->user()->current_company_id, 403);
    }
}
